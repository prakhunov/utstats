variable "region" {
  description = "Region for the UTStats host (players are mostly EU)"
  type        = string
  default     = "eu-central-1"
}

variable "instance_type" {
  type    = string
  default = "t4g.small"
}

variable "root_volume_gb" {
  description = "Root EBS volume (OS + MariaDB + imported log backups)"
  type        = number
  default     = 30
}

variable "hostname" {
  description = "Canonical name in the banko.gg zone; utstats.passionategaming.eu is a CNAME to it at NFO"
  type        = string
  default     = "utstats.ut.banko.gg"
}

variable "site_names" {
  description = "Hostnames Caddy serves and gets certificates for"
  type        = list(string)
  default     = ["utstats.ut.banko.gg", "utstats.passionategaming.eu"]
}

variable "artifacts_bucket" {
  description = "Existing FragMath bucket holding the GeoIP mmdb and UTStats releases/dumps (us-east-2)"
  type        = string
  default     = "fragmath-build-artifacts"
}

variable "geoip_key" {
  type    = string
  default = "geoip/GeoLite2-Country.mmdb"
}

variable "release_prefix" {
  description = "S3 prefix for UTStats release tarballs and DB dumps"
  type        = string
  default     = "utstats"
}

variable "ssm_prefix" {
  description = "SSM Parameter Store path holding the app's env vars (SecureString)"
  type        = string
  default     = "/utstats"
}
