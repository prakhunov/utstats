<?php

/*
 * Modified from UTStats (https://github.com/sn3p/utstats), Open Unreal Mod License.
 * Changes: ported to PHP 8.5 and PDO (bound SQL parameters), request input hardening,
 * formatting. Full per-change history: git log -- <this file>.
 */

include("../includes/config.php");
include("../includes/functions.php");
$list_players = $_GET['list_players'] ?? '';
$params = [];
$query = "select name from uts_pinfo;";
$result = db_query($query, $params);
header('Content-Type: application/json');
{
    $rows = [];
    while ($r = $result->fetch(PDO::FETCH_ASSOC)) {
        $rows[] = $r['name'];
    }
    $output = json_encode($rows, JSON_PRETTY_PRINT);
    echo $output;
}
