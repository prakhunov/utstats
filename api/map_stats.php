<?php

include("../includes/config.php");
include("../includes/functions.php");
$player = $_GET['player'] ?? '';
$map_type = $_GET['map_type'] ?? '';
$limit = $_GET['limit'] ?? '';
if ($map_type == null) {
    $map_type = 'CTF';
}
if ($limit == null) {
    $limit = 50;
}
$map_select_exp = "substring_index(substring_index(substring_index(substring_index(substring_index(replace(substring_index(substring_index(mapfile, '-', 2), '-', -1), '.unr', ''), 'LE', 1), '_', 1), ']', 1),'2017',1),'BETA', 1)";
if ($player == null) {
    $query = "select $map_select_exp as Map, count(*) as Played from uts_match where mapfile like ? group by map order by Played desc limit ?;";
    $params = ['%' . $map_type . '%', (int) $limit];
} elseif (strcasecmp($map_type, 'CTF') == 0 or strcasecmp($map_type, 'DOM') == 0) {
    $query = "select Map, count(won) as Played, sum(won) as Won from (select $map_select_exp as Map, case when ((team = 0 and t0score > t1score) or (team = 1 and t1score > t0score)) then 1 else 0 end as won from uts_match,uts_pinfo,uts_player where uts_match.id = uts_player.matchid and uts_player.pid = uts_pinfo.id and mapfile like ? and uts_pinfo.name = ?) as t group by map order by played desc limit ?";
    $params = ['%' . $map_type . '%', $player, (int) $limit];
} else {
    $query = "select $map_select_exp as Map, count(*) as Played from uts_match,uts_pinfo,uts_player where uts_match.id = uts_player.matchid and uts_player.pid = uts_pinfo.id and mapfile like ? and uts_pinfo.name = ? group by map order by Played desc limit ?;";
    $params = ['%' . $map_type . '%', $player, (int) $limit];
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
