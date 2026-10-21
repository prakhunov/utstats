<?php

/*
Retrieve player names & player teams
Pretty much utstats code re-used
@return $playernames, $playerteams (arrays based on pid)
*/
function getPlayerTeam()
{
    global $matchid;

    $playernames = [];
    $playerteams = [];

    // Get List of Player IDs and Process What They Have Done
    $sql_player = "SELECT p.playerid,p.team,i.name FROM uts_player p JOIN uts_pinfo i ON p.pid = i.id WHERE p.matchid=?";
    $q_player = db_all($sql_player, [$matchid]);

    foreach ($q_player as $r_player) {
        $playerid = $r_player['playerid'];
        $playername = $r_player['name'];
        $playerteam = $r_player['team'];

        $playernames[$playerid] = $playername;
        $playerteams[$playerid] = $playerteam;
    }

    $r_team = db_row("SELECT COUNT(DISTINCT team) FROM uts_player WHERE matchid=?", [$matchid]);
    $teams = $r_team[0];

    return [$playernames,$playerteams,$teams];
}

/*
Get time game starts, game ends, and ratio compared to real time
@return $time_gamestart, $time_gameend, $time_ratio_correction (difference in ut time & real time, typically 110%)
*/
function getGameStartEndRatio($uid)
{
    // gather game start & end time
    $q_logdom = db_query("SELECT col0 FROM uts_temp_$uid WHERE col1='game_start' OR col1='game_end' ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);

    // A missing row used to give false
    $time_gamestart = $q_logdom[0] ?? false;
    $time_gameend = $q_logdom[1] ?? false;
    //$time_ratio_correction = ($time_gameend-$time_gamestart)/1200;
    $time_ratio_correction = TIMERATIO; // based on hardcore mode

    return [$time_gamestart, $time_gameend, $time_ratio_correction];
}

function generateTeamLabels()
{
    return ['Red Team', 'Blue Team', 'Green Team', 'Gold Team'];
}

/*
Generate the chart with pickups
*/
function renderDataPickups($uid, $team = true, $playerRedWins = true, $topFraggers = null)
{
    global $matchid;
    global $renderer_color;
    global $renderer_folder;
    global $renderer_width;
    global $renderer_heigth;

    $q_pickups = db_query("SELECT SUM(pu_belt), SUM(pu_keg), SUM(pu_pads), SUM(pu_armour), SUM(pu_amp) FROM uts_player as p WHERE matchid = ? GROUP BY team", [$matchid]);

    while ($r_pickups = $q_pickups->fetch(PDO::FETCH_NUM)) {
        $preData[] = $r_pickups;
    }

    $pickupitems = ['belt','keg','pads','armour','amp'];
    $itemsPickedUp = [];

    if ($team || $playerRedWins) {
        $teamOneId = 0;
        $teamTwoId = 1;
    } else {
        $teamOneId = 1;
        $teamTwoId = 0;
    }

    // Process data to convert these to percentages
    // Normal numbers don't plot nicely (fe. pads getting much higher pickups due to lower spawn time
    for ($i = 0;$i < count($pickupitems);$i++) {
        if ($preData[0][$i] > 0) {
            $percValue = round($preData[0][$i] / ($preData[0][$i] + $preData[1][$i]) * 100, 0);

            $data[$teamOneId][] = $percValue;
            $data[$teamTwoId][] = 100 - $percValue;
            $itemsPickedUp[] = $pickupitems[$i];

        } elseif ($preData[1][$i] > 0) {
            $data[$teamOneId][] = 0;
            $data[$teamTwoId][] = 100;
            $itemsPickedUp[] = $pickupitems[$i];
        }
    }

    if (count($itemsPickedUp) > 2) {
        if ($team) {
            $labels = generateTeamLabels();
        } else {
            $labels = generateLabelsFraggers($topFraggers);
        }

        $charttype = $team ? RENDERER_CHART_ITEMS_TEAMPICKUPS : RENDERER_CHART_ITEMS_PLAYERPICKUPS;

        // Save team score over team for teams
        db_exec(
            "INSERT INTO uts_chartdata (mid,chartid,data,labels,categories) VALUES (?, ?, ?, ?, ?)",
            [$matchid, $charttype, gzencode(serialize($data)), gzencode(serialize($labels)), gzencode(serialize($itemsPickedUp))]
        );
    }
}

/*
Generate labels for the fraggers
*/
function generateLabelsFraggers($topFraggers)
{
    global $playernames;

    $labels = [];

    foreach ($topFraggers as $fragger) {
        // PHP 5's substr() returned false for '' (unknown/merged player); keep that label value
        $name = $playernames[$fragger] ?? '';
        $labels[] = ($name === '') ? false : substr($name, 0, 18);
    }

    return $labels;
}

/*
Helper function to sort array on key, based on solution from the interwebs
*/
function array_sort($array, $on)
{
    $new_array = [];
    $sortable_array = [];

    if (count($array) > 0) {
        foreach ($array as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $k2 => $v2) {
                    if ($k2 == $on) {
                        $sortable_array[$k] = $v2;
                    }
                }
            } else {
                $sortable_array[$k] = $v;
            }
        }

        asort($sortable_array);

        foreach ($sortable_array as $k => $v) {
            $new_array[$k] = $array[$k];
        }
    }

    return $new_array;
}
