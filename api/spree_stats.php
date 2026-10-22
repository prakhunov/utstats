<?php

/*
 * Modified from UTStats (https://github.com/sn3p/utstats), Open Unreal Mod License.
 * Changes: ported to PHP 8.5 and PDO (bound SQL parameters), request input hardening,
 * formatting. Full per-change history: git log -- <this file>.
 */

include("../includes/config.php");
include("../includes/functions.php");
$player = $_GET['player'] ?? '';
$map_key = $_GET['map_key'] ?? '';
$map_condition = "";
$params = ['player' => $player];
if ($map_key != null) {
    $map_condition = "AND uts_match.mapfile like :map_key";
    $params['map_key'] = '%' . $map_key . '%';
}

$query = "select sum(spree_kill) as KillingSpree, sum(spree_rampage) as Rampage, sum(spree_dom) as Dominating, sum(spree_uns) as Unstoppable, sum(spree_god) as Godlike from uts_player,uts_pinfo,uts_games,uts_match where uts_pinfo.id = uts_player.pid and uts_match.id = uts_player.matchid and uts_player.gid = uts_games.id and uts_games.name like '%Capture the Flag%' and uts_pinfo.name = :player $map_condition;";
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
