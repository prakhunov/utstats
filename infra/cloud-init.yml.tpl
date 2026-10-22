#cloud-config
# UTStats host bootstrap (AlmaLinux 10 aarch64). Installs podman, the AWS CLI and the SSM
# agent, then runs the latest release's deploy/install.sh, which owns everything else
# (quadlet units, importer timer, secrets, GeoIP, image build). Re-deploys run the same
# script via `just deploy` (SSM send-command), so this file rarely needs to change.
#
# Template variables: ${region}, ${releases_uri}, ${bucket_region}

packages:
  - podman
  - unzip
  - jq
  - tar
  - gzip

write_files:
  # Prefer IPv4 (same reason as FragMath's hosts: dead IPv6 egress hangs signed AWS calls).
  - path: /etc/gai.conf
    content: |
      precedence ::ffff:0:0/96 100

  - path: /usr/local/sbin/utstats-deploy
    permissions: "0755"
    content: |
      #!/usr/bin/env bash
      # Fetch a release tarball from S3 and run its installer. Usage: utstats-deploy [release]
      set -euo pipefail
      release=$${1:-latest}
      work=$(mktemp -d)
      trap 'rm -rf "$work"' EXIT
      aws s3 cp --region ${bucket_region} "${releases_uri}/releases/$release.tar.gz" "$work/release.tar.gz"
      mkdir -p "$work/src"
      tar -xzf "$work/release.tar.gz" -C "$work/src"
      UTSTATS_REGION=${region} UTSTATS_BUCKET_REGION=${bucket_region} UTSTATS_RELEASES_URI=${releases_uri} \
        bash "$work/src/deploy/install.sh" "$work/src"

runcmd:
  # 1 GB swap: headroom for importer runs next to MariaDB on 2 GB RAM.
  - [sh, -c, "fallocate -l 1G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile && echo '/swapfile none swap defaults 0 0' >> /etc/fstab"]
  # AWS CLI v2
  - [sh, -c, "curl -fsSL https://awscli.amazonaws.com/awscli-exe-linux-aarch64.zip -o /tmp/awscli.zip && unzip -q /tmp/awscli.zip -d /tmp && /tmp/aws/install && rm -rf /tmp/aws /tmp/awscli.zip"]
  # SSM agent (Session Manager access; no SSH port is open)
  - [sh, -c, "dnf install -y https://s3.${region}.amazonaws.com/amazon-ssm-${region}/latest/linux_arm64/amazon-ssm-agent.rpm && systemctl enable --now amazon-ssm-agent"]
  - [/usr/local/sbin/utstats-deploy]
