<?php
if (empty($import_adminkey) or isset($_REQUEST['import_adminkey']) or $import_adminkey != $adminkey) die('bla');

$options['title'] = 'Empty the Database';
$i = 0;
$options['vars'][$i]['name'] = 'sure';
$options['vars'][$i]['type'] = 'static';
$options['vars'][$i]['options'] = 'No|Yes';
$options['vars'][$i]['exitif'] = 'No';
$options['vars'][$i]['prompt'] = 'Are you sure (all data will be lost)?';
$options['vars'][$i]['caption'] = 'Sure:';
$i++;
$options['vars'][$i]['name'] = 'really';
$options['vars'][$i]['type'] = 'static';
$options['vars'][$i]['options'] = 'No|Yes';
$options['vars'][$i]['exitif'] = 'No';
$options['vars'][$i]['prompt'] = 'Are you really sure (this can NOT be undone)?';
$options['vars'][$i]['caption'] = 'Really sure (last warning):';
$i++;

$results = adminselect($options);

IF ($results['sure'] == "Yes" and $results['really'] == "Yes") {
  echo '<br><table border="0" cellpadding="1" cellspacing="2" width="600">
  <tr>
    <td class="smheading" align="center" colspan="2">Empty Database</td>
 </tr>
  <tr>
    <td class="smheading" align="left" width="300">
      Emptying All Tables except uts_ip2country, uts_weaponstats and uts_charttypes
    </td>';

    db_exec("TRUNCATE uts_chartdata;");
    db_exec("TRUNCATE uts_events;");
    db_exec("TRUNCATE uts_games;");
    db_exec("TRUNCATE uts_gamestype;");
    db_exec("TRUNCATE uts_killsmatrix;");
    db_exec("TRUNCATE uts_match;");
    db_exec("TRUNCATE uts_pinfo;");
    db_exec("TRUNCATE uts_player;");
    db_exec("TRUNCATE uts_rank;");
    db_exec("DELETE FROM uts_weapons WHERE id > 19");
    db_exec("ALTER TABLE uts_weapons AUTO_INCREMENT=20");
    db_exec("TRUNCATE uts_weaponstats;");

    echo '<td class="grey" align="left" width="300">Done</td>
  </tr>
  <tr>
    <td class="smheading" align="center" colspan="2">
      Database Emptied - <a href="./admin.php?key='.$_REQUEST['key'].'">Go Back To Admin Page</a>
    </td>
  </tr></table>';
} else {
  echo '<br><table border="0" cellpadding="1" cellspacing="2" width="600">
  <tr>
    <td class="smheading" align="center" colspan="2">Empty Database</td>
  </tr>
  <tr>
    <td class="smheading" align="left" width="300">Database Not Emptied</td>
    <td class="grey" align="left" width="300">Answer Was No</td>
  </tr>
  <tr>
    <td class="smheading" align="center" colspan="2">Database Not Emptied - <a href="./admin.php?key='.$_REQUEST['key'].'">Go Back To Admin Page</a></td>
  </tr></table>';
}

?>
