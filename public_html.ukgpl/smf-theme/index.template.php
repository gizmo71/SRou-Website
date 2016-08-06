<?php

function srou_template_init() {
	global $settings, $boarddir;

	$settings['srou_layout_header'] = array_merge($settings['srou_layout_header'], array(
		'imgW'=>'400',
		'imgH'=>'60',
		'imgUrl'=>'http://www.ukgpl.com/images/ukgpl.jpg',
		'imgAlt'=>'UKGPL logo',
		'imgSub'=>'', // Was "The UK Grand Prix Legends Championship"
		'imgSubStyle'=>'color: #ffffff',
		'homeUrl'=>'http://www.ukgpl.com/',
		'ads'=>"$boarddir/../../../public_html.ukgpl/header-ads.php",
	));
}

include("$boarddir/Themes/srou/shared.template.php");

?>