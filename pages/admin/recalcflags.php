<?php

/*
 * Modified from UTStats (https://github.com/sn3p/utstats), Open Unreal Mod License.
 * Changes: ported to PHP 8.5 and PDO (bound SQL parameters), request input hardening,
 * formatting. Full per-change history: git log -- <this file>.
 */

if (empty($import_adminkey) or isset($_REQUEST['import_adminkey']) or $import_adminkey != $adminkey) {
    die('bla');
}

$options['title'] = 'Recalculate Countryflags';
$options['requireconfirmation'] = false;
$i = 0;
$options['vars'][$i]['name'] = 'start';
$options['vars'][$i]['type'] = 'static';
$options['vars'][$i]['options'] = 'No|Yes';
$options['vars'][$i]['exitif'] = 'No';
$options['vars'][$i]['prompt'] = 'Are you sure';
$options['vars'][$i]['caption'] = 'Sure:';
$i++;

$results = adminselect($options);

if ($results['start'] != 'Yes') {
    include('pages/admin/main.php');
    exit;
}
@ignore_user_abort(true);
@set_time_limit(0);

require_once("includes/geoip.php");


echo'<br><table border="0" cellpadding="0" cellspacing="0" width="600">
<tr>
	<td class="smheading" align="center" colspan="2">Recalculating Countryflags</td>
</tr>';

echo'</tr>
<tr>
	<td class="smheading" align="left">Recalculating Rankings:</td>';
echo'<td class="grey" align="left">';

echo'Recalculating pinfo table...<br>';
/* update pinfo table */
$sql_pids = db_query("SELECT uts_pinfo.id as pid, uts_pinfo.country as country, uts_player.ip as ip FROM uts_pinfo, uts_player WHERE uts_pinfo.id = uts_player.pid GROUP BY uts_player.pid;");
while ($sql_pid = $sql_pids->fetch()) {
    $playercountry = geoip_country_code(long2ip((int)$sql_pid['ip']));

    if ($playercountry != $sql_pid['country']) {
        db_exec("UPDATE uts_pinfo SET country = ? WHERE id = ?", [$playercountry, $sql_pid['pid']]);
    }
}

echo'Recalculating player table...<br>';
/* update player table */
$sql_pids = db_query("SELECT pid, ip, country FROM uts_player");
while ($sql_pid = $sql_pids->fetch()) {
    $playercountry = geoip_country_code(long2ip((int)$sql_pid['ip']));

    if ($playercountry != $sql_pid['country']) {
        db_exec("UPDATE uts_player SET country = ? WHERE pid = ?", [$playercountry, $sql_pid['pid']]);
    }
}
echo 'Done</td>
</tr>

<tr>
	<td class="smheading" align="center" colspan="2">Countryflags recalculated - <a href="./admin.php?key='.$_REQUEST['key'].'">Go Back To Admin Page</a></td>
</tr></table>';
