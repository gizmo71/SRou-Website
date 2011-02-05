<?php

function srou_template_init() {
	global $settings, $boardurl;

	$settings['srou_layout_header'] = array_merge($settings['srou_layout_header'], array(
		'imgW'=>'274',
		'imgH'=>'80',
		'imgUrl'=>"$boardurl/Themes/ukir/ukir.jpg",
		'imgAlt'=>'UKiR logo by Lazlow',
		'imgSub'=>"by SimRacing.org.uk",
		'imgSubStyle'=>'color: #c0c0ff',
		'centreCell'=>"<IMG ALIGN=RIGHT SRC='$boardurl/Themes/ukir/iRacing_square.jpg' WIDTH=83 HEIGHT=80>",
		'homeUrl'=>"$boardurl/index.php?action=LM2R&group=229&theme=33", //FIXME: use lm2 link fn?
	));

	$settings['srou_footer_html'] = "<A HREF='http://www.iRacing.com/'><IMG SRC='$boardurl/Themes/ukir/unofficial_iracing.jpg' WIDTH='243' HEIGHT='63'></A>";
}

include("$boarddir/Themes/srou/shared.template.php");

?>