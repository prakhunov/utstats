<?php
if (empty($import_adminkey) or isset($_REQUEST['import_adminkey']) or $import_adminkey != $adminkey) die('bla');
	
$options['title'] = 'Merge Players';
$i = 0;
$options['vars'][$i]['name'] = 'mplayer1';
$options['vars'][$i]['type'] = 'player';
$options['vars'][$i]['prompt'] = 'Choose player to merge to:';
$options['vars'][$i]['caption'] = 'Player to merge to:';
$i++;
$options['vars'][$i]['name'] = 'mplayer2';
$options['vars'][$i]['type'] = 'player';
$options['vars'][$i]['prompt'] = 'Choose player to merge from:';
$options['vars'][$i]['caption'] = 'Player to merge from:';
$options['vars'][$i]['exclude'] = 'mplayer1';
$i++;

$results = adminselect($options);



$mplayer1 = (int)$results['mplayer1'];
$mplayer2 = (int)$results['mplayer2'];


$mp1name = db_row("SELECT name FROM uts_pinfo WHERE id = $mplayer1");
$mp2name = db_row("SELECT name FROM uts_pinfo WHERE id = $mplayer2");

echo'<br><table border="0" cellpadding="0" cellspacing="0" width="600">
<tr>
	<td class="smheading" align="center" colspan="2">Merging '.$mp2name['name'].' Into '.$mp1name['name'].'</td>
</tr>
<tr>
	<td class="smheading" align="left" width="200">Removing Info Records</td>';
db_exec("DELETE FROM uts_pinfo WHERE id = $mplayer2");
	echo'<td class="grey" align="left" width="400">Done</td>
</tr>
<tr>
	<td class="smheading" align="left" width="200">Updating Player Records</td>';
db_exec("UPDATE uts_player SET pid = $mplayer1  WHERE pid = $mplayer2");
	echo'<td class="grey" align="left" width="400">Done</td>
</tr>
<tr>
	<td class="smheading" align="left" width="200">Updating Weapon Records</td>';
db_exec("UPDATE uts_weaponstats SET pid = $mplayer1  WHERE pid = $mplayer2");
db_exec("DELETE FROM uts_weaponstats WHERE pid = $mplayer2");
	echo'<td class="grey" align="left" width="400">Done</td>
</tr>
<tr>
	<td class="smheading" align="left" width="200">Amending Player Weapon Stats:</td>';
db_exec("DELETE FROM uts_weaponstats WHERE matchid='0' AND pid = '$mplayer1'");

foreach (db_all("SELECT weapon, SUM(kills) AS kills, SUM(shots) AS shots, SUM(hits) as hits, SUM(damage) as damage, AVG(acc) AS acc FROM uts_weaponstats WHERE pid = '$mplayer1'  GROUP BY weapon") as $r_weaponstats) {
	db_exec("INSERT INTO uts_weaponstats SET matchid='0', pid='$mplayer1',  weapon=?, kills=?, shots=?, hits=?, damage=?, acc=?", [$r_weaponstats['weapon'], $r_weaponstats['kills'], $r_weaponstats['shots'], $r_weaponstats['hits'], $r_weaponstats['damage'], $r_weaponstats['acc']]);
}
	echo'<td class="grey" align="left" width="400">Done</td>
</tr>
<tr>
	<td class="smheading" align="left" width="200">Updating First Blood Records</td>';
db_exec("UPDATE uts_match SET firstblood = $mplayer1  WHERE firstblood = $mplayer2");
	echo'<td class="grey" align="left" width="400">Done</td>
</tr>
<tr>
	<td class="smheading" align="left" width="200">Temporary Rank</td>';
db_exec("UPDATE uts_rank SET pid = $mplayer2 WHERE pid= $mplayer1");
	echo'<td class="grey" align="left" width="400">Done</td>
</tr>
<tr>
	<td class="smheading" align="left" width="200">Creating New Rank</td>';

$sql_nrank = "SELECT SUM(time) AS time, pid, gid, AVG(rank) AS rank, AVG(prevrank) AS prevrank, SUM(matches) AS matches FROM uts_rank WHERE pid = $mplayer2 GROUP BY pid, gid";
foreach (db_all($sql_nrank) as $r_nrank) {

	db_exec("INSERT INTO uts_rank SET time = ?, pid = $mplayer1, gid = ?, rank = ?, prevrank = ?, matches = ?", [$r_nrank['time'], $r_nrank['gid'], $r_nrank['rank'], $r_nrank['prevrank'], $r_nrank['matches']]);
}

	echo'<td class="grey" align="left" width="400">Done</td>
</tr>
<tr>
	<td class="smheading" align="left" width="200">Removing Old Rank</td>';
db_exec("DELETE FROM uts_rank WHERE pid = $mplayer2");
	echo'<td class="grey" align="left" width="400">Done</td>
</tr>
<tr>
	<td class="smheading" align="center" colspan="2">Player Record Merged - <a href="./admin.php?key='.$_REQUEST['key'].'">Go Back To Admin Page</a></td>
</tr>
</table>';

?>
