<?php
include ("../includes/config.php");
include ("../includes/functions.php");
$order = db_pick($_GET['order'] ?? '', array('', 'asc', 'desc', 'ASC', 'DESC'), '');
$order_by = db_pick($_GET['order_by'] ?? '', array('id', 'matchid', 'insta', 'playerid', 'pid', 'team', 'isabot', 'ip', 'gid', 'gametime', 'gamescore', 'lowping', 'highping', 'avgping', 'frags', 'deaths', 'kills', 'suicides', 'teamkills', 'eff', 'accuracy', 'ttl', 'flag_taken', 'flag_dropped', 'flag_return', 'flag_capture', 'flag_cover', 'flag_seal', 'flag_assist', 'flag_kill', 'flag_pickedup', 'dom_cp', 'ass_obj', 'spree_double', 'spree_triple', 'spree_multi', 'spree_mega', 'spree_ultra', 'spree_monster', 'spree_kill', 'spree_rampage', 'spree_dom', 'spree_uns', 'spree_god', 'pu_pads', 'pu_armour', 'pu_keg', 'pu_invis', 'pu_belt', 'pu_amp', 'rank'), 'matchid');
$limit = $_GET['limit'] ?? '';
$type = db_pick($_GET['type'] ?? '', array('id', 'matchid', 'insta', 'playerid', 'pid', 'team', 'isabot', 'ip', 'gid', 'gametime', 'gamescore', 'lowping', 'highping', 'avgping', 'frags', 'deaths', 'kills', 'suicides', 'teamkills', 'eff', 'accuracy', 'ttl', 'flag_taken', 'flag_dropped', 'flag_return', 'flag_capture', 'flag_cover', 'flag_seal', 'flag_assist', 'flag_kill', 'flag_pickedup', 'dom_cp', 'ass_obj', 'spree_double', 'spree_triple', 'spree_multi', 'spree_mega', 'spree_ultra', 'spree_monster', 'spree_kill', 'spree_rampage', 'spree_dom', 'spree_uns', 'spree_god', 'pu_pads', 'pu_armour', 'pu_keg', 'pu_invis', 'pu_belt', 'pu_amp', 'rank'), 'frags');
$player = $_GET['player'] ?? '';
$insta = $_GET['insta'] ?? '';
$map_key = $_GET['map_key'] ?? '';
$map_condition = "";
$params = array('player' => $player);
if($map_key != null) {
        $map_condition = "AND uts_match.mapfile like :map_key";
	$params['map_key'] = '%' . $map_key . '%';
}

if($limit == null) {
	$limit = 5;
}

$game = 'Capture the flag';
if($insta != null) {
	$game = 'Capture the flag (insta)';
}

// count >= 10 instead of 10 for 5v5 because sometimes player sub in the middle of game then stats will capture 11 players info instead of 10
$query = "select round(uts_player.$type) as stats, uts_player.matchid as matchid from uts_player,uts_pinfo,uts_games,uts_match where uts_player.pid = uts_pinfo.id and uts_pinfo.name = :player and uts_match.id = uts_player.matchid $map_condition and uts_player.gid = uts_games.id and uts_games.name like :game and uts_player.matchid in (select matchid from (select matchid, count(*) as count from uts_player group by matchid) as t1 where count >= 10) order by uts_player.$order_by $order limit :limit;";
$params['game'] = '%' . $game;
$params['limit'] = (int) $limit;

//echo "Query: $query";

$result = db_query($query, $params);
	header('Content-Type: application/json');
	{
		$rows = array();
		while($r = $result->fetch(PDO::FETCH_ASSOC)) {
			$rows[] = $r;
		}
		$output = json_encode($rows, JSON_PRETTY_PRINT);
		echo $output;
	}
?>
