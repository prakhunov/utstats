<?php /* Modified from UTStats (https://github.com/sn3p/utstats), Open Unreal Mod License. Changes: ported to PHP 8.5 and PDO (bound SQL parameters), request input hardening, formatting. Full per-change history: git log -- <this file>. */ ?>
<?php
include_once('includes/renderer-dm.php');


// Only run script if at least 5 minutes were played
if (($time_gameend - $time_gamestart) > 300 && count(array_unique($playernames)) >= 2) {

    try {
        $safe_uid = $uid;

        // Get relevant data
        list($datafrags, $derivfrags, $topFraggers, $counter, $datafragsteam, $derivfragsteam, $topTeams) = parseDMdata($safe_uid);

        if ($playernumberofteams > 1 && count(array_unique($playernames)) > 3 && ($gamename == "Tournament Team Game" || $gamename == "Tournament Team Game (insta)" || $gamename == "2v2v2v2 iTDM")) {
            renderFragBarsTeams($datafragsteam, $derivfragsteam, $topTeams, $counter);
        }

        renderFragBars($datafrags, $derivfrags, $topFraggers, $counter);

        if (count($playernames) == 2) {
            renderDataPickups($safe_uid, false, ($playerteams[$topFraggers[0]] == 0), $topFraggers);
        } elseif ($playernumberofteams == 2) {
            renderDataPickups($safe_uid);
        }

    } catch (Throwable $e) {

        // Do not block import (PHP 8 throws where PHP 5 only warned), but keep a trace
        error_log("UTStats: DM graphs for match $matchid failed: $e");

    }
}
?>