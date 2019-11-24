<?php
$ID_MEMBER = $user_info['id']; //FIXME: remove and use standard everywhere

$modSettings['disableQueryCheck'] = true; // We use UNION and friends...
//FIXME: Get rid of these eventually and use the new Settings.php ones.
$guest_member_id = $lm2_guest_member_id;
$ukgtrModsGroup = $lm2_mods_group;
$incidentReportForum = $lm2_incident_report_forum;

require_once("$sourcedir/Subs-LM2.php");

// Not in PHP 5.4 or better.
//set_magic_quotes_runtime(0);

//FIXME: remove and always call the LM2 ones.
function get_request_param($name) { return lm2GetRequestParam($name); }
function find_event_moderator($event) { return lm2FindEventModerator($event); }

function sqlString($s, $emptyIfNull = false) { return lm2SqlString($s, $emptyIfNull); }

function rebuild_driver_cache() {
	global $lm2_db_prefix, $db_prefix;

	lm2_query("
		INSERT INTO {$lm2_db_prefix}drivers
		(driver_member, driver_name)
		SELECT id_member, real_name AS realName
		FROM {$db_prefix}members
		WHERE id_member NOT IN (SELECT driver_member FROM {$lm2_db_prefix}drivers)
		", __FILE__, __LINE__);
	lm2_query("
		UPDATE {$lm2_db_prefix}drivers, {$db_prefix}members
		SET driver_name = real_name
		WHERE driver_member = id_member
		AND IFNULL(driver_name <> real_name, 1)
		", __FILE__, __LINE__);
}

//TODO: remove and use the SMF ones.
//function full_event_group_name($group) { return lm2FullEventGroupName($group); }
function make_event_link($group, $event) { return lm2MakeEventLink($event); }
function php2timestamp($php_time, $utc = false) { return lm2Php2timestamp($php_time, $utc); }
function timestamp2php($mysql_timestamp, $utc = false) { return lm2Timestamp2php($mysql_timestamp, $utc); }
$circuit_html_clause = $lm2_circuit_html_clause;
$circuit_link_clause = $lm2_circuit_link_clause;
$lap_record_types = $lm2_lap_record_types;
$lap_record_clause = $lm2_lap_record_clause;
$champ_types = $lm2_champ_types;
$penalty_points_clause = $lm2_penalty_points_clause;

function parse_incident_time($timeString) {
	$seconds = 0;
	$delims = ':hms';
	for ($n = strtok(trim($timeString, $delims), $delims); $n !== false; $n = strtok('$delims')) {
		$seconds *= 60;
		$seconds += $n;
	}
	return $seconds;
}

function microtime_float() {
   list($usec, $sec) = explode(" ", microtime());
   return ((float) $usec + (float) $sec);
}

//TODO: change everywhere to call smcFunc thingy
$inhibitTimings = false;
function lm2_query($sql, $file, $line) {
	global $inhibitTimings, $db_connection;

	$start = microtime_float();
	mysqli_query($db_connection, "SET @oldMode = @@SQL_MODE") || die(mysqli_error($db_connection));
	mysqli_query($db_connection, "SET sql_mode = 'MYSQL40'") || die(mysqli_error($db_connection));
	($result = mysqli_query($db_connection, $sql)) !== FALSE || die("<PRE>$sql\n" . mysqli_error($db_connection) . "\n$file $line\n</PRE>");
	if ($inhibitTimings !== true) {
		$end = microtime_float();
		if (($ms = ($end - $start) * 1000.0) >= (is_numeric($inhibitTimings) ? $inhibitTimings : 500)) {
			echo sprintf("<!-- %s %dms %s %s -->\n", $sql, $ms, $file, $line);
		}
	}
	mysqli_query($db_connection, "SET sql_mode = @oldMode") || die(mysqli_error($db_connection));
	return $result;
}
//function mysql_free_result($q) { global $smcFunc; return $smcFunc['db_free_result']($q); }
//function mysql_fetch_assoc($q) { global $smcFunc; return $smcFunc['db_fetch_assoc']($q); }

//FIXME: remove and use LM2 version everywhere.
function format_timestamp($time, $date_only) { return lm2FormatTimestamp($time, $date_only); }

function reset_unadjusted_positions($id_event) {
	global $lm2_db_prefix;

	// Update the entry count cache.
	lm2_query("
		UPDATE {$lm2_db_prefix}events
		JOIN {$lm2_db_prefix}event_groups ON id_event_group = event_group
		SET entries_c = (SELECT COUNT(*) FROM {$lm2_db_prefix}event_entries WHERE id_event = event)
		WHERE " . (is_null($id_event) ? "NOT is_protected" : "id_event = $id_event"), __FILE__, __LINE__);

	// Set adjusted time based actual time plus any applied penalties.
	lm2_query("
		UPDATE {$lm2_db_prefix}event_entries
		JOIN {$lm2_db_prefix}events ON id_event = event
		SET race_time_adjusted = race_time_actual + IFNULL((
			SELECT SUM(seconds_added)
			FROM {$lm2_db_prefix}penalties
			WHERE id_event_entry = event_entry
			AND seconds_added
			AND IFNULL(victim_report, 'Y') = 'Y'
		), 0)
		WHERE " . (is_null($id_event) ? "NOT is_protected_c AND event_status NOT IN ('H', 'U')" : "id_event = $id_event") . "
		", __FILE__, __LINE__);

	// Finally, reorder the race results.
	$temp_db_prefix = "{$lm2_db_prefix}TEMP_";
	lm2_query("SET @pos = -1", __FILE__, __LINE__);
	lm2_query("SET @race = -1", __FILE__, __LINE__);
	$live = "(NOT is_protected_c AND event NOT IN (SELECT id_event FROM {$lm2_db_prefix}events WHERE event_status = 'H'))";
	lm2_query("CREATE TEMPORARY TABLE {$temp_db_prefix}p2 (INDEX (race2)) AS
		SELECT event AS race2, race_pos_sim AS sim_pos2
		FROM {$lm2_db_prefix}event_entries WHERE race_pos_sim IS NOT NULL AND $live
		", __FILE__, __LINE__);
	//TODO: consider if it should be 'race_laps IS NULL' for standing starts, and how to do '50% of winners distance' type things.
	$unclassified = "(excluded_c = 'Y' OR (race_time_adjusted IS NULL AND (IFNULL(race_laps, 0) = 0)) AND race_pos_sim IS NULL)";
	lm2_query("
		UPDATE {$lm2_db_prefix}event_entries
		SET race_pos = IF($unclassified
			, NULL
			, (@pos := IF(
				race_pos_sim IS NOT NULL
				, race_pos_sim
				, (
					SELECT MIN(poss_pos)
					FROM {$lm2_db_prefix}possible_positions
					WHERE poss_pos > IF(@race = event, @pos, (@race := event) * 0)
					AND IFNULL(poss_pos NOT IN (SELECT sim_pos2 FROM {$temp_db_prefix}p2 WHERE race2 = event), TRUE)
				)
			))
		)
		WHERE " . (is_null($id_event) ? $live : "event = $id_event") . "
		ORDER BY $unclassified
		, event
		, IFNULL(race_pos_sim, 999)
		, race_laps DESC
		, IFNULL(race_time_adjusted, 999999) ASC
		, qual_best_lap_time ASC
		" , __FILE__, __LINE__);
	lm2_query("DROP TEMPORARY TABLE {$temp_db_prefix}p2", __FILE__, __LINE__);
}

$penalty_types = $lm2_penalty_types; //XXX: replace with SMF one everywhere...

// Very simple wrappers to save us a load of typing!

function nullIfNull($value) {
	return is_null($value) ? "NULL" : $value;
}

function contentize($root_contents) {
	return generate_toc($root_contents, 'root')
		. "<HR />\n"
		. generate_body($root_contents, 2);
}

function generate_toc($contents, $id) {
	$content = '';
	if (is_array($contents)) {
		$content .= "<UL" . (is_null($id) ? '' : " ID=\"$id\"") . ">\n";
		foreach ($contents AS $id=>$block) {
			$content .= "<LI><A HREF=\"#$id\">{$block['title']}</A></LI>\n";
			$content .= generate_toc($block['contents'], null);
		}
		$content .= "</UL>\n";
	}
	return $content;
}

function generate_body($contents, $level) {
	$content = '';
	if (is_null($contents)) {
		;
	} else if (!is_array($contents)) {
		$content .= "<UL>\n$contents\n</UL>\n<P ALIGN=RIGHT><SMALL><A HREF=\"#root\"> Back to top</A></SMALL></P>\n";
	} else if (!is_null($contents)) {
		foreach ($contents AS $id=>$block) {
			$content .= "<H$level ID=$id>{$block['title']}</H$level>\n";
			if ($items = $block['items']) {
				$content .= "<UL>\n";
				foreach ($items AS $item) {
					$content .= "<LI>$item</LI>\n";
				}
				$content .= "</UL>\n<P ALIGN=RIGHT><SMALL><A HREF=\"#root\"> Back to top</A></SMALL></P>\n";
			}
			$content .= generate_body($block['contents'], $level + 1);
		}
	}
	return $content;
}

// XML utilities.

//FIXME: remove and use the SMF ones instead.
function get_single_element_text($parent, $tagname, $default = 'MAGIC-NO-DEFAULT') { return lm2GetSingleElementText($parent, $tagname, $default); }
function get_single_element($parent, $tagname, $null_if_missing = false) { return lm2GetSingleElement($parent, $tagname, $null_if_missing); }
function get_element_text($node) { return lm2GetElementText($node); }

function make_text_tag($doc, $name, $text, $parent) {
	$tag = $doc->createElement($name);
	$tag->appendChild($doc->createTextNode($text));
	$parent->appendChild($tag);
}

function make_cdata_tag($doc, $name, $text, $parent) {
	$tag = $doc->createElement($name);
	$tag->appendChild($doc->createCDATASection($text));
	$parent->appendChild($tag);
}
?>
