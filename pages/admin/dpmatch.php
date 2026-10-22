<?php

/*
 * Modified from UTStats (https://github.com/sn3p/utstats), Open Unreal Mod License.
 * Changes: ported to PHP 8.5 and PDO (bound SQL parameters), request input hardening,
 * formatting. Full per-change history: git log -- <this file>.
 */

if (empty($import_adminkey) or isset($_REQUEST['import_adminkey']) or $import_adminkey != $adminkey) {
    die('bla');
}

$options['title'] = 'Delete Player from Match';
$i = 0;
$options['vars'][$i]['name'] = 'pid';
$options['vars'][$i]['type'] = 'player';
$options['vars'][$i]['prompt'] = 'Choose the player you want to delete from a match:';
$options['vars'][$i]['caption'] = 'Player to delete:';
$i++;
$options['vars'][$i]['name'] = 'mid';
$options['vars'][$i]['type'] = 'match';
$options['vars'][$i]['whereplayer'] = 'pid';
$options['vars'][$i]['prompt'] = 'Choose the match you want to delete the player from:';
$options['vars'][$i]['caption'] = 'Match:';
$i++;

$results = adminselect($options);


$matchid = (int)$results['mid'];
$pid = (int)$results['pid'];

echo'<br><table border="0" cellpadding="0" cellspacing="0" width="600">
<tr>
	<td class="smheading" align="center" colspan="2">Delete Player From Match ID '.$matchid.'</td>
</tr>';

echo'<tr>
	<td class="smheading" align="left" width="200">Amending Players Rank:</td>';

$q_radjust = db_row("SELECT pid, gid, rank FROM uts_player WHERE id = ?", [$pid]);
if (!$q_radjust) {
    $sql_crank = false;
} else {
    $rank_pid = $q_radjust['pid'];
    $rank_gid = $q_radjust['gid'];
    $rank = $q_radjust['rank'];

    $sql_crank = db_row("SELECT id, rank, matches FROM uts_rank WHERE pid = ? AND gid = ?", [$rank_pid, $rank_gid]);
}
if (!$sql_crank) {
    echo'<td class="grey" align="left" width="400">Player not in rankings</td>';
} else {
    $rid = $sql_crank['id'];
    $newrank = $sql_crank['rank'] - $rank;
    $oldrank = $sql_crank['rank'];
    $matchcount = $sql_crank['matches'] - 1;

    db_exec("UPDATE uts_rank SET rank = ?, prevrank = ?, matches = ? WHERE id = ?", [$newrank, $oldrank, $matchcount, $rid]);
    db_exec("DELETE FROM uts_rank WHERE matches = 0");

    echo'<td class="grey" align="left" width="400">Done</td>';
}
echo'</tr>
<tr>
	<td class="smheading" align="left">Removing Kill Matrix Entries:</td>';
foreach (db_all("SELECT matchid, playerid FROM uts_player WHERE pid = ? and matchid = ?", [$pid, $matchid]) as $r_match) {
    db_exec("DELETE FROM uts_killsmatrix WHERE matchid = ? AND (killer = ? OR victim = ?)", [$r_match['matchid'], $r_match['playerid'], $r_match['playerid']]);
}
echo'<td class="grey" align="left">Done</td>
</tr>


<tr>
	<td class="smheading" align="left" width="200">Removing Player Weapon Stats:</td>';
db_exec("DELETE FROM uts_weaponstats WHERE matchid = ? AND pid = ?", [$matchid, $pid]);
echo'<td class="grey" align="left" width="400">Done</td>
</tr>
<tr>
	<td class="smheading" align="left" width="200">Removing Player Events:</td>';
db_exec("DELETE e.* FROM uts_events as e, uts_player as p WHERE p.pid = ? AND e.matchid = ? AND p.playerid = e.playerid AND p.matchid = e.matchid", [$pid, $matchid]);
echo'<td class="grey" align="left" width="400">Done</td>
</tr>
<tr>
	<td class="smheading" align="left" width="200">Removing Player From Match:</td>';
db_exec("DELETE FROM uts_player WHERE matchid IN (?,'0') AND pid = ?", [$matchid, $pid]);
echo'<td class="grey" align="left" width="400">Done</td>
</tr>
<tr>
	<td class="smheading" align="left" width="200">Amending Player Weapon Stats:</td>';
db_exec("DELETE FROM uts_weaponstats WHERE matchid IN (?,'0') AND pid = ?", [$matchid, $pid]);

foreach (db_all("SELECT SUM(kills) AS kills, SUM(shots) AS shots, SUM(hits) as hits, SUM(damage) as damage, AVG(acc) AS acc FROM uts_weaponstats WHERE pid = ?  GROUP BY weapon", [$pid]) as $r_weaponstats) {
    db_exec("INSERT INTO uts_weaponstats SET matchid='0', pid=?, kills=?, shots=?, hits=?, damage=?, acc=?", [$pid, $r_weaponstats['kills'], $r_weaponstats['shots'], $r_weaponstats['hits'], $r_weaponstats['damage'], $r_weaponstats['acc']]);
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
	<td class="smheading" align="center" colspan="2">Match Deleted - <a href="./admin.php?key='.$_REQUEST['key'].'">Go Back To Admin Page</a></td>
</tr></table>';
