<?php

function srou_template_init() {
	global $settings, $boardurl;

	$settings['srou_layout_header'] = array_merge($settings['srou_layout_header'], array(
		'imgSub'=>'GTR2 travels backwards in time',
		'homeUrl'=>"$boardurl/index.php?action=LM2R&group=213&theme=34", //FIXME: use lm2 link fn?
	));

	$settings['srou_downloads_topic'] = 3356;
}

include("$boarddir/Themes/srou/shared.template.php");

?>