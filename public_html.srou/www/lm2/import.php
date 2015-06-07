<P ALIGN="RIGHT"><B><A HREF="http://www.simracing.org.uk/smf/index.php?topic=362.0">Importer Instructions</A></B></P>
<?php
ini_get("precision") >= 12 || die("default precision is less than 12");
$sim = -1;
$id_event = $_REQUEST['id_event'];
if (!is_null($id_race1 = $_REQUEST['id_race1'])) {
	echo "<P>Using $id_race1 to set starting positions for $id_event (Race '07)</P>\n";
	db_query("
		UPDATE {$lm2_db_prefix}event_entries r1, {$lm2_db_prefix}event_entries r2
		SET r2.start_pos = IF(r1.race_pos < 9, 9 - r1.race_pos, r1.race_pos)
		WHERE r1.event = $id_race1 AND r2.event = $id_event AND r1.sim_driver = r2.sim_driver
		", __FILE__, __LINE__);
	db_query("
		UPDATE {$lm2_db_prefix}event_entries r1, {$lm2_db_prefix}event_entries r2
		SET r2.driver_type = r1.driver_type
		WHERE r1.event = $id_race1 AND r2.event = $id_event AND r1.sim_driver = r2.sim_driver
		", __FILE__, __LINE__);
} else if (is_null($id_event) || $id_event == "") {
	show_event_selector();
} else if (is_null($sim = $_REQUEST['id_sim'])) {
?>
<FORM enctype="multipart/form-data" method="POST">
<?php
	$query = db_query("SELECT sim FROM {$lm2_db_prefix}events WHERE id_event = $id_event", __FILE__, __LINE__);
	($row = mysql_fetch_assoc($query)) || die("can't find event for $id_event!");
	mysql_fetch_assoc($query) && die("ambiguous event $id_event!");
	mysql_free_result($query);
	$sim = $row['sim'];
    echo "<INPUT TYPE='HIDDEN' NAME='id_event' VALUE='$id_event' />\n";
    echo "<INPUT TYPE='HIDDEN' NAME='id_sim' VALUE='$sim' />\n";
?>
<TABLE>
    <!-- Name of input element determines name in $_FILES array -->
<?php
	include("importers/{$sim}.php");
	showFileChoosers();
?>
    <TR><TD COLSPAN="2"><HR></TD></TR>
    <TR><TD COLSPAN="2" ALIGN="RIGHT"><INPUT type="button" value="Send File" onClick="this.disabled=true;this.form.submit();" /></TD></TR>
    <TR><TD COLSPAN="2" ALIGN="RIGHT"><INPUT name="simulate" type="checkbox" value="1" /> Simulate import (don't actually import)</TD></TR>
</TABLE>
</form>
<?php
} else {
	rebuild_driver_cache();

	$iracing_subsession = null;
	$location = null;
	$track_length = null;
	$race_start_time = null;
	$entries = array();
	$fatal_errors = array();

	$query = db_query("
		SELECT id_sim_circuit
		, circuit
		, {$lm2_db_prefix}events.sim AS id_sim
		, {$lm2_db_prefix}sim_circuits.sim AS sim
		, (SELECT count(*) FROM {$lm2_db_prefix}event_entries WHERE event = $id_event) AS entries_c
		FROM {$lm2_db_prefix}events
		JOIN {$lm2_db_prefix}sim_circuits ON id_sim_circuit = sim_circuit
		WHERE id_event = $id_event
		", __FILE__, __LINE__);
	($current_circuit = mysql_fetch_assoc($query)) || die("can't find event or circuits for $id_event!");
	mysql_fetch_assoc($query) && die("ambiguous event $id_event!");
	mysql_free_result($query);
	($current_circuit['id_sim_circuit'] == -1) && die("cannot import for an event that has no location");
	($current_circuit['sim'] == -1) || die("cannot import for an event that is already locked to a physical circuit");
	($current_circuit['id_sim'] == $sim) || die("sims for event and import don't match"); // Should never get here!
	($current_circuit['entries_c'] == 0) || die("suspected double import");

	include("importers/{$sim}.php");
	doImport();

	// Fix up the cars.
	foreach ($entries AS $key=>&$entry) {
		$entry['simCarId'] = lookup_car($entry);
	}

	is_null($location) && die("no location");

	$query = db_query("
		SELECT id_sim_circuit, circuit, length_metres, sim_name
		FROM {$lm2_db_prefix}sim_circuits
		WHERE sim = $sim
		" . ($sim == 9 ? "" : "AND IFNULL(length_metres, -1) = IFNULL(" . nullIfNull($track_length) . ", -1)") . "
		AND sim_name = " . sqlString($location) . "
		", __FILE__, __LINE__);
	if ($row = mysql_fetch_assoc($query)) {
		mysql_fetch_assoc($query) && die("ambiguous circuit matches!");
		($row['circuit'] == $current_circuit['circuit']) || die("circuit mapping found but for wrong track");
		$current_circuit = $row;
	} else {
		if ($_REQUEST['simulate'] == '1') {
			echo "<P>Wants to make a new sim_circuit, location '$location', length '$track_length'
				from id_sim_circuit {$current_circuit['id_sim_circuit']}</P>\n";
		} else {
			db_query("
				INSERT INTO {$lm2_db_prefix}sim_circuits
				(circuit, sim, sim_name, length_metres)
				VALUES ({$current_circuit['circuit']}, $sim, " . sqlString($location) . "
				, " . nullIfNull($track_length) . ")
				", __FILE__, __LINE__);
			$current_circuit['id_sim_circuit'] = db_insert_id();
			echo "<P>Note: location '$location' added with length '$track_length' for sim $sim as {$current_circuit['id_sim_circuit']}</P>\n";
		}
	}
	mysql_free_result($query);

	//echo "<PRE>entries: " . print_r($entries, true) . "</PRE>\n";

	if (count($entries) == 0) {
		die("no entries - are you sure you did anything?");
	} else if (count($fatal_errors) > 0) {
		echo "<H2>" . count($fatal_errors) . " fatal errors; not writing event to database.<BR>Please fix the problems and try again.</H2>";
		foreach (array_unique($fatal_errors) AS $fatal_error) {
			echo "$fatal_error<BR/>\n";
		}
	} else if ($_REQUEST['simulate'] == '1') {
		echo "<P>Import simulated - not writing entries</P>\n";
//foreach ($entries AS $entry) {
//echo "<BR/>Would write " . print_r($entry, true) . "\n";
//}
	} else {
		write_entries($current_circuit['id_sim_circuit']);
		echo "<P>Don't forget to generate the standings!</P>\n";

		if ($sim == 7) {
			$query = db_query("
				SELECT r1.id_event
				FROM {$lm2_db_prefix}events r1
				JOIN {$lm2_db_prefix}events r2 USING (sim, sim_circuit)
				WHERE r2.id_event = $id_event
				AND r1.event_date < r2.event_date AND r1.event_date > r2.event_date - INTERVAL 2 HOUR
				", __FILE__, __LINE__);
			while ($row = mysql_fetch_assoc($query)) {
				echo "<P><A HREF='?action=import&id_event=$id_event&id_race1={$row['id_event']}'>Take starting positions from event {$row['id_event']}</A></P>\n";
			}
			echo "<P><A HREF='?action=refdata&refData=eve&rdFilt=e$id_event'>Check for AI drivers</A></P>\n";
			mysql_free_result($query);
		}
	}
}

// Used by some of the importers.
function show_mod_selector() {
	global $lm2_db_prefix, $sim;
?>
    <TR><TD>Mod/class</TD><TD><SELECT name="mod" onSelect="alert('foo\n' + form.submit_button);">
    	<OPTION VALUE="" SELECTED>Please select a mod...</OPTION>
<?php
	$query = db_query("
		SELECT type, mod_desc
		FROM {$lm2_db_prefix}sim_mods
		WHERE id_sim = $sim
		", __FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($query)) {
		print "<OPTION VALUE='${row['type']}'>${row['mod_desc']}</OPTION>\n";
	}
	mysql_free_result($query);
?>
    </SELECT> <SPAN STYLE="color: red">You <B>must</B> select a mod before proceeding!</SPAN></TD></TR>
<?php
}

function show_event_selector() {
	global $circuit_html_clause, $lm2_db_prefix;
?>
<form enctype="multipart/form-data" method="POST">
	<SELECT NAME="id_event">
      <OPTION VALUE="">Please select an event...</OPTION>
<?php
	$query = db_query("
		SELECT id_event
		, $circuit_html_clause AS circuit
		, full_desc AS event_group
		, event_date
		, {$lm2_db_prefix}sims.sim_name
		FROM {$lm2_db_prefix}events
		JOIN {$lm2_db_prefix}event_groups ON event_group = id_event_group
		JOIN {$lm2_db_prefix}sim_circuits ON id_sim_circuit = sim_circuit
		JOIN {$lm2_db_prefix}circuits ON id_circuit = {$lm2_db_prefix}sim_circuits.circuit
		JOIN {$lm2_db_prefix}sims ON {$lm2_db_prefix}events.sim = id_sim
		JOIN {$lm2_db_prefix}circuit_locations ON id_circuit_location = circuit_location
		WHERE id_event NOT IN (SELECT event FROM {$lm2_db_prefix}event_entries)
		AND event_date < NOW() + INTERVAL 1 DAY
		ORDER BY event_date IS NULL, event_date
		" , __FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($query)) {
	    echo "<OPTION VALUE=\"{$row['id_event']}\">"
	        . "{$row['event_date']} {$row['circuit']} &mdash; {$row['event_group']}"
	        . " #{$row['id_event']} &mdash; {$row['sim_name']}</OPTION>\n";
	}
	mysql_free_result($query);
?>
	</SELECT>
	<INPUT type="button" value="Next &gt;" onClick="this.disabled=true;this.form.submit();" />
</FORM>
<?php
}

function write_entries($id_sim_circuit) {
	global $id_event;
	global $entries;
	global $lm2_db_prefix;
	global $race_start_time, $iracing_subsession;

	db_query("
		UPDATE {$lm2_db_prefix}events SET sim_circuit = $id_sim_circuit
		" . (is_null($race_start_time) ? "" : ", event_date = " . php2timestamp($race_start_time)) . "
		" . (is_null($iracing_subsession) ? "" : ", iracing_subsession = " . sqlString($iracing_subsession)) . "
		WHERE id_event = $id_event
		", __FILE__, __LINE__); 

	$has_GridPos = false;

	foreach ($entries AS $entry) {
		$fields = "event";
		$values = "$id_event";

		$fields .= ", member";
		$values .= ", " . $entry['memberId'];

		$fields .= ", sim_driver";
		$values .= ", " . nullIfNull($entry['simDriver']);

		$fields .= ", sim_car";
		$values .= ", " . $entry['simCarId'];

		$fields .= ", qual_best_lap_time";
		$values .= ", " . nullIfNull($entry['qualBestLapTime']);

		$fields .= ", qual_best_lap_no";
		$values .= ", " . nullIfNull($entry['qualBestLapNo']);

		$fields .= ", qual_laps";
		$values .= ", " . nullIfNull($entry['qualLaps']);

		$fields .= ", retirement_reason";
		$values .= ", " . nullIfNull($entry['reason']);

		$fields .= ", race_time_actual";
		$values .= ", " . nullIfNull($entry['raceTime']);

		$fields .= ", race_laps";
		$values .= ", " . nullIfNull($entry['raceLaps']);

		$fields .= ", race_best_lap_time";
		$values .= ", " . nullIfNull($entry['raceBestLapTime']);

		$fields .= ", race_best_lap_no";
		$values .= ", " . nullIfNull($entry['raceBestLapNo']);

		$fields .= ", ballast_car";
		$values .= ", " . nullIfNull($entry['CarKG']);

		$fields .= ", ballast_driver";
		$values .= ", " . nullIfNull($entry['DriverKG']);

		$fields .= ", pitstops";
		$values .= ", " . nullIfNull($entry['Pitstops']);

		$fields .= ", laps_led";
		$values .= ", " . nullIfNull($entry['LapsLed']);

		$fields .= ", incident_points";
		$values .= ", " . nullIfNull($entry['IncidentPoints']);

		if ($entry['GridPos']) {
			$has_GridPos = true;
			$fields .= ", start_pos";
			$values .= ", " . nullIfNull($entry['GridPos']);
		}

		if ($entry['RacePos']) {
			$fields .= ", race_pos_sim";
			$values .= ", " . nullIfNull($entry['RacePos']);
		}

		$query = db_query("INSERT INTO {$lm2_db_prefix}event_entries ($fields) VALUES ($values)", __FILE__, __LINE__);
	}

	// Now do a bit of fixing up.
	db_query("SET @pos = 0", __FILE__, __LINE__);
	db_query(
		"UPDATE {$lm2_db_prefix}event_entries SET"
		. " qual_pos = IF(IFNULL(qual_best_lap_time, 0) = 0, NULL, (@pos := @pos + 1))"
		. ($has_GridPos ? "" : ", start_pos = qual_pos")
		. " WHERE event = $id_event"
		. " ORDER BY qual_best_lap_time ASC"
		, __FILE__, __LINE__); 

	lm2_query("SET @pos = 0", __FILE__, __LINE__);
	lm2_query("UPDATE {$lm2_db_prefix}event_entries"
		. " SET race_best_lap_pos = IF(IFNULL(race_best_lap_time, 0) = 0, NULL, (@pos := @pos + 1))"
		. " WHERE event = $id_event"
		. " ORDER BY event, race_best_lap_time ASC"
		, __FILE__, __LINE__); 

	reset_unadjusted_positions($id_event);

	lm2_query("UPDATE {$lm2_db_prefix}events, {$lm2_db_prefix}event_entries"
		. " SET event_seconds = race_time_actual"
		. " WHERE id_event = $id_event AND race_pos = 1 AND id_event = event"
		, __FILE__, __LINE__); 
}

function parse_time_string($time_string) {
	if (sscanf($time_string, "%d/%d/%d %d:%d:%d", $year, $month, $day, $hour, $minute, $second) != 6)
		return null;
	return mktime($hour, $minute, $second, $month, $day, $year);
}

function upgradeCode2Sql($code) {
	return is_null($code) ? "NULL" : "CAST(0x$code AS unsigned)";
}

function &lookup_entry(&$slot, $isRace, $isGPL = false) {
	global $entries;
	global $lm2_db_prefix;
	global $fatal_errors;
	global $id_event;

	$driver = $slot['Driver'];
	$lobby = $slot['Lobby Username'];
	$slotNum = $slot['#'];

	if (preg_match('/^(.*) [(]([+-].*)kg[)]$/i', $slot['Vehicle'], $matches)) {
		$slot['Vehicle'] = $matches[1];
		$slot['PenaltyCar'] = $matches[2];
	}

	if (preg_match('/^(.*) [(]([+-].*)kg[)]$/i', $slot['Team'], $matches)) {
		$slot['Team'] = $matches[1];
		$slot['Penalty'] = $matches[2];
	}

	if (preg_match('/^(.*); (Dunlop|Michelin|Yokohama|Pirelli)$/i', $slot['Team'], $matches)) {
		$slot['Team'] = $matches[1];
		$type = $matches[2];
	}

	$entry = array(
		Driver=>$driver,
		LobbyName=>$lobby,
		Car=>array(
			Vehicle=>$slot['Vehicle'],
			Team=>$slot['Team'],
			VehicleNumber=>$slot['VehicleNumber'],
			VehicleFile=>$slot['VehicleFile'],
			VehicleType=>$slot['VehicleType'],
			UpgradeCode=>$slot['UpgradeCode'],
		),
		// Stuff used internally:
		slot=>$slotNum,
		fromRace=>$isRace
	);

	foreach ($entries AS $key=>&$old_entry) {
		if ($isGPL) { // In GPL we do everything based on car number.
			if ($old_entry['slot'] == $entry['slot']) {
				check_and_copy($old_entry['Driver'], $entry['Driver'], 'Driver');
				check_and_copy($old_entry['LobbyName'], $entry['LobbyName'], 'LobbyName');
				check_and_copy($old_entry['Car']['Vehicle'], $entry['Car']['Vehicle'], 'Vehicle');
				check_and_copy($old_entry['Car']['VehicleFile'], $entry['Car']['VehicleFile'], 'VehicleFile');
				check_and_copy($old_entry['Car']['VehicleType'], $entry['Car']['VehicleType'], 'VehicleType');
				return $old_entry;
			}
		} else if ($old_entry['Driver'] == $driver && $old_entry['LobbyName'] == $lobby) {
			if ($isRace) {
				array_push($fatal_errors, "two race entries for $driver/$lobby");
			} else if ($old_entry['Car']['Vehicle'] == $slot['Vehicle'] && $old_entry['Car']['Team'] == $slot['Team']
				&& $old_entry['Car']['VehicleNumber'] == $slot['VehicleNumber'] && $old_entry['slot'] == $slotNum)
			{
				return $old_entry;
			} else if ($old_entry['fromRace']) {
				echo "<P>Warning: ignoring [Slot$slotNum] for $driver/$lobby; does not match race info</P>";
			} else {
//FIXME: if it's the same driver and car, try to pick the one with a bigger timestamp on the laps.
				array_push($fatal_errors, "ambiguous qualifying info for $driver/$lobby; driver not seen in race");
			}
			return null;
		}
	}

	if (!$isGPL) {
		lookup_driver($entry, $driver, $lobby);

		if (is_null($entry['DriverKG'])) {
			$query = db_query("
				SELECT eb_ballast FROM {$lm2_db_prefix}event_ballasts
				WHERE eb_name = " . sqlString($driver) . " COLLATE latin1_bin
				AND eb_event = $id_event
				", __FILE__, __LINE__);
			if ($row = mysql_fetch_assoc($query)) {
				$entry['DriverKG'] = $row['eb_ballast'];
			}
			mysql_fetch_assoc($query) && die("more than one matching ballasts for $driver");
			mysql_free_result($query);
		}
	}

	$entries[] =& $entry;
	
	return $entry;
}

function check_and_copy(&$old, &$new, $hint = 'no hint available') {
	global $fatal_errors;

	if (is_null($new) && !is_null($old)) {
		// Do nothing.
	} else if (!is_null($new) && is_null($old)
		|| is_numeric($new) && is_numeric($old) && abs($new - $old) < 0.01)
	{
		$old = $new;
	} else if ($new != $old) {
		array_push($fatal_errors, "conflicting values '$new' and '$old' ($hint)");
	}
}

function lookup_driver(&$entry, $driver, $lobby) {
	global $lm2_db_prefix;
	global $fatal_errors;
	global $lm2_guest_member_id;
	global $sim;

	$driver = sqlString($driver, true);
	$lobby = sqlString($lobby, true);

	$query = db_query("
		SELECT id_sim_drivers, member
		FROM {$lm2_db_prefix}sim_drivers
		WHERE driving_name = $driver AND lobby_name = $lobby AND sim = $sim
		", __FILE__, __LINE__);
	if (!($row = mysql_fetch_assoc($query))) {
		mysql_free_result($query);

		// Not seen this before, so add it.
		$query = db_query("
			INSERT INTO {$lm2_db_prefix}sim_drivers
			(member, driving_name, lobby_name, sim)
			VALUES (0, $driver, $lobby, $sim)
			", __FILE__, __LINE__);
		array_push($fatal_errors, "added unknown member $driver/$lobby, mapped to ID 0");
	} else {
	    if (($entry['memberId'] = $row['member']) == 0) {
			array_push($fatal_errors, "$driver/$lobby mapped to member ID 0");
	    } else if ($entry['memberId'] == $lm2_guest_member_id) {
			echo "<P><B>Warning</B>: $driver/$lobby is a guest driver</P>";
	    }
		$entry['simDriver'] = $row['id_sim_drivers'];
	    if (mysql_fetch_assoc($query)) {
	 		array_push($fatal_errors, "$driver/$lobby mapped to more than one member");
	    }
	    mysql_free_result($query);

		$use_driver_details = false;
		$query = db_query("SELECT 1 FROM {$lm2_db_prefix}sims WHERE id_sim = $sim AND use_driver_details = 'Y'", __FILE__, __LINE__);
		while ($row = mysql_fetch_assoc($query)) {
			$use_driver_details = true;
		}
		mysql_free_result($query);

		if ($entry['memberId'] != 0 && $entry['memberId'] != $lm2_guest_member_id && $use_driver_details && $driver) {
			db_query("
				INSERT INTO {$lm2_db_prefix}driver_details
				(driver, sim, driving_name)
				VALUES ({$entry['memberId']}, $sim, $driver)
				ON DUPLICATE KEY UPDATE driving_name = $driver
				", __FILE__, __LINE__);
		}
	}
}

function lookup_car(&$entry) {
	global $fatal_errors, $lm2_db_prefix, $sim;

	$simCarId = -1;

	$vehicle = $entry['Car']['Vehicle'];
	$team = $entry['Car']['Team'];
	$number = $entry['Car']['VehicleNumber'];
	$file = $entry['Car']['VehicleFile'];
	$type = $entry['Car']['VehicleType'];
	$upgradeCode = $entry['Car']['UpgradeCode'];

	$query = db_query(sprintf("
		SELECT car, id_sim_car
		FROM {$lm2_db_prefix}sim_cars
		WHERE IFNULL(vehicle, '-') = IFNULL(%s, '-')
		AND IFNULL(team, '-') = IFNULL(%s, '-')
		AND IFNULL(number, -2) = IFNULL(%s, -2)
		AND IFNULL(file, -2) = IFNULL(%s, -2)
		AND IFNULL(type, -2) = IFNULL(%s, -2)
		AND IFNULL(upgrade_code, -2) = IFNULL(%s, -2)
		AND sim = $sim
		",
		sqlString($vehicle),
		sqlString($team),
		nullIfNull($number),
		sqlString($file),
		sqlString($type),
		upgradeCode2Sql($upgradeCode))
		, __FILE__, __LINE__);
	if (!($row = mysql_fetch_assoc($query))) {
		$sql = sprintf("
			INSERT INTO {$lm2_db_prefix}sim_cars
			(car, vehicle, team, number, sim, file, type, upgrade_code)
			VALUES (-1, %s, %s, %s, $sim, %s, %s, %s)
			",
			sqlString($vehicle),
			sqlString($team),
			nullIfNull($number),
			sqlString($file),
			sqlString($type),
			upgradeCode2Sql($upgradeCode));
		db_query($sql, __FILE__, __LINE__);
		array_push($fatal_errors, "added unknown $vehicle/$team/$number/$file/$type/$upgradeCode, mapped to ID -1");
	} else {
	    if ($row['car'] == -1) {
			array_push($fatal_errors, "$vehicle/$team/$number/$file/$type/$upgradeCode is mapped to car ID -1");
	    }
	    $simCarId = $row['id_sim_car'];
		if (mysql_fetch_assoc($query)) {
			array_push($fatal_errors, "$vehicle/$team/$number/$file/$type/$upgradeCode is mapped to more than one car ID");
	    }
	}
	mysql_free_result($query);

	return $simCarId;
}

// Parse a lap/race time. Returns time in seconds, or NULL if a "no time" was recorded.
function parseTime($s, $where = "unknown location") {
	if (is_null($s) || $s === "" || $s === "-") {
		return null;
	}

	if (substr($s, -3) == '---' || $s === "DNF") {
		return null;
	}

	if ($negative = (substr($s, 0, 1) == '-')) {
		$s = substr($s, 1);
	}

	$time = null;
	$n = strtok($s, ':');
	while (!is_null($n) && $n <> '') {
		if (is_null($time)) {
			$time = 0.0;
		}
		$time = $time * 60.0 + $n;
		$n = strtok(':');
	}

	if (is_null($time)) {
		die("bad time ($where): $s");
	}

	return $negative ? -$time : $time;
}
?>
