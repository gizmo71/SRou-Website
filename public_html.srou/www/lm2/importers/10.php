<?php
// Assetto Corsa Competitzione importer

function showFileChoosers() {
?>
    <TR><TH>Qualifying JSON (optional)</TH><TD><INPUT size="120" name="accQualJson" type="file" /></TD></TR>
    <TR><TH>Race JSON</TH><TD><INPUT size="120" name="accRaceJson" type="file" /></TD></TR>
<?php
}

function doImport() {
	$q = $_FILES["accQualJson"]['size'] ? $_FILES["accQualJson"]['tmp_name'] : null;
	$r = $_FILES["accRaceJson"]['size'] ? $_FILES["accRaceJson"]['tmp_name'] : null;
	doImportJson($q, $r);
}

function doImportJson($qFilename, $rFilename) {
	global $fatal_errors;

	$cars = array();

	processJson($rFilename, true, $cars);
	if ($qFilename) processJson($qFilename, false, $cars);

	// ACC inexplicably has no location information in the export!
	global $current_circuit, $lm2_db_prefix, $location;
	$query = db_query("
		SELECT CONCAT((SELECT brief_name FROM {$lm2_db_prefix}circuit_locations WHERE id_circuit_location = circuit_location)
		              , CONCAT(' (', layout_name, ')')) AS location
		FROM {$lm2_db_prefix}circuits
		WHERE id_circuit = (SELECT circuit FROM {$lm2_db_prefix}sim_circuits WHERE id_sim_circuit = {$current_circuit['id_sim_circuit']})
		", __FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($query)) {
		is_null($location) || die("multiple matches for sim circuit {$current_circuit['id_sim_circuit']}");
		$location = $row['location'];
	}
	mysql_free_result($query);
}

function processJson($filename, $isRace, &$cars) {
	global $entries, $modReport;

	$contents = file_get_contents($filename);
	$contents = mb_convert_encoding($contents, "UTF-8", "UTF-16LE"); //UCS-2LE
	($json = json_decode($contents, true)) || die("Couldn't convert to JSON because " . json_last_error() . ": $contents");

	($json['type'] == ($isRace ? 1 : 0)) || die("Type '{$json['type']}' doesn't match isRace $isRace");

	$simPos = 0;
	foreach ($json['leaderBoardLines'] as $leaderBoardLine) {
		$slot = array(
			'#'=>$leaderBoardLine['car']['raceNumber'],
			'Team'=>$leaderBoardLine['car']['teamName'],
			'VehicleNumber'=>$leaderBoardLine['car']['carId'],
			'VehicleFile'=>$leaderBoardLine['car']['carModel'],
			'VehicleType'=>$leaderBoardLine['car']['cupCategory'],
			// Unused: Vehicle, UpgradeCode
			'Driver'=>"{$leaderBoardLine['currentDriver']['firstName']} {$leaderBoardLine['currentDriver']['lastName']}",
			'Lobby Username'=>$leaderBoardLine['currentDriver']['playerId'],
		);
		$entry =& lookup_entry($slot, $isRace, false);

		if ($isRace) {
			$entry['raceLaps'] = $leaderBoardLine['timing']['lapCount'];
			//$entry['raceTime'] = bcdiv($leaderBoardLine['timing']['totalTime'], 1000, 3);
			$entry['raceBestLapTime'] = bcdiv($leaderBoardLine['timing']['bestLap'], 1000, 3);
			//$entry['raceBestLapNo'] = ;
			//$entry['Pitstops'] = ;
			//$entry['LapsLed'] = ;
			//$entry['IncidentPoints'] = ;
			$entry['RacePos'] = ++$simPos;
		} else {
			$entry['qualLaps'] = $leaderBoardLine['timing']['lapCount'];
			$entry['qualBestLapTime'] = bcdiv($leaderBoardLine['timing']['bestLap'], 1000, 3);
			//$entry['qualBestLapNo'] = ;
			$entry['GridPos'] = ++$simPos;
		}
	}
}

?>
