<?php
// iRacing importer

function showFileChoosers() {
	global $lm2_db_prefix;
?>
    <TR><TD COLSPAN="4"><I>Please see <A HREF="/smf/index.php?topic=7006">this topic</A> for details of how to get the Greasemonkey script required for the qualifying export.</I></TD></TR>
    <TR><TD><B>Race</B> CSV</TD><TD><INPUT size="120" name="rcsv" type="file" /></TD></TR>
    <TR><TD>Qualifying CSV</TD><TD><INPUT size="120" name="qcsv" type="file" /></TD></TR>
<?php
}

function doImport() {
	global $fatal_errors, $iracing_subsession;

	// Race results

	// Some fields need specific names for the lookup process.
	$fieldNameMap = array(
		"Driver" => "Driver",
		"Custid" => "Lobby Username",
		"Car #" => "#",
		"Car" => "Vehicle",
		"Car ID" => "VehicleFile",
		"Club ID" => "VehicleNumber",
		"Club" => "Team",
	);
	
	$file = $_FILES["rcsv"];

	if (preg_match('/\D(\d+)\.csv$/i', $file['name'], $matches)) {
		$iracing_subsession = $matches[1];
		echo "<P>Acquired subsession ID from filename: $iracing_subsession</P>\n";
	} else {
		echo '<P STYLE="color: red">Failed to acquired subsession ID from filename '.$file['name'].'; please enter it manually in the <A HREF="index.php?action=refdata&refData=evt&rdFilt=s9&sortOrder=-3">events list</A>.</P>\n';
	}

	($handle = fopen($file['tmp_name'], "r")) || die("can't open {$file['tmp_name']}");
	($index2Name = fgetcsv($handle)) || die("can't read header row");
	count($index2Name) || die("no fields!");
	count(array_diff(array_keys($fieldNameMap), $index2Name)) && die("some fields missing from " . print_r($fieldNameMap, true));
	//echo "<PRE>indices " . print_r($index2Name, true) . "</PRE>\n";
	$finPos = 0;
	$winnerLapsComp = null;
	$winnerRaceTime = null;
	while ($row = fgetcsv($handle)) {
		(count($row) == count($index2Name)) || die("bad row <PRE>" . print_r($row, row) . "</PRE>");
		$slot = array();
		foreach ($index2Name as $index=>$fieldName) {
			if (array_key_exists($fieldName, $fieldNameMap)) {
				$fieldName = $fieldNameMap[$fieldName];
			}
			$slot[$fieldName] = emptyToNull($row, $index);
		}
		$entry = &lookup_entry($slot, true, true);
		lookup_driver($entry, $entry['Driver'], $entry['LobbyName']);
		is_null($entry["RacePos"] = emptyToNull($slot, "Fin Pos")) && die("no RacePos");
		($entry["RacePos"] == ++$finPos) || die("RacePos out of sequence ; expected $finPos but got {$entry["RacePos"]}");
		is_null($entry["GridPos"] = emptyToNull($slot, "Start Pos")) && die("no GridPos");
		is_null($entry["IncidentPoints"] = emptyToNull($slot, "Inc")) && die("no IncidentPoints");
		is_null($entry["LapsLed"] = emptyToNull($slot, "Laps Led")) && die("no LapsLed");
		$entry["raceBestLapNo"] = emptyToNull($slot, "Fast Lap#");
		is_null($entry["raceLaps"] = emptyToNull($slot, "Laps Comp")) && die("no raceLaps");
		$entry["raceBestLapTime"] = parseTime(emptyToNull($slot, "Fastest Lap Time"), "Fastest Lap Time");
		$entry["reason"] = translateRetirementReason($slot["Out ID"], $slot["Out"]);
		if ($finPos == 1) {
			$winnerLapsComp = $entry["raceLaps"];
			$entry["raceTime"] = parseTime(emptyToNull($slot, "Average Lap Time"), "Average Lap Time") * $entry["raceLaps"];
			$referenceRaceTime = $entry["raceTime"] ? $entry["raceTime"] : null;
		} else if ($winnerLapsComp == $entry["raceLaps"]) {
			$interval = emptyToNull($slot, "Interval");
			$entry["raceTime"] = $referenceRaceTime - parseTime($interval, "Interval");
		}
//echo "<PRE>Slot: " . print_r($slot, true) . "Entry: " . print_r($entry, true) . "</PRE>\n";
	}
	fclose($handle);

	// Qualifying results

	$fieldNameMap = array(
		'displayName' => "Driver",
		"custid" => "Lobby Username",
		"carnum" => "#",
	);

	$file = $_FILES["qcsv"];

	($handle = fopen($file['tmp_name'], "r")) || die("can't open {$file['tmp_name']}");
	($index2Name = fgetcsv($handle)) || die("can't read qually header row");
	count($index2Name) || die("no qually fields!");
	count(array_diff(array_keys($fieldNameMap), $index2Name)) && die("some fields missing from " . print_r($fieldNameMap, true));

	while ($row = fgetcsv($handle)) {
		(count($row) == count($index2Name)) || die("bad row <PRE>" . print_r($row, row) . "</PRE>");
		$slot = array();
		foreach ($index2Name as $index=>$fieldName) {
			if (array_key_exists($fieldName, $fieldNameMap)) {
				$fieldName = $fieldNameMap[$fieldName];
			}
			$slot[$fieldName] = emptyToNull($row, $index);
		}
		$entry = &lookup_entry($slot, true, true);
		if (($entry["qualBestLapNo"] = emptyToNull($slot, "fastestlapnum")) === '-') {
			$entry["qualBestLapNo"] = null;
		}
		$entry["qualBestLapTime"] = parseTime(emptyToNull($slot, "fastestlaptime"), "fastestlaptime");
		is_null($entry["qualLaps"] = emptyToNull($slot, "lapscomplete")) && die("no lapscomplete");
//echo "<PRE>QSlot: " . print_r($slot, true) . "Entry: " . print_r($entry, true) . "</PRE>\n";
	}
	fclose($handle);

	//array_push($fatal_errors, "not written yet!");
}

function emptyToNull(&$slot, $fieldName) {
	array_key_exists($fieldName, $slot) || die("No $fieldName in " . print_r($slot, true));
	$value = $slot[$fieldName];
	return $value === "" ? null : $value;
}

function translateRetirementReason($id, $text) {
	global $lm2_db_prefix;

	(is_numeric($id) && $text) || die("bad reason: $id=$text");

	// Reasons which we want to map especially.
	$reasons = array(
		0=>null, // "Running"
		32=>-2, // "Disconnected"
	);

	if (array_key_exists($id, $reasons)) {
		return $reasons[$id];
	}

	// Otherwise, we are going to look through the existing ones for a match...

	$text = strtolower($text);

	$code = null;

	$query = db_query("
		SELECT retirement_reason
		FROM {$lm2_db_prefix}retirement_reasons
		WHERE LOWER(reason_desc) = " . sqlString($text) . "
		", __FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($query)) {
		is_null($code) || die("ambiguous retirement reason '$text'");
		$code = $row['retirement_reason'];
	}
	mysql_free_result($query);

	// ... and if we don't find one, add it.

	if (is_null($code)) {
		echo "<P><I>Adding unknown retirement reason '$text'</I></P>\n";

		$query = db_query("
			SELECT MAX(retirement_reason) AS max_reason
			FROM {$lm2_db_prefix}retirement_reasons
			", __FILE__, __LINE__);
		($row = mysql_fetch_assoc($query)) || die("wot, no reasons?");
		$code = $row['max_reason'] + 1;
		mysql_free_result($query);

		$query = db_query("
			INSERT INTO {$lm2_db_prefix}retirement_reasons
			(retirement_reason, reason_desc)
			VALUES ($code, " . sqlString($text) . ")
			", __FILE__, __LINE__);
	}

	return $code;
}

?>