<?php

/*
 * Modified from UTStats (https://github.com/sn3p/utstats), Open Unreal Mod License.
 * Changes: ported to PHP 8.5 and PDO (bound SQL parameters), request input hardening,
 * formatting. Full per-change history: git log -- <this file>.
 */

include("../includes/config.php");
include("../includes/functions.php");

$last_index = (int) ($_GET['last_index'] ?? 0);
$matchid = $_GET['matchid'] ?? '';
$query = "select uts_match.id as matchid, t0, t1, t2, t3, t0score, t1score, t2score, t3score, replace(mapfile, '.unr', '') as mapname, teamgame, gamename, insta, DATE_FORMAT(substring(time,1,8), '%Y-%m-%d') as date from uts_match where gamename like '%Capture the Flag%' and t2 = 0 and t3 = 0 order by id desc limit ?,1;";
$params = [$last_index];
if ($matchid != null) {
    $query = "select uts_match.id as matchid, t0, t1, t2, t3, t0score, t1score, t2score, t3score, replace(mapfile, '.unr', '') as mapname, teamgame, gamename, insta, DATE_FORMAT(substring(time,1,8), '%Y-%m-%d') as date from uts_match where gamename like '%Capture the Flag%' and t2 = 0 and t3 = 0 and uts_match.id = ?;";
    $params = [(int) $matchid];
}
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
