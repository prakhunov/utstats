<?php

/*
 * Modified from UTStats (https://github.com/sn3p/utstats), Open Unreal Mod License.
 * Changes: ported to PHP 8.5 and PDO (bound SQL parameters), request input hardening,
 * formatting. Full per-change history: git log -- <this file>.
 */

require_once 'includes/renderer-general-import.php';

$safe_uid = $uid;

// Get relevant info
$playerinfo = getPlayerTeam($uid);
$playernames = $playerinfo[0];
$playerteams = $playerinfo[1];
$playernumberofteams = $playerinfo[2];

$time_gameinfo = getGameStartEndRatio($uid);
$time_gamestart = $time_gameinfo[0];
$time_gameend = $time_gameinfo[1];
$time_ratio_correction = $time_gameinfo[2];
