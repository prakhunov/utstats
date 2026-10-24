<?php

// Secrets come from the container environment (see .env.example); never hardcode them here.
function utstats_env(string $name, ?string $default = null): string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        if ($default !== null) {
            return $default;
        }
        throw new RuntimeException("Missing required environment variable $name");
    }
    return $value;
}

// Database connection details
$dbname = utstats_env('UTSTATS_DB_NAME');
$hostname = utstats_env('UTSTATS_DB_HOST', 'db');
$dbport = (int) utstats_env('UTSTATS_DB_PORT', '3306');
$uname = utstats_env('UTSTATS_DB_USER');
$upass = utstats_env('UTSTATS_DB_PASS');

// UT servers the importer pulls logs from, as a JSON array of
// {"host","port","user","password","dir"} objects (dir MUST NOT end with a /).
$ut_servers = json_decode(utstats_env('UTSTATS_FTP_SERVERS', '[]'), true, 4, JSON_THROW_ON_ERROR);

// The key needed to run the import script / admin pages
$import_adminkey = utstats_env('UTSTATS_ADMIN_KEY');

// When runnning from the command-line (cron jobs):
// The absolute path to UTStats's home directory.
// Only needed if you're starting the importer from another directory
// Leave emtpy if unsure
$import_homedir = '/var/www/html/';

// Use the MySQL temporary tables feature?
// Available since MySQL 3.23 - requires CREATE TEMPORARY TABLE privilege since 4.0.2
$import_use_temporary_tables = false;		// set to true if available

// Use temporary heap tables?
// This will (at any cost) keep the entire table in RAM and may speed and/or fuck things up
$import_use_heap_tables = false;				// DON'T USE IF YOU DON'T KNOW WHAT YOU'RE DOING!

// Log files start with...
$import_log_start = "Unreal.ngLog";

// Log files end with...
$import_log_extension = ".log";

// How to backup logfiles?
// Possible values: yes      - move logfiles to the backup directory
//                  no       - don't make a backup. The file will be lost after it was imported
//                  compress - will compress the logfile and move it to the backup directory
//                             It'll first try bzip2 compression, then gzip (your php must be
//                             compiled to support these)
//                             If both fail, it will backup the uncompressed log
//                  gzip     - same as compress but will only try to gzip the file
$import_log_backup = "compress";

// Purge old logs after x days. 0 to disable.
$import_log_backups_purge_after = 120;


// After how many seconds should we reload the import page?
// This is to prevent the 'maximum execution time exeeded' error. It will reload
// the page after the amount of seconds you specify in order to bypass php's time limit.
// Set to 0 to disable (f.e. if your php does not run in safe mode)
$import_reload_after = 22;

// Ignore bots and bot kills/deaths?
$import_ignore_bots = true;

// How to deal with banned players?
// 1 - (recommended) import the player and display him/her on matchpages (without values :D)
//     but don't include him/her in rankings and don't allow to show individual player stats
//     You may unban a player banned with this setting and all stuff will display again
// 2 - don't import at all
//     will lead to 'strange' results on matchpages because kills of and against this player
//     won't be shown; efficiency etc. will be calculated including these kills though.
$import_ban_type = 1;

// Try to import logs from previous versions of UTStats
// Set this to true and you'll probably some strange results - You've been warned ;)
$import_incompatible_logs = false;

// Don't import if the gametime was less than x minutes. Set to 0 to import all logs.
$import_ignore_if_gametime_less_than = 0;


// UTStats can download and manage your UTDC logs
// Enable downloading of UTDC logs?
$import_utdc_download_enable = false;

// Log files start with...
$import_utdc_log_start = "[UTDC]";

// Log files end with...
$import_utdc_log_extension = ".log";

// Screenshot files start with...
$import_utdc_screenshot_start = "[UTDC]";

// Screenshot files end with...
$import_utdc_screenshot_extension = ".enc";

// Compress UTDC logfiles after download? [compress/gzip/no]
// (see $import_log_backup for available options)
$import_utdc_log_compress = "compress";

// Purge old UTDC logs after x days. 0 to disable.
$import_utdc_log_purge_after = 0;

// UTStats can download and manage your AnthChecker logs
// Enable downloading of AC logs?
$import_ac_download_enable = true;

// Log files start with...
$import_ac_log_start = "[AC]";

// Log files end with...
$import_ac_log_extension = ".log";

// Compress AnthChecker logfiles after download? [compress/gzip/no]
// (see $import_log_backup for available options)
$import_ac_log_compress = "compress";

// Purge old AnthChecker logs after x days. 0 to disable.
$import_ac_log_purge_after = 120;

// UTStats can download and manage your ACE logs
// Enable downloading of ACE logs?
$import_ace_download_enable = true;

// Log files start with...
$import_ace_log_start = "[ACEPLAYERS]";

// Log files end with...
$import_ace_log_extension = ".log";

// Log files start with...
$import_ace_screenshot_start = "[ACEPLAYERS]";

// Log files end with...
$import_ace_screenshot_extension = ".jpg";

// Compress ACE logfiles after download? [compress/gzip/no]
// (see $import_log_backup for available options)
$import_ace_log_compress = "compress";

// Purge old ACE logs after x days. 0 to disable.
$import_ace_log_purge_after = 0;

// Enable the creation of pictures? (Signature pictures for users where they can see their current ranking and stuff)
// Requires GD- and FreeType support.
// see config_pic.php for picture configuration options
$pic_enable = true;



// FTP Connection Details
$ftp_use = true;			      	// Whether to auto get the log files
$ftp_interval = 1;			    	// How often in minutes to allow stats update

$ftp_debug = false;					// Debugging output that may help you to resolve ftp problems

$ftp_hostname = array_column($ut_servers, 'host');
$ftp_port = array_map('intval', array_column($ut_servers, 'port'));
$ftp_uname = array_column($ut_servers, 'user');
$ftp_upass = array_column($ut_servers, 'password');
$ftp_dir = array_map(fn ($server) => [$server['dir']], $ut_servers);
$ftp_passive = array_fill(0, count($ut_servers), true);
$ftp_delete = array_fill(0, count($ut_servers), true);

$renderer_folder = "renderings";
$renderer_width = 350;
$renderer_heigth = 250;
$renderer_color['team'][0][0] = '#ff3333';			// team red color 1
$renderer_color['team'][0][1] = '#cc0000';			// team red color 2
$renderer_color['team'][1][0] = '#0080ff';			// team blue color 1
$renderer_color['team'][1][1] = '#0000cc';			// team blue color 2
$renderer_color['team'][2][0] = '#88d8b0';			// team green color 1
$renderer_color['team'][2][1] = '#009f50';			// team green color 2
$renderer_color['team'][3][0] = '#ffeead';			// team gold color 1
$renderer_color['team'][3][1] = '#e5b021';			// team gold color 2
$renderer_color['player'][0][0] = '#a7d848';	// player 1 color 1
$renderer_color['player'][0][1] = '#98be44';	// player 1 color 2
$renderer_color['player'][1][0] = '#6ccdd0';	// player 2 color 1
$renderer_color['player'][1][1] = '#66af9f';	// player 2 color 2
$renderer_color['player'][2][0] = '#ffce59';	// player 3 color 1
$renderer_color['player'][2][1] = '#ffa65a';	// player 3 color 2
$renderer_color['player'][3][0] = '#fba263';	// player 4 color 1
$renderer_color['player'][3][1] = '#eb7254';	// player 4 color 2
$renderer_color['player'][4][0] = '#fb6370 ';	// player 5 color 1
$renderer_color['player'][4][1] = '#fb6363';	// player 5 color 2
$renderer_color['background'] = '#262626';// background color 1
$renderer_color['band'][0] = '#666666';// band color 1
$renderer_color['band'][1] = '#515151';// band color 2
$renderer_color['heading'] = '#bbdeff';// font heading
$renderer_color['font'] = '#999999';// font other
$renderer_color['amp'] = '#aa02db';// color amp
$renderer_color_transparancy = 0.3;// color amp

define("TIMERATIO", 1.09863333333333333333333333333333333333333333333333333333333);

define("RENDERER_CHARTTYPE_LINE", "line");
define("RENDERER_CHARTTYPE_COLUMN", "column");
define("RENDERER_CHARTTYPE_BAR", "bar");
define("RENDERER_CHARTTYPE_LINESTEP", "linestep");
define("RENDERER_CHARTTYPE_LINECOLUMN", "linecolumn");
define("RENDERER_CHARTTYPE_LINESTEPCOLUMN", "linestepcolumn");
define("RENDERER_CHARTTYPE_RADAR", "radar");

define("RENDERER_CHART_CTF_TEAMSCORE", 10);
define("RENDERER_CHART_CTF_GRABBREAKDOWN", 11);

define("RENDERER_CHART_DOM_SCOREDERIV", 21);

define("RENDERER_CHART_FRAGS_TEAMSCORE", 90);
define("RENDERER_CHART_FRAGS_TEAMDERIV", 91);
define("RENDERER_CHART_FRAGS_TEAMNORMAL", 92);
define("RENDERER_CHART_FRAGS_PLAYERSCORE", 93);
define("RENDERER_CHART_FRAGS_PLAYERDERIV", 94);
define("RENDERER_CHART_FRAGS_PLAYERNORMAL", 95);
define("RENDERER_CHART_FRAGS_PLAYERSCORE5", 96);
define("RENDERER_CHART_FRAGS_PLAYERNORMAL5", 97);

define("RENDERER_CHART_ITEMS_TEAMPICKUPS", 100);
define("RENDERER_CHART_ITEMS_PLAYERPICKUPS", 101);
define("RENDERER_CHART_ITEMS_AMPRUNS", 102);
