<?php
if (empty($import_adminkey) or isset($_REQUEST['import_adminkey']) or $import_adminkey != $adminkey) die('bla');
	
$options['title'] = 'Delete match';
$i = 0;
$options['vars'][$i]['name'] = 'server';
$options['vars'][$i]['type'] = 'server';
$options['vars'][$i]['prompt'] = 'Choose the server where the match took place:';
$options['vars'][$i]['caption'] = 'Server:';
$i++;
$options['vars'][$i]['name'] = 'mid';
$options['vars'][$i]['type'] = 'match';
$options['vars'][$i]['whereserver'] = 'server';
$options['vars'][$i]['prompt'] = 'Choose the match to delete:';
$options['vars'][$i]['caption'] = 'Match to delete:';
$i++;

$results = adminselect($options);


$matchid = (int)$results['mid'];

echo'<br><table border="0" cellpadding="0" cellspacing="0" width="600">
<tr>
	<td class="smheading" align="center" colspan="2">Deleting Match ID '.$matchid.'</td>
</tr>
<tr>
	<td class="smheading" align="left" width="200">Adjusting Rankings</td>';
$sql_radjust = "SELECT pid, gid, rank FROM uts_player WHERE matchid = ?";
$pids = array();
foreach (db_all($sql_radjust, [$matchid]) as $r_radjust) {

	$pid = $r_radjust['pid'];
	$pids[] = $pid;
	$gid = $r_radjust['gid'];
	$rank = $r_radjust['rank'];

	$sql_crank = db_row("SELECT id, rank, matches FROM uts_rank WHERE pid = ? AND gid = ?", [$pid, $gid]);
	if (!$sql_crank) continue;
	
	$rid = $sql_crank['id'];
	$newrank = $sql_crank['rank']-$rank;
	$oldrank = $sql_crank['rank'];
	$matchcount = $sql_crank['matches']-1;

	db_exec("UPDATE uts_rank SET rank = ?, prevrank = ?, matches = ? WHERE id = ?", [$newrank, $oldrank, $matchcount, $rid]);
	db_exec("DELETE FROM uts_rank WHERE matches = 0");
}
	echo'<td class="grey" align="left" width="700">Done</td>
</tr>
<tr>
	<td class="smheading" align="left" width="200">Removing Match Record:</td>';
db_exec("DELETE FROM uts_match WHERE id = ?", [$matchid]);
	echo'<td class="grey" align="left" width="400">Done</td>
</tr>
<tr>
	<td class="smheading" align="left" width="200">Removing Player Records:</td>';
db_exec("DELETE FROM uts_player WHERE matchid = ?", [$matchid]);
	echo'<td class="grey" align="left" width="400">Done</td>
</tr>
<tr>
	<td class="smheading" align="left" width="200">Removing Kill Matrix Entries:</td>';
db_exec("DELETE FROM uts_killsmatrix WHERE matchid = ?", [$matchid]);
	echo'<td class="grey" align="left" width="400">Done</td>
</tr>
<tr>
	<td class="smheading" align="left" width="200">Removing Weapon Stats:</td>';
db_exec("DELETE FROM uts_weaponstats WHERE matchid = ?", [$matchid]);
	echo'<td class="grey" align="left" width="400">Done</td>
</tr>
<tr>
	<td class="smheading" align="left" width="200">Amending Player Weapon Stats:</td>';
foreach($pids as $pid) {
	db_exec("DELETE FROM uts_weaponstats WHERE matchid IN (?,'0') AND pid = ?", [$matchid, $pid]);

	foreach (db_all("SELECT weapon, SUM(kills) AS kills, SUM(shots) AS shots, SUM(hits) as hits, SUM(damage) as damage, AVG(acc) AS acc FROM uts_weaponstats WHERE pid = ?  GROUP BY weapon", [$pid]) as $r_weaponstats) {
		db_exec("INSERT INTO uts_weaponstats SET matchid='0', pid=?, weapon=?, kills=?, shots=?, hits=?, damage=?, acc=?", [$pid, $r_weaponstats['weapon'], $r_weaponstats['kills'], $r_weaponstats['shots'], $r_weaponstats['hits'], $r_weaponstats['damage'], $r_weaponstats['acc']]);
	}
}
	echo'<td class="grey" align="left" width="400">Done</td>
</tr>
<tr>
	<td class="smheading" align="left" width="200">Amending Global Weapon Stats:</td>';
	db_exec("DELETE FROM uts_weaponstats WHERE matchid='0' AND pid='0'");

	foreach (db_all("SELECT weapon, SUM(kills) AS kills, SUM(shots) AS shots, SUM(hits) as hits, SUM(damage) as damage, AVG(acc) AS acc FROM uts_weaponstats WHERE matchid = '0'  GROUP BY weapon") as $r_weaponstats) {
		db_exec("INSERT INTO uts_weaponstats SET matchid='0', pid='0', weapon=?, kills=?, shots=?, hits=?, damage=?, acc=?", [$r_weaponstats['weapon'], $r_weaponstats['kills'], $r_weaponstats['shots'], $r_weaponstats['hits'], $r_weaponstats['damage'], $r_weaponstats['acc']]);
	}

	echo'<td class="grey" align="left" width="400">Done</td>
</tr>
<tr>
	<td class="smheading" align="left" width="200">Removing events:</td>';
db_exec("DELETE FROM uts_events WHERE matchid = ?", [$matchid]);
	echo'<td class="grey" align="left" width="400">Done</td>
</tr>
<tr>
	<td class="smheading" align="center" colspan="2">Match Deleted - <a href="./admin.php?key='.$_REQUEST['key'].'">Go Back To Admin Page</a></td>
</tr></table>';


?>
