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
	<TD ALIGN='RIGHT' VALIGN='MIDDLE'>
		<form action='https://www.paypal.com/cgi-bin/webscr' method='post'>
		<input type='hidden' name='cmd' value='_s-xclick'>
		<input type='image' src='https://www.paypal.com/en_US/i/btn/x-click-but21.gif' border='0' name='submit' alt='Make payments with PayPal - it&#39;s fast and secure!'>
		<input type='hidden' name='encrypted' value='-----BEGIN PKCS7-----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-----END PKCS7-----'>
		<BR/><A HREF='$boardurl/index.php?topic=754.0'><B>Read this first!</B></A>
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