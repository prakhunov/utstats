<?php

/*
 * Modified from UTStats (https://github.com/sn3p/utstats), Open Unreal Mod License.
 * Changes: ported to PHP 8.5 and PDO (bound SQL parameters), request input hardening,
 * formatting. Full per-change history: git log -- <this file>.
 */

include("../includes/config.php");
include("../includes/functions.php");
$matchid = (int) ($_GET['matchid'] ?? 0);
$query = "select name as 'N', team as 'T', frags as 'F', deaths as 'D', flag_capture as 'Caps', flag_cover as 'Covs', flag_kill as 'FK', flag_taken as 'G', gamescore as 'S' from uts_player,uts_pinfo where uts_player.pid = uts_pinfo.id and matchid = ? order by team asc, gamescore desc;";
$params = [$matchid];
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
