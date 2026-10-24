<?php

/*
 * Modified from UTStats (https://github.com/sn3p/utstats), Open Unreal Mod License.
 * Changes: ported to PHP 8.5 and PDO (bound SQL parameters), request input hardening,
 * formatting. Full per-change history: git log -- <this file>.
 */

// Requested directly (img src), not through admin.php, so check the remembered admin key here.
require(__DIR__ . '/../../includes/config.php');
if (!hash_equals($import_adminkey, (string) ($_COOKIE['uts_adminkey'] ?? ''))) {
    http_response_code(403);
    die('bla');
}

$filename = (!empty($_REQUEST['filename'])) ? stripslashes($_REQUEST['filename']) : '';
$filename = str_replace(['..', '/', '\\', '<', ':'], [], $filename);


if (!empty($filename)) {
    if (!file_exists('../../logs/utdc/'. $filename) or !is_file('../../logs/utdc/'. $filename)) {
        die('bla ' . $filename);
    }

    header("Content-type: image/jpg");

    $fp_in = fopen('../../logs/utdc/'. $filename, 'rb') or die("Can't open file");
    $blocksize = 1024;
    $buffer = '';
    $bytes = null;
    while (!feof($fp_in)) {
        $buffer .= @fread($fp_in, $blocksize);
        if ($buffer === false) {
            return(false);
        }
        if ($bytes === false) {
            return(false);
        }
    }

    $buffer = preg_replace('[\x00]', '', $buffer);
    echo base64_decode($buffer);
}
