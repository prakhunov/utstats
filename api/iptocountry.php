<?php

/*
 * Query server for the UT99 IpToCountry 1.6 mutator (ipToCountry.LinkActor on the FragMath
 * game servers), replacing the third-party hosts of its ini. Same request and reply as the
 * mutator's own master server script (iptocountry16.php):
 *
 *   GET /api/iptocountry.php?ip=a.b.c.d[,e.f.g.h,...]
 *   -> hh:mm a.b.c.d:a.b.c.d:UNITED STATES:USA:us[,e.f.g.h:...]
 *
 * Each address gives five ":" separated fields: address, host name, country name,
 * three-letter code, lowercase two-letter code (the name of the flag texture). The host
 * name field repeats the address: no reverse DNS is done here. An address with no country
 * (private, unassigned, not in the database) gives "Unknown" and two empty fields, as the
 * original does. hh:mm is UTC.
 *
 * The mutator speaks plain HTTP only, so Caddy serves this one path without TLS. It reads
 * the reply up to the first line break, so the body is one line with no line break.
 * Country data: the GeoLite2-Country database the importer uses (includes/geoip.php).
 */

require_once("../includes/geoip.php");

// The mutator asks for at most its whole queue in one request.
const IPTOCOUNTRY_MAX_ADDRESSES = 64;

$countries = require("../includes/iptocountry_countries.php");

$addresses = array_slice(
    array_values(array_filter(
        explode(',', (string) ($_GET['ip'] ?? '')),
        static fn (string $ip): bool => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
    )),
    0,
    IPTOCOUNTRY_MAX_ADDRESSES
);

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
if ($addresses === []) {
    http_response_code(400);
    exit;
}

$entries = array_map(static function (string $ip) use ($countries): string {
    $code = geoip_country_code($ip);
    if ($code === '') {
        return "$ip:$ip:Unknown::";
    }
    [$alpha3, $name] = $countries[$code] ?? ['', 'Unknown (' . strtoupper($code) . ')'];
    return "$ip:$ip:$name:$alpha3:$code";
}, $addresses);

$body = gmdate('H:i') . ' ' . implode(',', $entries);
header('Content-Length: ' . strlen($body));
echo $body;
