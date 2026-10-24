<?php

/*
 * Modified from UTStats (https://github.com/sn3p/utstats), Open Unreal Mod License.
 * Changes: ported to PHP 8.5 and PDO (bound SQL parameters), request input hardening,
 * formatting. Full per-change history: git log -- <this file>.
 */

require_once("includes/geoip.php");

// Get the unique ID of this player.
// Create a new one if he has none yet.
$r_pid = db_row("SELECT id, country, banned FROM uts_pinfo WHERE name = ?", [$playername]);
if ($r_pid) {
    $pid = $r_pid['id'];
    $pid_country = $r_pid['country'];
    $playerbanned = ($r_pid['banned'] == 'Y') ? true : false;
} else {
    db_exec("INSERT INTO uts_pinfo SET name = ?", [$playername]);
    $pid = db_insert_id();
    $pid_country = false;
    $playerbanned = false;
}
$playerid2pid[$playerid] = $pid;

// Do we import banned players?
if ($playerbanned and $import_ban_type == 2) {
    return;
}


// Did the player do first blood?
if ($playerid == $firstblood) {
    $upd_firstblood = "UPDATE uts_match SET firstblood = ? WHERE id = ?";
    db_exec($upd_firstblood, [$pid, $matchid]);
}

// Get player's IP
$q_playerip = db_row("SELECT INET_ATON(col4) AS ip FROM uts_temp_$uid WHERE col1 = 'player' AND col2 = 'IP' and col3 = ? ORDER BY id ASC LIMIT 0,1", [$playerid]);
$playerip = ($q_playerip) ? $q_playerip['ip'] : 0;
if (empty($playerip)) {
    $playerip = 0;
}

// Map the IP to a country. A failed lookup (no GeoIP database, private/unknown IP)
// keeps the country we already know instead of wiping the player's flag.
$playercountry = geoip_country_code(long2ip((int) $playerip));
if ($playercountry === '' && $pid_country) {
    $playercountry = $pid_country;
}

if ($playercountry != $pid_country) {
    db_exec("UPDATE uts_pinfo SET country = ? WHERE id = ?", [$playercountry, $pid]);
}


// Get Sprees
$q_spree_dbl = db_count("SELECT id FROM uts_temp_$uid WHERE col1 = 'spree' AND col2 = 'spree_dbl' AND col3 = ?", [$playerid]);
$q_spree_mult = db_count("SELECT id FROM uts_temp_$uid WHERE col1 = 'spree' AND col2 = 'spree_mult' AND col3 = ?", [$playerid]);
$q_spree_ult = db_count("SELECT id FROM uts_temp_$uid WHERE col1 = 'spree' AND col2 = 'spree_ult' AND col3 = ?", [$playerid]);
$q_spree_mon = db_count("SELECT id FROM uts_temp_$uid WHERE col1 = 'spree' AND col2 = 'spree_mon' AND col3 = ?", [$playerid]);

$q_spree_kill = db_count("SELECT id FROM uts_temp_$uid WHERE col1 = 'spree' AND col2 = 'spree_kill' AND col3 = ?", [$playerid]);
$q_spree_rampage = db_count("SELECT id FROM uts_temp_$uid WHERE col1 = 'spree' AND col2 = 'spree_rampage' AND col3 = ?", [$playerid]);
$q_spree_dom = db_count("SELECT id FROM uts_temp_$uid WHERE col1 = 'spree' AND col2 = 'spree_dom' AND col3 = ?", [$playerid]);
$q_spree_uns = db_count("SELECT id FROM uts_temp_$uid WHERE col1 = 'spree' AND col2 = 'spree_uns' AND col3 = ?", [$playerid]);
$q_spree_god = db_count("SELECT id FROM uts_temp_$uid WHERE col1 = 'spree' AND col2 = 'spree_god' AND col3 = ?", [$playerid]);


// Get Count of Pickups
$sql_player7 = "SELECT col2, COUNT(col2) AS pu_count FROM uts_temp_$uid WHERE col1 = 'item_get' AND col3 = ? GROUP BY col2";
$q_player7 = db_all($sql_player7, [$playerid]);

$pu_pads = 0;
$pu_armour = 0;
$pu_keg = 0;
$pu_belt = 0;
$pu_amp = 0;
$pu_invis = 0;

foreach ($q_player7 as $r_player7) {
    // Cycle through pickups and see what the player got
    if ($r_player7['col2'] == "Thigh Pads") {
        $pu_pads = $r_player7['pu_count'];
    }
    if ($r_player7['col2'] == "Body Armor") {
        $pu_armour = $r_player7['pu_count'];
    }
    if ($r_player7['col2'] == "Super Health Pack") {
        $pu_keg = $r_player7['pu_count'];
    }
    if ($r_player7['col2'] == "ShieldBelt") {
        $pu_belt = $r_player7['pu_count'];
    }
    if ($r_player7['col2'] == "Damage Amplifier") {
        $pu_amp = $r_player7['pu_count'];
    }
    if ($r_player7['col2'] == "Invisibility") {
        $pu_invis = $r_player7['pu_count'];
    }
}

// Get ping information
$r_player9 = db_row("SELECT MIN(col4 * 1) AS lowping, MAX(col4 * 1) AS highping, AVG(col4 * 1) AS avgping FROM uts_temp_$uid WHERE col1 = 'Player' AND col2 = 'Ping' AND col3 = ? AND col4 > 0", [$playerid]);
$lowping = $r_player9['lowping'];
$highping = $r_player9['highping'];
$avgping = (int)$r_player9['avgping'];

// People who join at the end error the import, this stops it
if ($lowping == null) {
    $lowping = 0;
}
if ($highping == null) {
    $highping = 0;
}
if ($avgping == null) {
    $avgping = 0;
}

// Get accuracy, ttl etc
$r_acc = 0;
$r_deaths = 0;
$r_efficiency = 0;
$r_frags = 0;
$r_kills = 0;
$r_teamkills = 0;
$r_suicides = 0;
$r_tos = 0;
$r_ttl = 0;

$q_acc = db_row("SELECT col4 FROM uts_temp_$uid WHERE col1 = 'stat_player' AND col2 = 'accuracy' AND col3 = ?", [$playerid]);
$q_deaths = db_row("SELECT col4 FROM uts_temp_$uid WHERE col1 = 'stat_player' AND col2 = 'deaths' AND col3 = ?", [$playerid]);
$q_kills = db_row("SELECT col4 FROM uts_temp_$uid WHERE col1 = 'stat_player' AND col2 = 'kills' AND col3 = ?", [$playerid]);
$q_teamkills = db_row("SELECT col4 FROM uts_temp_$uid WHERE col1 = 'stat_player' AND col2 = 'teamkills' AND col3 = ?", [$playerid]);
$q_efficiency = db_row("SELECT col4 FROM uts_temp_$uid WHERE col1 = 'stat_player' AND col2 = 'efficiency' AND col3 = ?", [$playerid]);
$q_suicides = db_row("SELECT col4 FROM uts_temp_$uid WHERE col1 = 'stat_player' AND col2 = 'suicides' AND col3 = ?", [$playerid]);
$q_tos = db_row("SELECT col4 FROM uts_temp_$uid WHERE col1 = 'stat_player' AND col2 = 'time_on_server' AND col3 = ?", [$playerid]);
$q_ttl = db_row("SELECT col4 FROM uts_temp_$uid WHERE col1 = 'stat_player' AND col2 = 'ttl' AND col3 = ?", [$playerid]);
$q_score = db_row("SELECT col4 FROM uts_temp_$uid WHERE col1 = 'stat_player' AND col2 = 'score' AND col3 = ?", [$playerid]);

if ($teamgame == "True") {
    $r_kills = $q_kills['col4'] ?? null;
    $r_teamkills = $q_teamkills['col4'] ?? null;
}
if ($teamgame == "False") {
    $r_kills = ($q_kills['col4'] ?? null) + ($q_teamkills['col4'] ?? null);
    $r_teamkills = 0;
}

$r_acc = get_dp($q_acc['col4'] ?? 0);
$r_efficiency = get_dp($q_efficiency['col4'] ?? 0);
$r_deaths = $q_deaths['col4'] ?? null;
$r_suicides = $q_suicides['col4'] ?? null;
$r_frags = $r_kills - $r_suicides - $r_teamkills;

$r_tos = get_dp($q_tos['col4'] ?? 0);
// time on server can't be longer then the server game time!
if ($r_tos > $servergametime) {
    $r_tos = get_dp($servergametime);
}
$r_ttl = get_dp($q_ttl['col4'] ?? 0);
$r_score = $q_score['col4'] ?? null;

// Non-team matches (DM, 1v1, LMS) have no TeamChange event: store team 0 instead of
// relying on MySQL coercing '' into the int column (same fix as upstream sn3p 4090438).
if (!$playerteam) {
    $playerteam = 0;
}

// Generate player record
$sql_playerid = "	INSERT
							INTO		uts_player
							SET		matchid = :matchid,
										playerid = :playerid,
										pid = :pid,
										team = :playerteam,
										gid = :gid,
										insta = :gameinsta,
										country = :playercountry,
										ip = :playerip,


										spree_double = :q_spree_dbl,
										spree_multi = :q_spree_mult,
										spree_ultra = :q_spree_ult,
										spree_monster = :q_spree_mon,
										spree_kill = :q_spree_kill,
										spree_rampage = :q_spree_rampage,
										spree_dom = :q_spree_dom,
										spree_uns = :q_spree_uns,
										spree_god = :q_spree_god,

										pu_pads = :pu_pads,
										pu_armour = :pu_armour,
										pu_keg = :pu_keg,
										pu_belt = :pu_belt,
										pu_amp = :pu_amp,
										pu_invis = :pu_invis,

										lowping = :lowping,
										highping = :highping,
										avgping = :avgping,

										accuracy = :r_acc,
										frags = :r_frags,
										deaths = :r_deaths,
										kills = :r_kills,
										suicides = :r_suicides,
										teamkills = :r_teamkills,
										eff = :r_efficiency,
										gametime = :r_tos,
										ttl = :r_ttl,
										gamescore= :r_score";

// Values were quoted strings before (NULL became ''), so bind them as strings
db_exec($sql_playerid, array_map('strval', compact(
    'matchid',
    'playerid',
    'pid',
    'playerteam',
    'gid',
    'gameinsta',
    'playercountry',
    'playerip',
    'q_spree_dbl',
    'q_spree_mult',
    'q_spree_ult',
    'q_spree_mon',
    'q_spree_kill',
    'q_spree_rampage',
    'q_spree_dom',
    'q_spree_uns',
    'q_spree_god',
    'pu_pads',
    'pu_armour',
    'pu_keg',
    'pu_belt',
    'pu_amp',
    'pu_invis',
    'lowping',
    'highping',
    'avgping',
    'r_acc',
    'r_frags',
    'r_deaths',
    'r_kills',
    'r_suicides',
    'r_teamkills',
    'r_efficiency',
    'r_tos',
    'r_ttl',
    'r_score'
)));
$playerecordid = db_insert_id();
