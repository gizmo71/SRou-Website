<?php
function makeSrouLayoutHeader() {
	global $settings, $lm2_db_prefix, $boardurl;

	$theme = $settings['srou_layout_header'];

	echo "
<TABLE ALIGN='CENTER' CLASS='windowbg' WIDTH='100%' CELLSPACING='0' CELLPADDING='0'><TR>
	<TD VALIGN='BOTTOM' ALIGN='RIGHT'
	 onClick='top.location.href=&quot;{$theme['homeUrl']}&quot;'
	 STYLE='cursor: pointer; font-weight: bold; font-size: 10px; {$theme['imgSubStyle']}'
	 WIDTH='{$theme['imgW']}' HEIGHT='{$theme['imgH']}' BACKGROUND='{$theme['imgUrl']}'
	 TITLE='{$theme['imgAlt']}'
	/>
	{$theme['imgSub']}</TD><TD WIDTH='*'>{$theme['centreCell']}</TD>
	<TD WIDTH='1'>&nbsp;</TD>";
	if ($theme['ads'] === true) {
		try {
			$query = db_query("SELECT SUM(amount) AS total FROM {$lm2_db_prefix}money", __FILE__, __LINE__);
			$balance = ($row = mysql_fetch_assoc($query)) ? $row['total'] : 0;
			mysql_free_result($query);
		} catch (Exception $e) {
			$balance = 0;
		}
		echo ($balance < 0 ? "
	<TD ALIGN='RIGHT' VALIGN='MIDDLE'>" . '
<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="hosted_button_id" value="F84XCYWWCG9R4">
<input type="image" src="https://www.paypalobjects.com/en_US/GB/i/btn/btn_buynowCC_LG.gif" border="0" name="submit" alt="PayPal – The safer, easier way to pay online.">
<img alt="" border="0" src="https://www.paypalobjects.com/en_GB/i/scr/pixel.gif" width="1" height="1">
<BR/>' . "<A HREF='$boardurl/index.php?topic=754.0'><B>Read this first!</B></A>&nbsp;&nbsp;&nbsp;&nbsp;
</form>
		</TD>" : "") . "
	<TD ALIGN='RIGHT' VALIGN='MIDDLE' WIDTH='1'>
		<A HREF='$boardurl/index.php?topic=754.0'><IMG SRC='/lm2/donations.php?balance=$balance' WIDTH='80' HEIGHT='43' BORDER='0' />
		<SMALL><BR/><NOBR>Quarterly&nbsp;cost: &#163;220</NOBR></SMALL></A>
	</TD>
		";
	} else if ($theme['ads'] !== false) {
		include($theme['ads']);
	}
	echo "
	<TD ALIGN='RIGHT' VALIGN='MIDDLE' WIDTH='1'><NOBR>
		&nbsp;
	</NOBR></TD>
</TR></TABLE>
";

	// http://www.projecthoneypot.org/
	$honeypots = array(
		// '<a href="http://www.simracing.org.uk/frequent.php"><img src="nonchalant-unilinear.gif" height="1" width="1" border="0"></a>',
		'<a href="http://www.simracing.org.uk/frequent.php"><!-- nonchalant-unilinear --></a>',
		'<a href="http://www.simracing.org.uk/frequent.php" style="display: none;">nonchalant-unilinear</a>',
		'<div style="display: none;"><a href="http://www.simracing.org.uk/frequent.php">nonchalant-unilinear</a></div>',
		'<a href="http://www.simracing.org.uk/frequent.php"></a>',
		'<!-- <a href="http://www.simracing.org.uk/frequent.php">nonchalant-unilinear</a> -->',
		'<div style="position: absolute; top: -250px; left: -250px;"><a href="http://www.simracing.org.uk/frequent.php">nonchalant-unilinear</a></div>',
		'<a href="http://www.simracing.org.uk/frequent.php"><span style="display: none;">nonchalant-unilinear</span></a>',
		'<a href="http://www.simracing.org.uk/frequent.php"><div style="height: 0px; width: 0px;"></div></a>'
	);
	$index = array_rand($honeypots);
	echo "\n{$honeypots[$index]}\n";
}
?>
