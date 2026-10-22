<?php

/*
 * Modified from UTStats (https://github.com/sn3p/utstats), Open Unreal Mod License.
 * Changes: ported to PHP 8.5 and PDO (bound SQL parameters), request input hardening,
 * formatting. Full per-change history: git log -- <this file>.
 */

$mid = preg_replace('/\D/', '', $_GET['mid'] ?? '');
$pid = preg_replace('/\D/', '', $_GET['pid'] ?? '');

if ($pid != "") {
    include("match_player.php");
} else {
    include("match_info.php");
}
