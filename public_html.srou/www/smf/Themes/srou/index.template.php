<?php
require_once("$boarddir/Themes/default/index.template.php");

global $settings;
$settings['srou_home'] = "https://{$_SERVER['SROU_HOST_WWW']}/";
$settings['srou_replay_url'] = "//{$_SERVER['SROU_HOST_REPLAY']}/";
$settings['srou_rules_topic'] = 3349;
$settings['srou_links_topic'] = 3354;

?>
