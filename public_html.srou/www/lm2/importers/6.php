<?php
ini_set('display_errors', 'stdout');

// Race Room Experience importer
function showFileChoosers() {
?>
    <TR><TD><B>XML</B></TD><TD><INPUT size="120" name="xml" type="file" /></TD></TR>
<?php
}

function doImport() {
	global $location, $race_start_time;

	$result = load_xml_file($_FILES['xml']);
	$location = get_single_element_text($result, 'Track');
	($race_start_time = strtotime(get_single_element_text($result, 'Time'))) || die("bad time");

	$race = $qual = null;
	foreach ($result->getElementsByTagName('MultiplayerRaceSession') as $session) {
		($type = get_single_element_text($session, 'Type')) || die("No session type");
		if ($type == 'Qualify') $qual = $session;
		else if ($type == 'Race') $race = $session;
	}
echo "<P>Track is $location</P>\n";
echo "<P>Race start time is $race_start_time</P>\n";

	maybeParseXmlSession($race, true);
	maybeParseXmlSession($qual, false);

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

	($root->tagName == 'MultiplayerRaceResult') || die("root node is not MultiplayerRaceResult");
	(get_single_element_text($root, 'Experience') == 'RaceRoom Experience') || die("Does not appear to be RaceRoom Experience");

	return $root;
} // End load_xml_file()

function maybeParseXmlSession($session, $isRace) {
	if (!$session)
		return;

	foreach ($session->getElementsByTagName('MultiplayerRacePlayer') as $driver_entry) {
		// Fake the old form...
		$slot = array();
		$slot['Driver'] = $slot['Lobby Username'] = get_single_element_text($driver_entry, 'Username');
		$slot['Vehicle'] = get_single_element_text($driver_entry, 'Car');
		//$slot['VehicleNumber'] = get_single_element_text($driver_entry, 'CarNumber');
		//$slot['Team'] = get_single_element_text($driver_entry, 'TeamName');
		//$slot['VehicleFile'] = get_single_element_text($driver_entry, 'VehFile');
		//$slot['VehicleType'] = get_single_element_text($driver_entry, 'CarType');
		//$slot['UpgradeCode'] = decodeUpgradeCode(get_single_element_text($driver_entry, 'UpgradeCode'));
		echo "<HR/>" . print_r($slot, true) . "\n";

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
