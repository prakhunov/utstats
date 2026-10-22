<?php /* Modified from UTStats (https://github.com/sn3p/utstats), Open Unreal Mod License. Changes: ported to PHP 8.5 and PDO (bound SQL parameters), request input hardening, formatting. Full per-change history: git log -- <this file>. */ ?>
<?php
include_once('includes/renderer-dm.php');
include_once('includes/renderer-ctf.php');

// Only run script if at least 5 minutes were played
if (($time_gameend - $time_gamestart) > 300 && count(array_unique($playernames)) >= 2) {

    try {
        $safe_uid = $uid;

        prepCTFdata($safe_uid);

        renderScoreGraph($safe_uid);

        // Get relevant data
        list($datafrags, $derivfrags, $topFraggers, $counter, $datafragsteam, $derivfragsteam, $topTeams) = parseDMdata($safe_uid);

        renderFragBarsTeams($datafragsteam, $derivfragsteam, $topTeams, $counter);

        if ($playernumberofteams == 2) {
            renderDataPickups($safe_uid);
        }

    } catch (Throwable $e) {

        // Do not block import (PHP 8 throws where PHP 5 only warned), but keep a trace
        error_log("UTStats: CTF graphs for match $matchid failed: $e");

    }
}
?>