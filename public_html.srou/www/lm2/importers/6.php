<?php
ini_set('display_errors', 'stdout');

// Race Room Experience importer
function showFileChoosers() {
?>
    <TR>
      <TD><B>XML</B></TD>
      <TD><INPUT size="120" name="xml" type="file" /></TD>
    </TR><TR>
      <TD><B>Race selection</B></TD>
      <TD><SELECT name="raceType">
        <OPTION VALUE="Race">Single race or first race of double header</OPTION>
        <OPTION VALUE="Race2">Second race of double header</OPTION>
      </SELECT></TD>
    </TR><TR>
      <TD COLSPAN="2"><I>For RRE double headers, import the first race as normal, then:
        <UL>
          <LI>import the second race using the alternate 'Race selection' option above,</LI>
          <LI>use the 'Event Entries' in Reference Data to wipe the qualifying positions and set the starting positions appropriately,</LI>
          <LI>and generate standings.</LI>
        </UL>
      </I></TD>
    </TR>
<?php
}

function doImport() {
	global $location, $race_start_time;

	$xpath = load_xml_file($_FILES['xml']);
	$xpath->registerNamespace('m', 'http://schemas.datacontract.org/2004/07/CommunityMultiplayer.Services');
	$result= $xpath->document->documentElement;
	($result->tagName == 'MultiplayerRaceResult') || die("root node is not MultiplayerRaceResult");
	($xpath->evaluate('string(m:Experience)') == 'RaceRoom Experience') || die("Does not appear to be RaceRoom Experience");
	($location = $xpath->evaluate('string(m:Track)')) || die("No location");
	($race_start_time = strtotime($xpath->evaluate('string(m:Time)'))) || die("No time");
        if ($_REQUEST['raceType'] == 'Race') $race_start_time -= 600;

	$race = $qual = null;
//$result->getElementsByTagName('MultiplayerRaceSession')
	foreach ($xpath->query('m:Sessions/m:MultiplayerRaceSession') as $session) {
		($type = $xpath->evaluate('string(m:Type)', $session)) || die("No session type");
		if ($type == 'Qualify') $qual = $session;
		else if ($type == $_REQUEST['raceType']) $race = $session;
	}
echo "<P>Track is $location</P>\n";
echo "<P>Race start time is $race_start_time</P>\n";

	maybeParseXmlSession($xpath, $race, true);
	maybeParseXmlSession($xpath, $qual, false);

	return true;
}

function load_xml_file($file) {
	if (!$file || $file['size'] == 0) {
		return null;
	}

	echo '<P>' . $file['tmp_name'] . ' from ' . $file['name'] . ' size ' . $file['size'] . '</P>';

	// , DOMXML_LOAD_RECOVERING
	($dom = DOMDocument::load($file['tmp_name'])) || die("Error while loading/parsing {$file['tmp_name']}");

	return new DOMXPath($dom);
} // End load_xml_file()

function maybeParseXmlSession($xpath, $session, $isRace) {
	if (!$session)
		return;

	foreach ($xpath->query('m:Players/m:MultiplayerRacePlayer', $session) as $driver_entry) {
		// Fake the old form...
		$slot = array();
		$slot['Lobby Username'] = $xpath->evaluate('string(m:Username)', $driver_entry);
		$slot['Driver'] = $xpath->evaluate('string(m:FullName)', $driver_entry) ?: $slot['Lobby Username'];
		if (!$slot['Driver']) $slot['Driver'] = '{unknown}';
		$slot['Vehicle'] = $xpath->evaluate('string(m:Car)', $driver_entry);
		//$slot['VehicleNumber'] = ?
		//$slot['Team'] = ?
		//$slot['VehicleFile'] = ?
		//$slot['VehicleType'] = ?
		//$slot['UpgradeCode'] = ?
//echo "<HR/>" . print_r($slot, true) . "\n";

		$entry = &lookup_entry($slot, $isRace);
		if ($entry == null) continue;

		$bestLapTime = millisecondsToSeconds($xpath->evaluate('string(m:BestLapTime[number(.) > 0])', $driver_entry));

		if ($isRace) {
			$laps = $xpath->query('m:RaceSessionLaps', $driver_entry)->item(0);
			$entry['raceTime'] = millisecondsToSeconds($xpath->evaluate('string(m:TotalTime[. != "-1"])', $driver_entry));
			$entry['reason'] = $xpath->evaluate('string(m:FinishStatus)', $driver_entry) == 'Finished' ? null : 0;
			$entry['raceLaps'] = $xpath->evaluate('count(m:MultiplayerRaceSessionLap[number(m:Time) > 0])', $laps);
			$entry['raceBestLapTime'] = $bestLapTime;
			$bestLapXPath = 'm:MultiplayerRaceSessionLap[m:Time = ../../m:BestLapTime]';
			$entry['raceBestLapNo'] = $xpath->evaluate("count($bestLapXPath/preceding-sibling::m:MultiplayerRaceSessionLap) + number(boolean($bestLapXPath))", $laps) ?: null;
			$entry['Pitstops'] = $xpath->evaluate('count(m:MultiplayerRaceSessionLap[m:PitStopOccured = "true"])', $laps);
			//$entry['GridPos'] = ?
			$entry['RacePos'] = $xpath->evaluate('string(m:Position)', $driver_entry);
		} else {
			//$entry['qualLaps'] = ;
			$entry['qualBestLapTime'] = $bestLapTime;
			//$entry['qualBestLapNo'] = ;
		}

//if (!$isRace) echo "<BR/> = <B>" . print_r($entry, true) . "</B>\n";
	}
} // End maybeParseXmlSession()

function millisecondsToSeconds($millis) {
	return $millis ? bcdiv($millis, '1000', 3) : null;
}

?>
