<?php

if (empty($import_adminkey) or isset($_REQUEST['import_adminkey']) or $import_adminkey != $adminkey) {
    die('bla');
}

$ban = (($_REQUEST['saction'] ?? '') == 'ban') ? true : false;

if ($ban) {
    $options['title'] = 'Ban Player';
} else {
    $options['title'] = 'Unban Player';
}
$i = 0;
$options['vars'][$i]['name'] = 'pid';
$options['vars'][$i]['type'] = 'player';
if ($ban) {
    $options['vars'][$i]['whereisbanned'] = 'N';
} else {
    $options['vars'][$i]['whereisbanned'] = 'Y';
}
$options['vars'][$i]['prompt'] = 'Player?';
$options['vars'][$i]['caption'] = 'Player:';
$i++;

$results = adminselect($options);


$pid = (int)$results['pid'];

if ($ban) {
    echo'<br><table border="0" cellpadding="0" cellspacing="0" width="600">
	<tr>
		<td class="smheading" align="center" colspan="2">Banning Player</td>
	</tr>
	<tr>
		<td class="smheading" align="left">Removing Player Rank:</td>';
    db_exec("DELETE FROM uts_rank WHERE pid = $pid");
    echo'<td class="grey" align="left">Done</td>
	</tr>
	<tr>
		<td class="smheading" align="left">Updating Player Record:</td>';
    db_exec("UPDATE uts_pinfo SET banned = 'Y' WHERE id = $pid");
    echo'<td class="grey" align="left">Done</td>
	</tr>
	
	<tr>
		<td class="smheading" align="center" colspan="2">Player Banned - <a href="./admin.php?key='.$_REQUEST['key'].'">Go Back To Admin Page</a></td>
	</tr></table>';
} else {
    echo'<table border="0" cellpadding="0" cellspacing="0" width="600">
	<tr>
		<td class="smheading" align="center" colspan="2">Unbanning Player</td>
	</tr>
	<tr>
		<td class="smheading" align="left">Updating Player Record:</td>';
    db_exec("UPDATE uts_pinfo SET banned = 'N' WHERE id = $pid");
    echo'<td class="grey" align="left">Done</td>
	</tr>
	<tr>
		<td class="smheading" align="left" width="200">Restoring Rankings</td>';

    $sql_nrank = "SELECT SUM(gametime) AS time, pid, gid, SUM(rank) AS rank, COUNT(matchid) AS matches FROM uts_player WHERE pid = $pid GROUP BY pid, gid";
    foreach (db_all($sql_nrank) as $r_nrank) {

        db_exec("INSERT INTO uts_rank SET time = ?, pid = $pid, gid = ?, rank = ?, prevrank = ?, matches = ?", [$r_nrank['time'], $r_nrank['gid'], $r_nrank['rank'], $r_nrank['rank'], $r_nrank['matches']]);
    }

    echo'<td class="grey" align="left" width="400">Done</td>
	</tr>	
	<tr>
		<td class="smheading" align="center" colspan="2">Player Unbanned - <a href="./admin.php?key='.$_REQUEST['key'].'">Go Back To Admin Page</a></td>
	</tr></table>';
}
