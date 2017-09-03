<?php
global $boardurl, $smcFunc;

try {
	$query = $smcFunc['db_query']('', "SELECT SUM(amount) AS total FROM {$GLOBALS['lm2_db_prefix']}money");
	$balance = ($row = $smcFunc['db_fetch_assoc']($query)) ? $row['total'] : 0;
	$smcFunc['db_free_result']($query);
} catch (Exception $e) {
	$balance = 0;
}

echo "<TD ALIGN='MIDDLE' VALIGN='MIDDLE' WIDTH='1'>";
echo "<A HREF='$boardurl/index.php?topic=754.0'><IMG SRC='/lm2/donations.php?balance=$balance' WIDTH='80' HEIGHT='43' BORDER='0' />
	<SMALL><BR/><NOBR>Quarterly&nbsp;cost: &#163;220</NOBR></SMALL></A>";
if ($balance > 0) {
	echo '
<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="hosted_button_id" value="F84XCYWWCG9R4">
<input type="image" src="https://www.paypalobjects.com/en_US/GB/i/btn/btn_buynowCC_LG.gif" border="0" name="submit" alt="PayPal – The safer, easier way to pay online.">
<img alt="" border="0" src="https://www.paypalobjects.com/en_GB/i/scr/pixel.gif" width="1" height="1">
<BR/>' . "<A HREF='$boardurl/index.php?topic=754.0'><B>Read this first!</B></A>&nbsp;&nbsp;&nbsp;&nbsp;</form>";
}
echo "</TD>";
?>
