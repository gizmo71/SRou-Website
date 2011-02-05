<?php

function srou_template_init() {
	global $settings, $boardurl;

	$settings['srou_layout_header'] = array_merge($settings['srou_layout_header'], array(
		'imgW'=>'580',
		'imgH'=>'89',
		'imgUrl'=>'/images/ukgtr-gvw.jpg',
		'imgAlt'=>'UKGTR logo by Glen van Winkle',
		'imgSub'=>'',
		'homeUrl'=>"$boardurl/index.php?action=LM2R&group=7&theme=3", //FIXME: use lm2 link fn?
	));

	$settings['srou_downloads_topic'] = 3356;
}

include("$boarddir/Themes/srou/shared.template.php");

?>