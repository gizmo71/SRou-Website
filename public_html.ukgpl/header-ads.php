<?php
global $boardurl;

$quarterlyCost = 30;
$balance = 0;
$topicId = 7341;
?>

<?php
if ($balance < 0) {
?>
	<TD ALIGN='RIGHT' VALIGN='MIDDLE'>
		Money money money
	</TD>
<?php
}
?>

	<TD ALIGN='RIGHT' VALIGN='MIDDLE' WIDTH='1'>
		<A HREF='<?php echo $boardurl; ?>/index.php?topic=<?php echo $topicId; ?>.0'><IMG
			SRC='<?php echo $boardurl; ?>/../lm2/donations.php?balance=<?php echo $balance; ?>' WIDTH='80' HEIGHT='43' BORDER='0' />
		<SMALL><BR/><NOBR>Quarterly&nbsp;cost: £<?php echo $quarterlyCost; ?></NOBR></SMALL></A>
	</TD>