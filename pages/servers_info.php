<?php

/*
 * Modified from UTStats (https://github.com/sn3p/utstats), Open Unreal Mod License.
 * Changes: ported to PHP 8.5 and PDO (bound SQL parameters), request input hardening,
 * formatting. Full per-change history: git log -- <this file>.
 */

$serverip = (string) ($_GET['serverip'] ?? '');
$serverip_html = htmlspecialchars($serverip);

// Firstly we need to work out First Last Next Prev pages
$scount = db_count("SELECT id FROM uts_match WHERE serverip = ?", [$serverip]);

$ecount = $scount / 25;
$ecount2 = number_format($ecount, 0, '.', '');

if ($ecount > $ecount2) {
    $ecount2 = $ecount2 + 1;
}

$fpage = 0;
if ($ecount < 1) {
    $lpage = 0;
} else {
    $lpage = $ecount2 - 1;
}

$cpage = preg_replace('/\D/', '', (string) ($_GET["page"] ?? ''));
if ($cpage == "") {
    $cpage = "0";
}
$qpage = $cpage * 25;

$tfpage = $cpage + 1;
$tlpage = $lpage + 1;

$ppage = $cpage - 1;
$ppageurl = "<a class=\"pages\" href=\"./?p=sinfo&amp;serverip=$serverip_html&amp;page=$ppage\">[Previous]</a>";
if ($ppage < "0") {
    $ppageurl = "[Previous]";
}

$npage = $cpage + 1;
$npageurl = "<a class=\"pages\" href=\"./?p=sinfo&amp;serverip=$serverip_html&amp;page=$npage\">[Next]</a>";
if ($npage >= "$ecount") {
    $npageurl = "[Next]";
}

$fpageurl = "<a class=\"pages\" href=\"./?p=sinfo&amp;serverip=$serverip_html&amp;page=$fpage\">[First]</a>";
if ($cpage == "0") {
    $fpageurl = "[First]";
}

$lpageurl = "<a class=\"pages\" href=\"./?p=sinfo&amp;serverip=$serverip_html&amp;page=$lpage\">[Last]</a>";
if ($cpage == "$lpage") {
    $lpageurl = "[Last]";
}


// Get the last match entry for this server

$serverinfo = db_row("SELECT time, servername, serverinfo, gameinfo, mutators FROM uts_match WHERE serverip = ? ORDER BY time DESC LIMIT 0,1", [$serverip]);
$matchdate = mdate($serverinfo['time']);

echo'
<table class = "zebra box" border="0" cellpadding="0" cellspacing="0" width="700">
  <tbody><tr>
    <th class="heading" align="center" colspan="2">'.$serverinfo['servername'].'</th>
  </tr>
  <tr>
    <th align="center" width="110">Last Match</th>
    <td align="center" width="305">'.$matchdate.'</td>
  </tr>
  <tr>
    <th align="center">Server Info</th>
    <td align="center">'.$serverinfo['serverinfo'].'</td>
  </tr>
  <tr>
    <th align="center">Mutators</td>
    <td align="center">'.$serverinfo['mutators'].'</th>
  </tr>
  <tr>
    <th align="center">Game Info</td>
    <td align="center">'.$serverinfo['gameinfo'].'</td>
  </tr>
</tbody></table>
<br>';

// Do graph stuff
$bgwhere = "serverip = ?";
$bgparams = [$serverip];
include("pages/graph_mbreakdown.php");

echo'<div class="pages">Page ['.$tfpage.'/'.$tlpage.'] Selection: '.$fpageurl.' / '.$ppageurl.' / '.$npageurl.' / '.$lpageurl.'</div>
<table class="zebra box" border="0" cellpadding="0" cellspacing="0">
  <tbody><tr>
    <th class="heading" colspan="4" align="center">Unreal Tournament Match List</th>
  </tr>
  <tr>
    <th class="smheading" align="center" width="220">Date/Time</th>
    <th class="smheading" align="center" width="140">Match Type</th>
    <th class="smheading" align="center">Map</td>
    <th class="smheading" align="center" width="40">Time</th>
  </tr>';

$sql_recent = "SELECT m.id, m.time, g.name AS gamename, m.mapfile, m.gametime FROM uts_match AS m, uts_games AS g  WHERE g.id = m.gid AND m.serverip = ? ORDER BY m.time DESC LIMIT ?,25";
foreach (db_all($sql_recent, [$serverip, (int) $qpage]) as $r_recent) {

    $r_time = mdate($r_recent['time']);
    $r_mapfile = un_ut($r_recent['mapfile']);
    $r_gametime = sec2min($r_recent['gametime']);
    $myurl = urlencode($r_mapfile);

    echo'
          <tr class="clickableRow" href="./?p=match&amp;mid='.$r_recent['id'].'">
                <td align="center"><a href="./?p=match&amp;mid='.$r_recent['id'].'">'.$r_time.'</a></td>
                <td align="center">'.$r_recent['gamename'].'</td>
                <td align="center"><a href="./?p=minfo&amp;map='.$myurl.'">'.$r_mapfile.'</a></td>
                <td align="center">'.$r_gametime.'</td>
          </tr>';
}

echo'
</tbody></table>
<div class="pages">Page ['.$tfpage.'/'.$tlpage.'] Selection: '.$fpageurl.' / '.$ppageurl.' / '.$npageurl.' / '.$lpageurl.'</div>';
