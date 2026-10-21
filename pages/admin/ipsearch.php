<?php

if (empty($import_adminkey) or isset($_REQUEST['import_adminkey']) or $import_adminkey != $adminkey) {
    die('bla');
}

$options['requireconfirmation'] = false;
$options['title'] = 'IP Search';
$i = 0;
$options['vars'][$i]['name'] = 'ip_from';
$options['vars'][$i]['type'] = 'text';
$options['vars'][$i]['prompt'] = 'Enter the IP you want to search from:';
$options['vars'][$i]['caption'] = 'IP from:';
$i++;
$options['vars'][$i]['name'] = 'ip_to';
$options['vars'][$i]['type'] = 'text';
$options['vars'][$i]['initialvalue'] = 'ip_from';
$options['vars'][$i]['prompt'] = 'Enter the IP you want to search to:';
$options['vars'][$i]['caption'] = 'IP to:';
$i++;

$results = adminselect($options);


$ip_from = $results['ip_from'];
$ip_to = $results['ip_to'];

echo'<br><table border="0" cellpadding="0" cellspacing="0" width="600">
<tr>
	<td class="smheading" align="center" colspan="2">Players using IPs '.htmlspecialchars($ip_from).' - '.htmlspecialchars($ip_to).' and their last 5 matches using these IPs</td>
</tr>';


$sql_players = "SELECT pi.name, pi.id AS pid FROM uts_player p, uts_pinfo pi WHERE p.pid = pi.id AND p.ip BETWEEN INET_ATON(?) AND INET_ATON(?) GROUP BY pid";
$j = 0;
foreach (db_all($sql_players, [$ip_from, $ip_to]) as $r_players) {
    echo '<tr>';
    echo '<td class="dark" align="center" valign="top" width="150">';
    echo '<a class="darkhuman" href="admin.php?action=pinfo&amp;pid='.$r_players['pid'].'">'.$r_players['name'].'</a></td>';
    $sql_recent = "SELECT m.time AS time, m.id AS mid, INET_NTOA(p.ip) AS ip FROM uts_player p, uts_match m WHERE m.id = p.matchid AND p.pid = ? AND p.ip BETWEEN INET_ATON(?) AND INET_ATON(?) ORDER BY m.time DESC LIMIT 0,5";
    echo '<td class="grey">';
    echo '<table width="100%" border="0" cellspacing="0" cellpadding="2">';
    foreach (db_all($sql_recent, [$r_players['pid'], $ip_from, $ip_to]) as $r_recent) {
        $j++;
        $class = ($j % 2) ? 'grey' : 'grey2';
        echo '<tr><td class="'.$class.'" align="center" width="60%">';
        echo '<a class="'.$class.'" href="./?p=match&amp;mid='.$r_recent['mid'].'">'.mdate($r_recent['time']).'</a>';
        echo '</td><td class="'.$class.'" align="center">';
        echo $r_recent['ip'];
        echo '</td></tr>';
    }
    echo '</table></td></tr>';
}


echo'<tr>
	<td class="smheading" align="center" colspan="2"><a class="grey" href="./admin.php?key='.htmlspecialchars($_REQUEST['key']).'">Go Back To Admin Page</a></td>
</tr></table>';
