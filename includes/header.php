<?php

/*
 * Modified from UTStats (https://github.com/sn3p/utstats), Open Unreal Mod License.
 * Changes: ported to PHP 8.5 and PDO (bound SQL parameters), request input hardening,
 * formatting. Full per-change history: git log -- <this file>.
 */

$ogmeta_title = htmlspecialchars($_GET["title"] ?? '');
$ogmeta_desc = htmlspecialchars($_GET["desc"] ?? '');
$ogmeta_imageurl = htmlspecialchars($_GET["image"] ?? '');
// Start buffering
ob_start();

// Date in the past
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

// Always modified
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");

// HTTP/1.1
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Content-Type: text/html; charset=utf-8");

// HTTP/1.0
header("Pragma: no-cache");

if (isset($_COOKIE['uts_lastvisit'])) {
    if (isset($_COOKIE['utss_lastvisit'])) {
        $s_lastvisit = $_COOKIE['utss_lastvisit'];
    } else {
        setcookie('utss_lastvisit', $_COOKIE['uts_lastvisit'], 0);
        $s_lastvisit = $_COOKIE['uts_lastvisit'];
    }
} else {
    $s_lastvisit = time();
}
setcookie('uts_lastvisit', time(), time() + 60 * 60 * 24 * 30 * 365);

echo '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
  <meta property="og:title" content="'.$ogmeta_title.'" />
  <meta property="og:description" content="'.$ogmeta_desc.'" />
  <meta property="og:image" content="'.$ogmeta_imageurl.'">
  <title>Unreal Tournament Stats - Powered by UTStats</title>
  <meta http-equiv="Content-Type" content="text/html;charset=utf-8">
  <link rel="icon" href="assets/images/favicon.ico" type="image/ico">

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tooltipster@4.2.8/dist/css/tooltipster.bundle.min.css" integrity="sha384-ioTDa6my5Uvuqn2xw3AoESoCr9OV+ayVZRpEZxVFjQ6+ElYZgxy+hMb/G2TDrQsC" crossorigin="anonymous">
  <link rel="stylesheet" href="assets/style.css">

  <!-- Third-party libraries from CDNs, pinned with Subresource Integrity hashes -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha384-1H217gwSVyLSIfaLxHbE7dRb3v4mYCKbpQvzx0cegeju1MVsGrX5xXxAvs/HgeFs" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/tooltipster@4.2.8/dist/js/tooltipster.bundle.min.js" integrity="sha384-yTUGQ6VrDk5dutziag4wpU1WpSw9L5QgkEd1Zhz+NAFo0ozEIGBO65AOLXX94IX7" crossorigin="anonymous"></script>
  <!-- Highcharts: free for non-commercial use only, https://shop.highcharts.com/faq/non-commercial -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/highcharts/4.0.4/adapters/standalone-framework.js" integrity="sha384-zLvQXV/UtDqvFkCVU8duYKF2Ptwq7E6nbyqthQyToOSzPNDtngq3QGGs7wJsTRwH" crossorigin="anonymous"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/highcharts/4.0.4/highcharts.js" integrity="sha384-uOVHZzSUqQ7ZQEwDH4xevLqO9ZdMclNYG5x2Bdx1lVNcKI9PsV5ljxW27K7uLjGG" crossorigin="anonymous"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/highcharts/4.0.4/highcharts-more.js" integrity="sha384-E5jdzNRH3K9oGni9OoJHwn2DD7KWDrQBzJGZgGQLySFPCeAfYz9kMGMwKcdzKlEG" crossorigin="anonymous"></script>
  <script src="assets/js/highcharts-utstats.js"></script>
  <script src="assets/main.js"></script>
</head>
<body>';

include("includes/navbar.php");

echo '<center><br>';
