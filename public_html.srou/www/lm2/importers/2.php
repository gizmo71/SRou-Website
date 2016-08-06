<?php
// rFactor importer

function showFileChoosers() {
?>
    <TR><TD>Qual XML</TD><TD><INPUT size="120" name="xmlq" type="file" /></TD></TR>
    <TR><TD><B>Race/Full XML</B></TD><TD><INPUT size="120" name="xml" type="file" /></TD></TR>
<?php
}

function doImport() {
	maybeParseXmlFiles('xml', 'xmlq');
}

function maybeParseXmlFiles($file, $fileQ) {
	global $sim;

	$result = load_xml_file($_FILES[$file]);
	$resultQ = load_xml_file($_FILES[$fileQ]);

	if (!$result) {
		if (!$resultQ) {
			return false;
		}
		// If only Q is available, shuffle them round.
		$result = $resultQ;
		$resultQ = null;
	}

	global $location;
	global $track_length;
	$location = get_single_element_text($result, 'TrackEvent');
	$track_length = get_single_element_text($result, 'TrackLength');

	$race = get_single_element($result, 'Race');

	$qualifying = get_single_element($result, 'Qualify', true);
	if (!is_null($resultQ)) {
		is_null($qualifying) || die("qualifying data found in main export but Q file also given");
		($location == get_single_element_text($resultQ, 'TrackEvent')) || die("locations do not match");
		($track_length == get_single_element_text($resultQ, 'TrackLength')) || die("track lengths do not match");
		!is_null($qualifying = get_single_element($resultQ, 'Qualify')) || die("Q file provided but no qualifying session found");
	}

//echo "<P>Track is $location, length $track_length</P>\n";
	global $race_start_time; //FIXME: Could also use DateTime which is in the PHP format already...
	($race_start_time = parse_time_string(get_single_element_text($race, 'TimeString'))) || die("bad time");
//echo "<P>Race start time is $race_start_time</P>\n";

	maybeParseXmlSession($race, true);
	maybeParseXmlSession($qualifying, false);

	return true;
}

function load_xml_file($file) {
	if (!$file || $file['size'] == 0) {
		return null;
	}

	echo '<P>' . $file['tmp_name'] . ' from ' . $file['name'] . ' size ' . $file['size'] . '</P>';

	// , DOMXML_LOAD_RECOVERING
	($dom = DOMDocument::load($file['tmp_name'])) || die("Error while loading/parsing {$file['tmp_name']}");

	$root = $dom->documentElement;

	($root->tagName == 'rFactorXML') || die("root node is not rFactorXML");
	($root->getAttribute('version') == '1.0') || die("Only version 1.0 of rFactor results XML is supported");

	($result = get_single_element($root, 'RaceResults')) || die("no RaceResults found");
	(get_single_element_text($result, 'Setting') == 'Multiplayer') || die("Exports must be from online mode");

	$rFver = get_single_element_text($result, 'GameVersion');
	($rFver == '1.255') || die("Only versions 1.255 of rFactor is supported");

	return $result;
} // End load_xml_file()

function maybeParseXmlSession($session, $isRace) {
	if (!$session)
		return;

	//FIXME: unify this with the list in the event.php reporting module...
	$rf_reason_codes = array(
		'Suspension'=>4,
		'Accident'=>6,
		'DNF'=>0, // 'unknown'
		'No reason'=>0, // 'unknown'
		//XXX: -1 for DQ, may need massaging in...
		// These are guesswork...
		'Engine'=>1,
		'Gearbox'=>2,
		'Clutch'=>3,
		'Brakes'=>5,
		'Stall'=>7,
		'Electronics'=>8,
		'Fuel'=>9,
	);

	foreach ($session->getElementsByTagName('Driver') as $driver_entry) {
		// Fake the old form...
		$slot = array();
		$slot['Driver'] = $slot['Lobby Username'] = get_single_element_text($driver_entry, 'Name');
		$slot['Vehicle'] = get_single_element_text($driver_entry, 'VehName');
		$slot['VehicleNumber'] = get_single_element_text($driver_entry, 'CarNumber');
		$slot['Team'] = get_single_element_text($driver_entry, 'TeamName');
		$slot['VehicleFile'] = get_single_element_text($driver_entry, 'VehFile');
		$slot['VehicleType'] = get_single_element_text($driver_entry, 'CarType');
		$slot['UpgradeCode'] = decodeUpgradeCode(get_single_element_text($driver_entry, 'UpgradeCode'));
		//echo "<HR/>" . print_r($slot, true) . "\n";

		$entry = &lookup_entry($slot, $isRace);
		if ($entry == null) continue;

		$rf_best_lap_time = get_single_element_text($driver_entry, 'BestLapTime', null);
		$bestLap = null;
		foreach ($driver_entry->getElementsByTagName('Lap') as $lap_entry) {
			$time = parseTime(get_element_text($lap_entry), "reading individual Lap data for {$slot['Driver']}");

			if (is_null($time)) // Doesn't happen in GTx.
				continue;

			$lap = array(Lap=>$lap_entry->getAttribute('num'), Time=>$time);
			if (is_null($bestLap) || $bestLap['Time'] > $time) {
				$bestLap = $lap;
			}
		}
		if (is_null($bestLap)) {
			if (!is_null($rf_best_lap_time)) {
				$bestLap = array(Lap=>null, Time=>$rf_best_lap_time);
			}
		} else if (!is_null($rf_best_lap_time)) {
			if ($rf_best_lap_time != $bestLap['Time']) {
				echo "<P>Warning: best lap times do not match: $rf_best_lap_time != " .$bestLap['Time'] . "</P>";
			}
		}

		$slot['BestLap'] = array(BestLap=>null, Time=>$rf_best_lap_time);

		if ($isRace) {
			$status = get_single_element_text($driver_entry, 'FinishStatus');
			if ($status == "Finished Normally") {
				$entry['raceTime'] = get_single_element_text($driver_entry, 'FinishTime');
			} else if ($status == "DNF") {
				$reason = get_single_element_text($driver_entry, 'DNFReason');
				is_null($entry['reason'] = $rf_reason_codes[$reason]) && die("unknown reason '$reason'");
			} else if ($status == "DQ") {
				$entry['reason'] = "-1";
			} else if ($status == "None") {
				continue; // DNS?
			} else {
				die("unknown FinishStatus $status");
			}
			$entry['raceLaps'] = get_single_element_text($driver_entry, 'Laps');
			$entry['raceBestLapTime'] = $slot['BestLap']['Time'];
			$entry['raceBestLapNo'] = $slot['BestLap']['Lap'];
			//FIXME: consider also storing individual laps against each entry for progression charts...
			$entry['Pitstops'] = get_single_element_text($driver_entry, 'Pitstops');
			$entry['GridPos'] = get_single_element_text($driver_entry, 'GridPos');
			$entry['RacePos'] = get_single_element_text($driver_entry, 'Position'); // rFactor seems to store reliable positions.
			$entry['DriverKG'] = get_single_element_text($driver_entry, 'PenaltyMass', null);
			//FIXME: ControlAndAids (for driver changes and AI control)
		} else {
			$entry['qualLaps'] = get_single_element_text($driver_entry, 'Laps');
			$entry['qualBestLapTime'] = $slot['BestLap']['Time'];
			$entry['qualBestLapNo'] = $slot['BestLap']['Lap'];
		}

		//echo "<BR/> = <B>" . print_r($entry, true) . "</B>\n";
	}
} // End maybeParseXmlSession()

function decodeUpgradeCode($s) {
	$bytes = sscanf($s, "%02x%02x%02x%02x %02x%02x%02x%02x");
	(count($bytes) == 8) || die("bad upgrade code: $s " . print_r($bytes, true));
	$code = "";
	for ($i = 8; $i-- > 0; ) {
		$code .= sprintf("%02x", $bytes[$i]);
	}
	return $code == '0000000000000000' ? null : $code;
} // End decodeUpgradeCode()

?>