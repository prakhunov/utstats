<?php

/*
 * Modified from UTStats (https://github.com/sn3p/utstats), Open Unreal Mod License.
 * Changes: ported to PHP 8.5 and PDO (bound SQL parameters), request input hardening,
 * formatting. Full per-change history: git log -- <this file>.
 */

include("../includes/config.php");
include("../includes/functions.php");

$player = $_GET['player'] ?? '';
$query = "select GROUP_CONCAT(DISTINCT name SEPARATOR ', ') as names from uts_pinfo where name like ? and length(name) >= 3 limit 5;";
$params = ['%' . $player . '%'];
$result = db_query($query, $params);
header('Content-Type: application/json');
{
    $rows = [];
    while ($r = $result->fetch(PDO::FETCH_ASSOC)) {
        $rows[] = $r;
    }
    $output = json_encode($rows, JSON_PRETTY_PRINT);
    echo $output;
}
