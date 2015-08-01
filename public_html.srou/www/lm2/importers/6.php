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
		if (!$slot['Driver']) $slot['Driver'] = '{unknown}';
		$slot['Vehicle'] = get_single_element_text($driver_entry, 'Car');
		//$slot['VehicleNumber'] = get_single_element_text($driver_entry, 'CarNumber');
		//$slot['Team'] = get_single_element_text($driver_entry, 'TeamName');
		//$slot['VehicleFile'] = get_single_element_text($driver_entry, 'VehFile');
		//$slot['VehicleType'] = get_single_element_text($driver_entry, 'CarType');
		//$slot['UpgradeCode'] = decodeUpgradeCode(get_single_element_text($driver_entry, 'UpgradeCode'));
		//echo "<HR/>" . print_r($slot, true) . "\n";

		$entry = &lookup_entry($slot, $isRace);
		if ($entry == null) continue;

		$bestLapTime = get_single_element_text($driver_entry, 'BestLapTime', null);
		if ($bestLapTime <= 0) $bestLapTime = null;

		if ($isRace) {
			//$entry['raceTime'] = ;
			//$entry['reason'] = "-1";
			//$entry['raceLaps'] = get_single_element_text($driver_entry, 'Laps');
			$entry['raceBestLapTime'] = $bestLapTime;
			//$entry['raceBestLapNo'] = ;
			//$entry['Pitstops'] = get_single_element_text($driver_entry, 'Pitstops');
			//$entry['GridPos'] = get_single_element_text($driver_entry, 'GridPos');
			$entry['RacePos'] = get_single_element_text($driver_entry, 'Position');
		} else {
			//$entry['qualLaps'] = ;
			$entry['qualBestLapTime'] = $bestLapTime;
			//$entry['qualBestLapNo'] = ;
		}

		//echo "<BR/> = <B>" . print_r($entry, true) . "</B>\n";
	}
} // End maybeParseXmlSession()

?>
