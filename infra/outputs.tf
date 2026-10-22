output "instance_id" {
  value = aws_instance.utstats.id
}

output "public_ip" {
  value = aws_eip.utstats.public_ip
}

output "hostname" {
  value = aws_route53_record.utstats.fqdn
}
