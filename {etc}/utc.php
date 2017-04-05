<?php
// LM2 iCalendar "feed"

require('include.php');
require('bennu/bennu.inc.php');

header('Content-Type: text/plain; charset=iso-8859-1');

$tsfmt = 'Ymd\THis\Z';

//error_reporting(E_ALL);

$query = lm2_query("SELECT id_event, event_date"
	. " FROM {$lm2_db_prefix}events"
	. " WHERE id_event IN (1, 35, 47, 44, 55, 56, 102)"
	. " ORDER BY event_date"
	, __FILE__, __LINE__);
while ($row = mysql_fetch_assoc($query)) {
	$event_date = timestamp2php($row['event_date']);
	echo "PHP $event_date, SQL {$row['event_date']}, Local " . php2timestamp($event_date) . ", UTC " . php2timestamp($event_date, true). "\n";
}
mysql_free_result($query);
?>