<?php

require_once 'renderer-general-import.php';

/**
Render graph with scores & grabs
*/
function renderScoreGraph($uid)
{
    global $matchid;
    global $time_gamestart;
    global $time_gameend;
    global $time_ratio_correction;
    global $playernumberofteams;
    global $playerteams;

    // Get all frags related events + start & end
    $q_frags = db_query("SELECT * FROM `uts_temp_$uid` WHERE col0>=(? + 0) ORDER BY id ASC", [(string) $time_gamestart]);

    // init caps/grabs array
    $caps = [];
    $grabs = [];
    $lastCapTeam = [];
    $overTimeCap = false;

    $teamLabels = generateTeamLabels();
    $realTimeEnd = ($time_gameend - $time_gamestart) / $time_ratio_correction / 60;

    // init vars & ensure datapoint at origin
    for ($i = 0;$i < $playernumberofteams;$i++) {
        $caps[$i][0] = [0,0];
        $prevcaps[$i] = 0;
        $prevgrabs[$i] = 0;

        $flagmid[$i] = 0;
        $flagbase[$i] = 0;
        $flagclose[$i] = 0;
        $flagnmybase[$i] = 0;
        $flagcap[$i] = 0;
    }

    $counter = 1;
    while ($data = $q_frags->fetch()) {

        // Collect data from utstats own table
        $r_id = $data[0];
        $r_time = round(($data[1] - $time_gamestart) / $time_ratio_correction / 60, 2);	// Transform UT time to real time
        $r_type = $data[2];
        $r_capper = $data[3];
        $r_team = $playerteams[$r_capper] ?? null;

        // Try to get team directly from data if possible, in case not found in playerteam db
        // (float): PHP 5 compared non-numeric strings like '' as 0
        if (!($r_team >= 0 && strlen((string) $r_team) > 0)) {
            if ((float) $data[4] == 0 || (float) $data[4] == 20) {
                $r_team = 1;
            }
            if ((float) $data[4] == 1 || (float) $data[4] == 14) {
                $r_team = 0;
            }
        }

        // Only collect team stats if team was found - sometimes this is bugged
        if ($r_team >= 0 && strlen((string) $r_team) > 0) {

            // If flag is captured, save this datapoint
            if ($r_type == "flag_captured") {
                $prevcaps[$r_team]++;
                $flagcap[$r_team]++;
                $flagnmybase[$r_team]--;

                // Analyse cap for tooltip data
                $capdata = analyseCap($uid, $data[1], $r_capper, $r_team, $r_id);

                $caps[$r_team][] = ["x" => $r_time,"y" => $prevcaps[$r_team], "tooltipdata" => $capdata];
                $lastCapTeam = [$r_time,$r_team];

                // If grab is done, simply save this event
            } elseif ($r_type == "flag_taken") {
                $prevgrabs[$r_team]++;
                $flagnmybase[$r_team]++;
            } elseif ($r_type == "flag_return_mid") {
                $flagmid[$r_team]++;
                $flagnmybase[$r_team]--;
            } elseif ($r_type == "flag_return_enemybase") {
                $flagbase[$r_team]++;
                $flagnmybase[$r_team]--;
            } elseif ($r_type == "flag_return_closesave") {
                $flagclose[$r_team]++;
                $flagnmybase[$r_team]--;

            }

            // If game_end event & game ended cause timelimit reached, round the time down to prevent UT gay timing to screw the graph
        } if ($r_type == "game_end") {

            // If it's a regular timelimit, floor the time to get it nicely rounded (ref gay UT timing)\
            $overTimeCap = (($r_time - $lastCapTeam[0]) <= 0.01);

            if ($r_capper == "timelimit" && !$overTimeCap) {
                $r_time = round($r_time);
            }

            // Repeat final CTF score at the end
            for ($i = 0;$i < $playernumberofteams;$i++) {
                if (!$overTimeCap || $i != $lastCapTeam[1]) {
                    $caps[$i][] = [$r_time,$prevcaps[$i]];
                }
            }
        }

        // Each minute, save amount of grabs. Don't do it for last minute if this one was less than half a minute
        if (($r_time >= $counter && ($realTimeEnd - $r_time) > 0.49) || $r_type == "game_end") {

            // Save grabs last min and reset
            for ($i = 0;$i < $playernumberofteams;$i++) {
                $grabs[$i][] = $prevgrabs[$i];
                $prevgrabs[$i] = 0;
            }

            $counter++;
        }
    }

    // Ensure flagNmyBase is at least 0
    for ($i = 0;$i < $playernumberofteams;$i++) {
        if ($flagnmybase[$i] < 0) {
            $flagnmybase[$i] = 0;
        }
    }

    // Save team score over team for teams
    db_exec(
        "INSERT INTO uts_chartdata (mid,chartid,data,labels) VALUES (?, ?, ?, ?)",
        [$matchid, RENDERER_CHART_CTF_TEAMSCORE, gzencode(serialize([$grabs,$caps])), gzencode(serialize($teamLabels))]
    );

    $labelsBreakdown = ['caps','close','base','mid','enemy base'];

    // Save team score over team for teams
    db_exec(
        "INSERT INTO uts_chartdata (mid,chartid,data,labels) VALUES (?, ?, ?, ?)",
        [$matchid, RENDERER_CHART_CTF_GRABBREAKDOWN, gzencode(serialize([$flagcap,$flagclose,$flagbase,$flagmid,$flagnmybase])), gzencode(serialize($labelsBreakdown))]
    );

}

/**
Analyse cap to show in tooltip
*/
function analyseCap($uid, $capTime, $capPlayer, $capTeam, $capId)
{
    global $playernames;
    global $playerteams;
    global $time_ratio_correction;

    // Initialise variables
    $tooltip = '';
    $cherrypick = '';
    $solocap = '';
    $standoff = '';
    $hero = '';
    $event = [];
    $cherrypickDone = false;

    $event[0]['flag_taken'] = ['id' => 0, 'time' => 0, 'player' => ''];
    $event[0]['flag_returned'] = ['id' => 0, 'time' => 0, 'player' => ''];
    $event[0]['flag_captured'] = ['id' => 0, 'time' => 0, 'player' => ''];
    $event[1]['flag_taken'] = ['id' => 0, 'time' => 0, 'player' => ''];
    $event[1]['flag_returned'] = ['id' => 0, 'time' => 0, 'player' => ''];
    $event[1]['flag_captured'] = ['id' => 0, 'time' => 0, 'player' => ''];

    // Get grab time for both teams related to this cap. Query of the month award.
    $q_grabs = db_all("SELECT f.id AS id, f.col0 AS gtime, f.col1 AS event, f.col2 AS player, f.col3 AS team 
	FROM (	
		SELECT col3, col1, MAX( col0 ) AS xgtime FROM `uts_temp_$uid` WHERE (col1 = 'flag_taken' OR col1 = 'flag_returned' OR col1 = 'flag_captured') AND id<? GROUP BY col3,col1 ORDER BY col3 DESC	 
	) AS x INNER JOIN `uts_temp_$uid` AS f 
	on f.col3 = x.col3 AND f.col1 = x.col1 AND f.col0 = x.xgtime", [$capId]);

    // Only continue if you have at least one event (should be at least flag_taken for this cap)
    if (count($q_grabs) > 0) {

        foreach ($q_grabs as $r_grabs) {
            // Ugly code to fix bug where sometimes 20 or 14 is imported instead of 0 and 1 respectively
            $fixedteam = ($r_grabs['team'] == 20) ? 0 : $r_grabs['team'];
            $fixedteam = ($fixedteam == 14) ? 1 : $fixedteam;

            // Swap team around since ut logs which flag has been taken, thus opposite team actually took it
            // (int): PHP 5 treated a non-numeric team as 0
            $event[1 - (int)$fixedteam][$r_grabs['event']] = ['id' => $r_grabs['id'],'time' => $r_grabs['gtime'],'player' => $r_grabs['player']];
        }

        // Determine how long flags were holded
        $flagHolded[$capTeam] = $capTime - $event[$capTeam]['flag_taken']['time'];
        $flagHolded[1 - $capTeam] = $event[1 - $capTeam]['flag_returned']['time'] - $event[1 - $capTeam]['flag_taken']['time'];

        // Determine if this cap resulted from a cherrypick
        // For this the flag shoudl be taken within 4 seconds after return
        // Only register as cherry pick if previous run was at least 10 sec, otherwise it's not really a hcerry pick but just a subsequent grab
        if ($event[$capTeam]['flag_returned']['time'] > 0 && ($event[$capTeam]['flag_taken']['time'] - $event[$capTeam]['flag_returned']['time']) < 4) {

            $prevRunTime = db_row("SELECT MAX( col0 ) as gtime FROM `uts_temp_$uid` WHERE id<? AND col1='flag_taken' AND col3=?", [$event[$capTeam]['flag_returned']['id'], (string) (1 - $capTeam)]);

            if (($event[$capTeam]['flag_returned']['time'] - $prevRunTime[0]) > 10) {
                $cherrypickDone = true;
            }
        }

        if ($event[$capTeam]['flag_captured']['time'] > 0 && ($event[$capTeam]['flag_taken']['time'] - $event[$capTeam]['flag_captured']['time']) < 4) {
            $cherrypickDone = true;
        }

        if ($cherrypickDone) {
            $cherrypick = "<br><span style=\"fontSize: '7px';  font-style: italic;\">Cherrypick by ".renderPlayernameForTooltip($event[$capTeam]['flag_taken']['player'])."</span>";
        }

        // Determine if solo cap
        // First check if person who grabbed is same person as capper
        if ($event[$capTeam]['flag_taken']['player'] == $capPlayer) {

            // Second check is to see no pickups were done during this run
            if (db_count("SELECT * FROM `uts_temp_$uid` WHERE col1 = 'flag_pickedup' AND col3 = ? AND id<? AND id>?", [(string) (1 - $capTeam), $capId, $event[$capTeam]['flag_taken']['id']]) == 0) {

                $solocap = "<br>Solo cap";

            }

        }

        // If taken for more than 30 seconds for both teams, this is a long standoff
        // Add who did the return ((float): PHP 5 compared a missing player '' as 0)
        if ($flagHolded[$capTeam] > 30 && $flagHolded[1 - $capTeam] > 30 && (float) $event[1 - $capTeam]['flag_returned']['player'] >= 0) {
            $standoff = "<br>Standoff return by ".renderPlayernameForTooltip($event[1 - $capTeam]['flag_returned']['player']);
        }

        $tooltip = "Capped by : ".renderPlayernameForTooltip($capPlayer)." (".renderTimeForTooltip($flagHolded[$capTeam] / $time_ratio_correction).")";
        $tooltip .= $cherrypick;
        $tooltip .= $solocap;
        $tooltip .= $standoff;

    }

    return $tooltip;
}

/**
Prep temporary database for CTF parsing
*/
function prepCTFdata($safe_uid)
{

    // Replace time-out return by normal return
    db_exec("UPDATE `uts_temp_$safe_uid` SET col1='flag_returned', col2=(@temp:=col2),col2='-1',col3=@temp WHERE col1='flag_returned_timeout'");

}

function renderPlayernameForTooltip($pid)
{
    global $playernames;

    return substr($playernames[$pid] ?? '', 0, 25);
}

function renderTimeForTooltip($time)
{

    $strtime = '';
    $minutes = floor($time / 60);
    if ($minutes > 0) {
        $strtime = $minutes."min ";
        $time = $time - $minutes * 60;
    }

    $seconds = round($time, 0);
    if ($seconds >= 1) {
        $strtime .= $seconds."sec";
    }

    return $strtime;
}
