<?php
global $boardurl;

$quarterlyCost = 0;
$balance = 0;
$topicId = 7555;
?>

<?php
if ($balance < 0) {
?>
	<TD ALIGN='RIGHT' VALIGN='MIDDLE'>
		<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
		<input type="hidden" name="cmd" value="_s-xclick">
		<input type="hidden" name="hosted_button_id" value="MQCV58CB2PGSW">
		<input type="image" src="https://www.paypal.com/en_GB/i/btn/btn_donate_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online.">
		<img alt="" border="0" src="https://www.paypal.com/en_GB/i/scr/pixel.gif" width="1" height="1">
</form>
	</TD>
<?php
}
?>

	<TD ALIGN='RIGHT' VALIGN='MIDDLE' WIDTH='1'>
		<A HREF='<?php echo $boardurl; ?>/index.php?topic=<?php echo $topicId; ?>.0'><IMG
			SRC='<?php echo $boardurl; ?>/../lm2/donations.php?balance=<?php echo $balance; ?>' WIDTH='80' HEIGHT='43' BORDER='0' />
		<SMALL><BR/><NOBR>Quarterly&nbsp;cost: £<?php echo $quarterlyCost; ?></NOBR></SMALL></A>
	</TD>