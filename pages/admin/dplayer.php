<?php

if (empty($import_adminkey) or isset($_REQUEST['import_adminkey']) or $import_adminkey != $adminkey) {
    die('bla');
}

$options['title'] = 'Delete Player';
$i = 0;
$options['vars'][$i]['name'] = 'pid';
$options['vars'][$i]['type'] = 'player';
$options['vars'][$i]['prompt'] = 'Choose the player you want to delete:';
$options['vars'][$i]['caption'] = 'Player to delete:';
$i++;

$results = adminselect($options);


$pid = (int)$results['pid'];
$playerid = $pid;

echo'<table border="0" cellpadding="0" cellspacing="0" width="600">
<tr>
	<td class="smheading" align="center" colspan="2">Deleting Player</td>
</tr>
<tr>
	<td class="smheading" align="left">Removing Kill Matrix Entries:</td>';
foreach (db_all("SELECT matchid, playerid FROM uts_player WHERE pid = ?", [$pid]) as $r_match) {
    db_exec("DELETE FROM uts_killsmatrix WHERE matchid = ? AND (killer = ? OR victim = ?)", [$r_match['matchid'], $r_match['playerid'], $r_match['playerid']]);
}
echo'<td class="grey" align="left">Done</td>
</tr>

<tr>
	<td class="smheading" align="left" width="300">Removing Player Info:</td>';
$r_pinfo = db_row("SELECT banned FROM uts_pinfo WHERE id = ?", [$playerid]);
if ($r_pinfo['banned'] != 'Y') {
    db_exec("DELETE FROM uts_pinfo WHERE id = ?", [$playerid]);
    echo'<td class="grey" align="left" width="300">Done</td>';
} else {
    echo'<td class="grey" align="left" width="300">No (player banned)</td>';
}
echo '
</tr>
<tr>
	<td class="smheading" align="left">Removing Player Match Events:</td>';
db_exec("DELETE e.* FROM uts_player as p, uts_events as e WHERE p.pid = ? AND p.playerid = e.playerid AND p.matchid = e.matchid", [$playerid]);
echo'<td class="grey" align="left">Done</td>
</tr>
<tr>
	<td class="smheading" align="left">Removing Player Match Records:</td>';
db_exec("DELETE FROM uts_player WHERE pid = ?", [$playerid]);
echo'<td class="grey" align="left">Done</td>
</tr>
<tr>
	<td class="smheading" align="left">Removing Player Rank:</td>';
db_exec("DELETE FROM uts_rank WHERE pid = ?", [$playerid]);
echo'<td class="grey" align="left">Done</td>
</tr>
<tr>
	<td class="smheading" align="left">Removing Player Weapon Stats:</td>';
db_exec("DELETE FROM uts_weaponstats WHERE pid = ?", [$playerid]);
echo'<td class="grey" align="left">Done</td>
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
	<td class="smheading" align="center" colspan="2">Player Deleted - <a href="./admin.php?key='.$_REQUEST['key'].'">Go Back To Admin Page</a></td>
</tr></table>';
