<?php

// Country lookups from a MaxMind GeoLite2-Country .mmdb (same file FragMath uses:
// s3://fragmath-build-artifacts/geoip/GeoLite2-Country.mmdb). Path comes from
// UTSTATS_GEOIP_DB; without the file every lookup returns '' (player gets no flag).

require_once(dirname(__DIR__) . '/vendor/autoload.php');

use MaxMind\Db\Reader;

// Lowercase ISO 3166-1 alpha-2 code (as stored in uts_pinfo.country), or '' when unknown.
function geoip_country_code(string $ip): string
{
    static $reader = null;
    if ($reader === null) {
        $path = getenv('UTSTATS_GEOIP_DB') ?: '/data/GeoLite2-Country.mmdb';
        if (!is_file($path)) {
            error_log("GeoIP database not found at $path; country lookups disabled");
            $reader = false;
        } else {
            $reader = new Reader($path);
        }
    }
    if ($reader === false || filter_var($ip, FILTER_VALIDATE_IP) === false) {
        return '';
    }

    $record = $reader->get($ip);
    return strtolower($record['country']['iso_code'] ?? $record['registered_country']['iso_code'] ?? '');
}
