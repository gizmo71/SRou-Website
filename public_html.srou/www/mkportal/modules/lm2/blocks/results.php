<? 
if (!defined("IN_MKP")) {
    die ("Sorry !! You cannot access this file directly.");
}

$content = "";
global $circuit_html_clause, $ID_MEMBER, $user_info, $ukgtrModsGroup, $lm2_db_prefix, $boardurl;

//FIXME: protect event from SQL injection from either event or group...
if ($event = $_REQUEST['event']) {
	$query = lm2_query("SELECT event_group FROM {$lm2_db_prefix}events WHERE id_event = $event", __FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($query)) {
		$group = $row['event_group'];
	}
	mysql_free_result($query);
} else {
	$group = $_REQUEST['group'];
}

ob_start();
lm2MakeSeriesTree($group);
$content .= ob_get_contents();
ob_end_clean();

$content .= " [ <A HREF='$boardurl/index.php?action=LM2R&team=*'>Teams</A>
| <A HREF='$boardurl/index.php?action=LM2R&circuit=*'>Circuits</A>
]";

$content .= '<BR/>&nbsp;&nbsp;<I>Recent</I>';
$events = lm2RecentUpcoming($event);
foreach ($events["recent"] as $row) {
	$content .= "<BR/>$row";
}
$content .= '<BR/>&nbsp;&nbsp<I><A HREF="/lm2/icalendar.php">Upcoming</A></I>';
foreach ($events["coming"] as $row) {
	$content .= "<BR/>$row";
}

unset($query);
unset($row);
unset($event);
unset($group);
unset($text);
unset($max_link_len);
unset($link);
unset($link_html);
?>