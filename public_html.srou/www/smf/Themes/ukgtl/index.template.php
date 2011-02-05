<?php

function srou_template_init() {
	global $settings, $boardurl;

	$settings['srou_layout_header'] = array_merge($settings['srou_layout_header'], array(
		'imgW'=>'376',
		'imgH'=>'89',
		'imgUrl'=>'/images/ukgtl-shark.gif',
		'imgAlt'=>'UKGTL logo by Shark',
		'imgSub'=>'by SimRacing.org.uk',
		'homeUrl'=>"$boardurl/index.php?action=LM2R&group=13&theme=5", //FIXME: use lm2 link fn?
	));

	$settings['srou_downloads_topic'] = 3358;
}

include("$boarddir/Themes/srou/shared.template.php");

?>