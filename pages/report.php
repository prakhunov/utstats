<?php

/*
 * Modified from UTStats (https://github.com/sn3p/utstats), Open Unreal Mod License.
 * Changes: ported to PHP 8.5 and PDO (bound SQL parameters), request input hardening,
 * formatting. Full per-change history: git log -- <this file>.
 */

$id = preg_replace('/\D/', '', $_GET["id"] ?? '');
$wid = preg_replace('/\D/', '', $_GET["wid"] ?? '');
$stage = $_GET["stage"] ?? '';
$oururl = $_SERVER["HTTP_HOST"].$_SERVER["PHP_SELF"];
$oururl = str_replace("index.php", "", $oururl);
$rtype = $_GET["rtype"] ?? '';

if (empty($id)) {
    die("No ID given");
}

if ($rtype == "clanbase") {
    include("pages/report_cb.php");
}

if ($rtype == "bbcode") {
    include("pages/report/bbcode.php");
}

if ($rtype == "clanbase" && $stage == "generate") {
    include("pages/report/clanbase.php");
}
