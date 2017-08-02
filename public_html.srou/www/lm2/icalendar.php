<?php require("../smf/SSI.php"); ?>
<?php
// LM2 iCalendar "feed"

//header('Content-Type: text/plain; charset=iso-8859-1');

require('include.php');
require('bennu/bennu.inc.php');

$tsfmt = 'Ymd\THis\Z';

//error_reporting(E_ALL);

$isGoogle = strstr($_SERVER['HTTP_USER_AGENT'], "Googlebot") !== false;

$method = $isGoogle ? null : "PUBLISH";

$a = new iCalendar;

add_prop($a, "prodid", "-//SimRacing.org.uk//LM2i//EN");
add_prop($a, "version", "2.0");
if ($method) {
	add_prop($a, "method", "$method");
}
add_prop($a, "X-WR-CALNAME", "SimRacing.org.uk");
add_prop($a, "X-WR-TIMEZONE", "Europe/London");

//TODO: add ability to restrict by event group hierarchy
$query = lm2_query("
	SELECT {$lm2_db_prefix}events.id_event
	, latitude_n, longitude_e
	, event_date AS event_start
	, IFNULL(event_date + INTERVAL event_seconds SECOND, event_date + INTERVAL 150 MINUTE) AS event_end
	, body
	, IFNULL({$db_prefix}calendar.title, CONCAT(short_desc, ' ', $circuit_html_clause)) AS summary
	, smf_topic
	, driver_name AS winner
	, CONCAT('UK', sim_name_short) AS league 
	FROM {$lm2_db_prefix}events
	JOIN {$lm2_db_prefix}event_groups ON id_event_group = event_group
	JOIN {$lm2_db_prefix}sim_circuits ON id_sim_circuit = sim_circuit
	JOIN {$lm2_db_prefix}circuits ON id_circuit = circuit
	JOIN {$lm2_db_prefix}circuit_locations ON id_circuit_location = circuit_location
	JOIN {$lm2_db_prefix}sims ON {$lm2_db_prefix}events.sim = id_sim
	LEFT JOIN {$lm2_db_prefix}event_entries ON id_event = event AND race_pos = 1
	LEFT JOIN {$lm2_db_prefix}drivers ON member = driver_member
	LEFT JOIN {$db_prefix}topics ON smf_topic = {$db_prefix}topics.id_topic
	LEFT JOIN {$db_prefix}messages ON id_first_msg = id_msg
	LEFT JOIN {$db_prefix}calendar ON smf_topic = {$db_prefix}calendar.id_topic
	WHERE event_date BETWEEN DATE_ADD(" . php2timestamp(time()) . ", INTERVAL -21 DAY) AND DATE_ADD(" . php2timestamp(time()) . ", INTERVAL 30 DAY)
	ORDER BY event_date DESC
	", __FILE__, __LINE__);
while ($row = mysql_fetch_assoc($query)) {
	$ev = new iCalendar_event;

	$url = $row['smf_topic'] ? "$boardurl/index.php?topic={$row['smf_topic']}" : null;

	add_prop($ev, "uid", "simracing.org.uk/event.{$row['id_event']}");
	if ($url) {
		add_prop($ev, "url", $url); // Google Calendar doesn't show it. :-( 
	}
	add_prop($ev, 'dtstamp', gmdate($tsfmt));
	add_prop($ev, "class", "PUBLIC");
	add_prop($ev, "categories", "SimRacing"); //FIXME: do we want to be able to pass these in?
	add_prop($ev, "summary", "{$row['league']} {$row['summary']}");

	add_prop($ev, "dtstart", gmdate($tsfmt, timestamp2php($row['event_start'])));
	add_prop($ev, "dtend", gmdate($tsfmt, timestamp2php($row['event_end'])));
//	add_prop($ev, "duration", "P1D"); // 1 day

	if ($row['latitude_n'] && $row['longitude_e']) {
		add_prop($ev, "geo", "{$row['latitude_n']};{$row['longitude_e']}");
		if ($isGoogle) {
			add_prop($ev, "location", "{$row['latitude_n']},{$row['longitude_e']}"); // Only format Google Calendar accepts. It also ignores the GEO tag. :-(
		}
	}

	if ($row['winner']) {
		add_prop($ev, "description", "Results: $url\nWinner: {$row['winner']}");
		//add_prop($ev, "status", "FINAL"); //TODO: make completed events a 'VJOURNAL' instead of a VEVENT?
	} else if ($url) {
		add_prop($ev, "description", "Announcement: $url");
	} else {
		add_prop($ev, "status", "TENTATIVE");
	}

	$a->add_component($ev);
}
mysql_free_result($query);

function add_prop(&$event, $name, $value, $params = null) {
	$event->add_property($name, $value, $params) || die("can't add $name=$value");
}


$ical = $a->serialize();
$ical === false && die("not valid");

if (!is_null($_REQUEST['plain'])) {
	header("Content-Type: text/plain; charset=iso-8859-1");
} else {
	header("Content-Type: text/calendar; charset=iso-8859-1" . ($isGoogle ? "" : "; method=$method"));
	header("Content-Disposition: inline; filename=SRou.ics");
}

echo $ical;
?>