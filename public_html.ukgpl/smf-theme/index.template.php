<?php
require_once("$boarddir/Themes/default/index.template.php");

global $settings;
$settings['srou_home'] = "https://{$_SERVER['SROU_HOST_UKGPL']}/";
$settings['srou_replay_url'] = 'https://antipastiracing.org.uk/antipastiracing/UKGPL_Replays/index.php';
$settings['srou_downloads_topic'] = null;
$settings['srou_rules_topic'] = "https://{$_SERVER['SROU_HOST_UKGPL']}/index.php/rules/intro";
$settings['srou_links_topic'] = 'http://games.groups.yahoo.com/group/ukgpl/links/';
?>
