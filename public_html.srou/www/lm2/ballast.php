<?php
require_once("../smf/SSI.php");
require_once("include.php");
$inhibitTimings = true;

is_numeric($event = get_request_param('event')) || die("no/bad event supplied");
//is_numeric($group = get_request_param('group')) || die("no/bad event group supplied");

// We only do the generation once; after that the results are taken from the table without any updates.

$query = db_query("
	SELECT sim
	, (SELECT COUNT(*) FROM {$lm2_db_prefix}event_ballasts WHERE eb_event = $event) AS ballasts
	FROM {$lm2_db_prefix}events
	WHERE id_event = $event
	", __FILE__, __LINE__);
($row = $smcFunc['db_fetch_assoc']($query)) || die("expected to get a row from the count!");
$sim = $row['sim'];
$smcFunc['db_fetch_assoc']($query) && die("didn't expect to get two rows from the count!");
$smcFunc['db_free_result']($query);

$temp_db_prefix = "{$lm2_db_prefix}TEMP_"; //TODO: put this into Settings.php?

if ($row['ballasts'] == 0) {
	db_query("
		INSERT INTO {$lm2_db_prefix}event_ballasts
		(eb_driver, eb_event, eb_name, eb_ballast)
		SELECT driver, $event, driving_name, handicap_ballast
		FROM {$lm2_db_prefix}driver_details
		WHERE sim = $sim
		AND handicap_ballast IS NOT NULL
		", __FILE__, __LINE__);
// Pre-voluntary stuff:
//	db_query("
//		CREATE TEMPORARY TABLE {$temp_db_prefix}ballasts
//		(UNIQUE (member, event_date))
//		AS SELECT driver_member AS member
//		, IFNULL(driving_name, 'NoNameSupplied') AS driving_name
//		, event_date
//		, class_min_ballast
//		, class_max_ballast
//		, MAX(ballast_delta) AS ballast_delta
//		, 9999.9 AS ballast
//		FROM {$lm2_db_prefix}event_entries
//		JOIN {$lm2_db_prefix}events e ON event = id_event AND event_type <> 'F'
//		JOIN {$lm2_db_prefix}drivers ON driver_member = member
//		JOIN {$lm2_db_prefix}sim_cars ON id_sim_car = sim_car
//		JOIN {$lm2_db_prefix}cars ON id_car = car
//		JOIN {$lm2_db_prefix}classes ON id_class = class
//		JOIN {$lm2_db_prefix}ballast_schemes ON class_ballast_scheme = ballast_scheme AND (ballast_position = race_pos_class OR ballast_position = 999)
//		LEFT JOIN {$lm2_db_prefix}driver_details dd ON driver_member = dd.driver AND dd.sim = e.sim
//		WHERE event_group = $group
//		AND driver_member <> $lm2_guest_member_id
//		GROUP BY driver_member, event_date
//		" , __FILE__, __LINE__);
//	db_query("SET @member = 0", __FILE__, __LINE__);
//	db_query("SET @ballast = 0000.0", __FILE__, __LINE__);
//	db_query("UPDATE {$temp_db_prefix}ballasts
//		SET ballast = (@ballast := IF(@member = member, @ballast, (@member := member) * 0000.0) + ballast_delta)
//		, ballast = (@ballast := IF(@ballast > class_max_ballast, class_max_ballast, @ballast))
//		, ballast = (@ballast := IF(@ballast < class_min_ballast, class_min_ballast, @ballast))
//		ORDER BY member, event_date
//		", __FILE__, __LINE__);
//	db_query("
//		INSERT INTO {$lm2_db_prefix}event_ballasts
//		(eb_driver, eb_event, eb_name, eb_ballast)
//		SELECT member, $event, driving_name, ballast
//		FROM {$temp_db_prefix}ballasts
//		ORDER BY event_date
//		ON DUPLICATE KEY UPDATE eb_ballast = ballast
//		", __FILE__, __LINE__);
//	db_query("DROP TABLE {$temp_db_prefix}ballasts", __FILE__, __LINE__);
//	db_query("
//		INSERT INTO {$lm2_db_prefix}event_ballasts
//		(eb_driver, eb_event, eb_name, eb_ballast)
//		VALUES ($lm2_guest_member_id, $event, '{unknown}', 0)
//		", __FILE__, __LINE__);
}

// Okay, we should now have the appropriate set of ballasts in the table, so generate the file.

$content = '';
$count = 0;

$query = db_query("
	SELECT eb_name AS driving_name, eb_ballast AS ballast
	FROM {$lm2_db_prefix}event_ballasts
	WHERE eb_event = $event
	ORDER BY driving_name
	", __FILE__, __LINE__);
while ($row = $smcFunc['db_fetch_assoc']($query)) {
	++$count;
	($row['ballast'] >= 0 && $row['ballast'] <= 120) || die("ballast {$row['ballast']} outside allowable range");
	$content .= sprintf("%s\r\n%d\r\n", $row['driving_name'], $row['ballast']);
}
$smcFunc['db_free_result']($query);

header('Content-Type: text/plain');
if (true) {
	header('Content-Disposition: attachment; filename="Drivers.txt"');
}
echo "$count\r\n$content";

//if ($event = 405) db_query("DELETE FROM {$lm2_db_prefix}event_ballasts WHERE eb_event = $event", __FILE__, __LINE__); //XXX: remove!
?>
