# UTStats host: one t4g.small in the default VPC running MariaDB + PHP-FPM + Caddy as
# podman quadlets (deploy/), plus a minutely systemd timer for the log importer.
# Access is SSM Session Manager only (no SSH port open).

data "aws_caller_identity" "current" {}

data "aws_vpc" "default" {
  default = true
}

data "aws_subnet" "a" {
  vpc_id            = data.aws_vpc.default.id
  availability_zone = "${var.region}a"
  default_for_az    = true
}

# banko.gg is managed by FragMath's infra; this state only owns its own record in it.
data "aws_route53_zone" "banko" {
  name = "banko.gg"
}

# --- AMI (AlmaLinux 10 aarch64, same catalog lookup as FragMath) --------------

data "http" "almalinux_amis" {
  url = "https://wiki.almalinux.org/ci-data/aws_amis.csv"
}

locals {
  almalinux_ami = one(distinct([
    for line in split("\n", data.http.almalinux_amis.response_body) :
    split(",", replace(line, "\"", ""))[3]
    if length(split(",", line)) >= 5
    && split(",", replace(line, "\"", ""))[2] == var.region
    && startswith(split(",", replace(line, "\"", ""))[1], "10.")
    && split(",", replace(line, "\"", ""))[4] == "aarch64"
  ]))
  releases_uri = "s3://${var.artifacts_bucket}/${var.release_prefix}"
}

# --- Network ------------------------------------------------------------------

resource "aws_security_group" "utstats" {
  name        = "utstats"
  description = "UTStats web (HTTP/HTTPS only)"
  vpc_id      = data.aws_vpc.default.id
}

resource "aws_vpc_security_group_ingress_rule" "http" {
  security_group_id = aws_security_group.utstats.id
  cidr_ipv4         = "0.0.0.0/0"
  ip_protocol       = "tcp"
  from_port         = 80
  to_port           = 80
}

resource "aws_vpc_security_group_ingress_rule" "https" {
  security_group_id = aws_security_group.utstats.id
  cidr_ipv4         = "0.0.0.0/0"
  ip_protocol       = "tcp"
  from_port         = 443
  to_port           = 443
}

resource "aws_vpc_security_group_ingress_rule" "http3" {
  security_group_id = aws_security_group.utstats.id
  cidr_ipv4         = "0.0.0.0/0"
  ip_protocol       = "udp"
  from_port         = 443
  to_port           = 443
}

# Outbound: AWS APIs, image pulls, ACME, FTP (control + passive data ports) and UDP
# server queries to the game servers.
resource "aws_vpc_security_group_egress_rule" "all" {
  security_group_id = aws_security_group.utstats.id
  cidr_ipv4         = "0.0.0.0/0"
  ip_protocol       = "-1"
}

# --- IAM ----------------------------------------------------------------------

resource "aws_iam_role" "utstats" {
  name = "utstats-host"
  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Effect    = "Allow"
      Principal = { Service = "ec2.amazonaws.com" }
      Action    = "sts:AssumeRole"
    }]
  })
}

resource "aws_iam_role_policy_attachment" "ssm_core" {
  role       = aws_iam_role.utstats.name
  policy_arn = "arn:aws:iam::aws:policy/AmazonSSMManagedInstanceCore"
}

resource "aws_iam_role_policy" "utstats" {
  name = "utstats-host"
  role = aws_iam_role.utstats.id
  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Sid      = "ReadReleasesGeoip"
        Effect   = "Allow"
        Action   = ["s3:GetObject"]
        Resource = ["arn:aws:s3:::${var.artifacts_bucket}/${var.release_prefix}/*", "arn:aws:s3:::${var.artifacts_bucket}/${var.geoip_key}"]
      },
      {
        Sid       = "ListReleases"
        Effect    = "Allow"
        Action    = ["s3:ListBucket"]
        Resource  = "arn:aws:s3:::${var.artifacts_bucket}"
        Condition = { StringLike = { "s3:prefix" = ["${var.release_prefix}/*"] } }
      },
      {
        Sid    = "ReadAppParams"
        Effect = "Allow"
        Action = ["ssm:GetParametersByPath", "ssm:GetParameters", "ssm:GetParameter"]
        # GetParametersByPath authorizes against the path itself, the others against each parameter.
        Resource = [
          "arn:aws:ssm:${var.region}:${data.aws_caller_identity.current.account_id}:parameter${var.ssm_prefix}",
          "arn:aws:ssm:${var.region}:${data.aws_caller_identity.current.account_id}:parameter${var.ssm_prefix}/*",
        ]
      },
      {
        Sid       = "DecryptAppParams"
        Effect    = "Allow"
        Action    = "kms:Decrypt"
        Resource  = "*"
        Condition = { StringEquals = { "kms:ViaService" = "ssm.${var.region}.amazonaws.com" } }
      },
    ]
  })
}

resource "aws_iam_instance_profile" "utstats" {
  name = "utstats-host"
  role = aws_iam_role.utstats.name
}

# --- App secrets ----------------------------------------------------------------
# The /utstats/* SecureStrings (DB creds, admin key, FTP servers) are created and
# updated ONLY by `just secrets-push`, never by tofu: a managed aws_ssm_parameter
# refreshes its real value into state even with ignore_changes. They were managed
# here briefly; this block drops them from state without deleting them.
removed {
  from = aws_ssm_parameter.env
  lifecycle {
    destroy = false
  }
}

# Importer on/off switch (see deploy/install.sh). Flip to "true" only after the old
# host's importer cron is stopped: both would race to pull+delete the same FTP logs.
resource "aws_ssm_parameter" "import_enabled" {
  name  = "${var.ssm_prefix}/IMPORT_ENABLED"
  type  = "String"
  value = "false"
  lifecycle {
    ignore_changes = [value]
  }
}

# --- Host ---------------------------------------------------------------------

resource "aws_instance" "utstats" {
  ami                    = local.almalinux_ami
  instance_type          = var.instance_type
  subnet_id              = data.aws_subnet.a.id
  vpc_security_group_ids = [aws_security_group.utstats.id]
  iam_instance_profile   = aws_iam_instance_profile.utstats.name

  metadata_options {
    http_tokens                 = "required"
    http_put_response_hop_limit = 1 # containers can't reach instance credentials
  }

  root_block_device {
    volume_type = "gp3"
    volume_size = var.root_volume_gb
    encrypted   = true
    tags        = { Name = "utstats-root", Snapshot = "utstats-daily" }
  }

  user_data_replace_on_change = true
  user_data = templatefile("${path.module}/cloud-init.yml.tpl", {
    region        = var.region
    releases_uri  = local.releases_uri
    bucket_region = "us-east-2"
  })

  tags = { Name = "utstats" }

  lifecycle {
    # AlmaLinux catalog is a live CSV; rebuild onto a newer AMI deliberately with
    #   tofu apply -replace=aws_instance.utstats
    ignore_changes = [ami]
  }
}

resource "aws_eip" "utstats" {
  instance = aws_instance.utstats.id
  domain   = "vpc"
  tags     = { Name = "utstats" }
}

resource "aws_route53_record" "utstats" {
  zone_id = data.aws_route53_zone.banko.zone_id
  name    = var.hostname
  type    = "A"
  ttl     = 300
  records = [aws_eip.utstats.public_ip]
}

# --- Backups: daily EBS snapshots of the root volume, keep 14 -----------------

resource "aws_iam_role" "dlm" {
  name = "utstats-dlm"
  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Effect    = "Allow"
      Principal = { Service = "dlm.amazonaws.com" }
      Action    = "sts:AssumeRole"
    }]
  })
}

resource "aws_iam_role_policy_attachment" "dlm" {
  role       = aws_iam_role.dlm.name
  policy_arn = "arn:aws:iam::aws:policy/service-role/AWSDataLifecycleManagerServiceRole"
}

resource "aws_dlm_lifecycle_policy" "daily" {
  description        = "UTStats daily root volume snapshots"
  execution_role_arn = aws_iam_role.dlm.arn
  state              = "ENABLED"

  policy_details {
    resource_types = ["VOLUME"]
    target_tags    = { Snapshot = "utstats-daily" }

    schedule {
      name      = "daily"
      copy_tags = true
      create_rule {
        interval      = 24
        interval_unit = "HOURS"
        times         = ["03:30"]
      }
      retain_rule {
        count = 14
      }
    }
  }
}
