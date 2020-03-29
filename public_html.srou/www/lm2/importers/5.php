<?php
// Assetto Corsa importer

function showFileChoosers() {
?>
    <TR><TH>Server log</TD><TD><INPUT size="120" name="acServerLog" type="file" /></TD></TR>
    <TR><TD COLSPAN="2" ALIGN="CENTER"><i>Choose log file (above) or JSON exports (below)</i></TD></TR>
    <TR><TH>Qualifying JSON (optional)</TH><TD><INPUT size="120" name="acQualJson" type="file" /></TD></TR>
    <TR><TH>Race JSON</TH><TD><INPUT size="120" name="acRaceJson" type="file" /></TD></TR>
<?php
}

function doImport() {
	$log = $_FILES["acServerLog"]['size'] ? $_FILES["acServerLog"]['tmp_name'] : null;
	$q = $_FILES["acQualJson"]['size'] ? $_FILES["acQualJson"]['tmp_name'] : null;
	$r = $_FILES["acRaceJson"]['size'] ? $_FILES["acRaceJson"]['tmp_name'] : null;
	if ($r) {
		if ($log) echo '<P><I>Using JSON; ignoring log file</I></P>';
		doImportJson($q, $r);
	} else if ($log) {
		if ($q) echo '<P><I>Using log; ignoring qualifying JSON</I></P>';
		doImportLog($log);
	} else {
		die("Neither log nor JSON files supplied");
	}
}

class Sessions {
	const PREAMBLE = 999;
	const BOOK = 0;
	const PRACTICE = 1;
	const QUALIFY = 2;
	const RACE = 3;
}

function doImportJson($qFilename, $rFilename) {
	global $fatal_errors;

	$cars = array();

	processJson($rFilename, Sessions::RACE, $cars);
	if ($qFilename) processJson($qFilename, Sessions::QUALIFY, $cars);

	foreach ($cars AS &$slot) {
if (!$slot['Driver'] && !$slot['Lobby Username']) {
	echo "Skipping empty slot {$slot['#']}<br/>\n";
	continue;
}
		$entry =& lookup_entry($slot, true, false);

		$entry['DriverKG'] = $slot['DriverKG'];

		if (count($slot['sesid'][Sessions::RACE]['Laps'])) {
			$entry['raceLaps'] = count($slot['sesid'][Sessions::RACE]['Laps']);
			$entry['raceTime'] = bcdiv($slot['sesid'][Sessions::RACE]['Result']['TotalTime'], 1000, 3);
			$entry['raceBestLapTime'] = bcdiv($slot['sesid'][Sessions::RACE]['Result']['BestLap'], 1000, 3);
			$entry['raceBestLapNo'] = $slot['sesid'][Sessions::RACE]['BestLapNo'];
			//$entry['Pitstops'] = ;
			//$entry['LapsLed'] = ;
			//$entry['IncidentPoints'] = ;
			$entry['RacePos'] = $slot['sesid'][Sessions::RACE]['SimPos'];
		}

		if ($qFilename && count($slot['sesid'][Sessions::QUALIFY]['Laps'])) {
			$entry['qualLaps'] = count($slot['sesid'][Sessions::QUALIFY]['Laps']);
			$entry['qualBestLapTime'] = bcdiv($slot['sesid'][Sessions::QUALIFY]['Result']['BestLap'], 1000, 3);
			$entry['qualBestLapNo'] = $slot['sesid'][Sessions::QUALIFY]['BestLapNo'];
			$entry['GridPos'] = $slot['sesid'][Sessions::QUALIFY]['SimPos'];
		}
$entry['__slot__'] = &$slot;
	}
}

function processJson($filename, $session, &$cars) {
	global $location, $entries, $modReport;

	$json = json_decode(file_get_contents($filename), true);
	$json['Events'] = null; //TODO: maybe report them

	((new ReflectionClass('Sessions'))->getConstants()[$json['Type']] == $session) || die("Unexpected type {$json['Type']} for session ID $session");

    $newLocation = $json['TrackName'];
    if ($json['TrackConfig']) $newLocation .= ":" . $json['TrackConfig'];

	if ($location) $location == $newLocation || die("Locations $location and $newLocation don't match");
	else $location = $newLocation;

	foreach ($json['Cars'] as &$car) {
		$slot = array(
			'#'=>$car['CarId'],
			'Driver'=>$car['Driver']['Name'],
			'Lobby Username'=>$car['Driver']['Guid'],
			'Vehicle'=>$car['Model'],
			'_skin'=>$car['Skin'],
			'DriverKG'=>$car['BallastKG']
		);
		if (array_key_exists($car['CarId'], $cars)) {
			$oldSlot =& $cars[$car['CarId']];
			array_intersect($slot, $oldSlot) == $slot || die("Mismatch between " . print_r($oldSlot, true) . " and " . print_r($slot, true));
		} else {
			$slot['sesid'] = [];
			$cars[$car['CarId']] = $slot;
		}
	}

	$simPos = 0;
	foreach ($json['Result'] as &$result) {
		$slot =& $cars[$result['CarId']];
		$slotData = slotDataFromResultOrLap($result);
		array_intersect($slotData, $slot) == $slotData || die("Mismatch between " . print_r($slot, true) . " and result's " . print_r($slotData, true));
		$slot['sesid'][$session] = [ 'Result'=>&$result, 'Laps'=>[], 'SimPos'=>++$simPos ];
	}

	// Note - assumes laps are in order!
	foreach ($json['Laps'] as &$lap) {
		$slot =& $cars[$lap['CarId']];
		$slotData = slotDataFromResultOrLap($lap);
		array_intersect($slotData, $slot) == $slotData || die("Mismatch between " . print_r($slot, true) . " and lap's " . print_r($slotData, true));
		!array_key_exists($lap['Timestamp'], $slot['sesid'][$session]['Laps']) || die("Already got lap at timestamp {$lap['Timestamp']}");
		$sessionSlot =& $slot['sesid'][$session];
		$sessionSlot['Laps'][$lap['Timestamp']] =& $lap;
		if ($lap['LapTime'] == $sessionSlot['Result']['BestLap'] && !$sessionSlot['BestLapNo']) {
			$sessionSlot['BestLapNo'] = count($sessionSlot['Laps']);
		}
unset($lap['Sectors']);
unset($lap['DriverName']);
unset($lap['DriverGuid']);
unset($lap['BallastKG']);
unset($lap['CarModel']);
unset($lap['CarId']);
unset($lap['Timestamp']);
	}
}

function slotDataFromResultOrLap(&$resultOrLap) {
	return [ 'Driver'=>$resultOrLap['DriverName'], 'Lobby Username'=>$resultOrLap['DriverGuid'], 'Vehicle'=>$resultOrLap['CarModel'], 'DriverKG'=>$resultOrLap['BallastKG'] ];
}

//////////////////////////////// JSON above, Log File below ////////////////////////////////

function doImportLog($filename) {
	global $fatal_errors;

	($handle = fopen($filename, "r")) || die("can't open $filename");
	do {
		$line = fgets($handle);
		if ($line === FALSE) die("No server banner found (or import of wrong version)");
	} while (!preg_match('/^Assetto Corsa Dedicated Server v(\\d+(?:\\.\\d+)+(?: \S+)?)$/', $line, $matches));
	echo('<P>AC dedicated server version ' . htmlentities($matches[1], ENT_QUOTES) . "</P>");

	$entryList = array();
	$racesOutput = 0;
	$race = FALSE;
	$guid2sesid = FALSE;
	$sessionType = Sessions::PREAMBLE;
	do {
		$line = fgets($handle);
		$outputRace = FALSE;
		if ($line === FALSE) {
			array_key_exists('location', $race) &&($outputRace = $race);
		} else if (preg_match('/^SENDING session type : (\\d+)$/', $line, $matches)) {
			$newSessionType = (int)$matches[1];
			if ($newSessionType <= $sessionType) {
				if ($sessionType == Sessions::RACE) $outputRace = $race;
				$race = $entryList;
				$race['location'] = $currentTrack;
				$guid2sesid = array();
			}
			$sessionType = $newSessionType;
		} else if (preg_match('/^(?:TRACK=(\\S+)|Changed track:\\s+(\\S+))$/', $line, $matches)) {
			// Ignore - doesn't distinguish layouts any more. :-(
			//$currentTrack = $matches[1] ?: $matches[2];
		} else if (preg_match('%^CALLING\\s+http.*/lobby.ashx/register\\?.*&track=([^&]+)&%', $line, $matches)) {
			$currentTrack = $matches[1];
		} else if (preg_match('/^Updating car info for GUID (\\d+), name=(.+) model=(\\S+) skin=(\\S+)$/', $line, $matches)) {
			array_key_exists($matches[1], $guid2sesid) || die("Update for unknown GUID {$matches[1]} @" . ftell($handle));
			$race['sesid'][$guid2sesid[$matches[1]]]['Driver'] = $matches[2];
			$race['sesid'][$guid2sesid[$matches[1]]]['Vehicle'] = $matches[3];
			$race['sesid'][$guid2sesid[$matches[1]]]['_skin'] = $matches[4];
		} else if (preg_match('/^NEW PICKUP CONNECTION from .*$/', $line, $matches)) {
			$line = fgets($handle) . fgets($handle);
			preg_match('/^VERSION (\d+)$\s+^(\S+(?:\s+\S+)*)$/m', $line, $matches) || die("Expected VERSION line and driver @" . ftell($handle). " but got <pre>$line</pre>");
			$matches[1] == '160' || die("Unknown pickup block version {$matches[1]} @" . ftell($handle));
			$driverName = $matches[2];
			$lineOffset = ftell($handle);
			preg_match('/^REQUESTED CAR: (\S+)\*$/', fgets($handle), $matches) || die("No car requested @$lineOffset");
			$car = $matches[1];
			for (;;) {
				($line = fgets($handle)) !== FALSE || die("Never found end of pickup block @$lineOffset");
				if (preg_match('/Slot found(?: by name)? at index (\d+)$/', $line, $matches)) break;
				if (preg_match('/, client refused$/', $line)) continue 2;
				if (preg_match('/^(?:ENTRY LIST OPEN MODE [01]|Looking for available slot(?: by name)?)$/', $line))
					continue;
				die("Unexpected pickup block line @" . ftell($handle));
			}
			$race['sesid'][$matches[1]] = array(
				'Lobby Username'=>null,
				'Driver'=>$driverName,
				'#'=>$matches[1],
				'Vehicle'=>$car,
				'_skin'=>null,
			);
			while (!preg_match('/^OK$/', ($line = fgets($handle)))) {
				$line === FALSE && die("Never found end of accepted pickup block @$lineOffset");
			}
		} else if (preg_match('/^Opening entry list:\s+/', $line)) {
			$lineOffset = ftell($handle);
			while (!preg_match('/^(?:Random seed: \d+|NextSession)$/', ($line = fgets($handle)))) {
				$line === FALSE && die("Never found end of entry list block @$lineOffset");
				if (preg_match('/^CAR:\s+(\d+)\s+(\S+)\s+\(\1\)\s+\[(.+) \[(.*)\]\]\s+\3\s+\[\4\]\s+(\d+)\s+(\d+)\s+kg$/', $line, $matches)) {
					$entryList['sesid'][$matches[1]] = array(
						'Lobby Username'=>$matches[5],
						'Driver'=>$matches[3],
						'#'=>$matches[1],
						'Vehicle'=>$matches[2],
						'_skin'=>$matches[4],
						'Penalty'=>$matches[6],
					);
				} else if (!preg_match('/^(?:Found car  CAR_\d+$|open setups\/)/', $line, $matches))
					die("Unexpected entry list line @" . ftell($handle));
			}
		} else if (preg_match('/^Adding car: SID:(\d+) name=(.+) model=(\\S+) skin=(\\S+) guid=(\d+)$/', $line, $matches)) {
			$race['sesid'][$matches[1]] = array(
				'Lobby Username'=>$matches[5],
				'Driver'=>$matches[2],
				'#'=>$matches[1],
				'Vehicle'=>$matches[3],
				'_skin'=>$matches[4],
			);
			$guid2sesid[$matches[5]] = $matches[1];
		} else if (preg_match('/^SendLapCompletedMessage$/', $line, $matches)) {
//TODO: only really want the last one in the session or we might end up with drivers who leave mucking it up?
			while (preg_match('/^(\\d+)\\) (.*) BEST: (\\d+:\\d+:\\d+) TOTAL: (\\d+:\\d+:\\d+) Laps:(\\d+) SesID:(\d+)$/', ($line = fgets($handle)), $matches)) {
				if (!$matches[2]) continue; // Pickup races may have unfilled slots.
				($sesid = &$race['sesid'][$matches[6]]) || die("Unknown sesid in {$matches[0]} @" . ftell($handle));
				($sesid['Driver'] == $matches[2]) || die("Name mismatch, expected '{$sesid['Driver']}' but got '{$matches[2]}' @" . ftell($handle));
				$sesid[$sessionType] = array(
					'pos'=>$matches[1],
					'best'=>decode_ac_time($matches[3]),
					'total'=>decode_ac_time($matches[4]),
					'laps'=>$matches[5],
				);
			}
		} else if (preg_match('/^CHAT \\[(.+)\\]: PLP: running version (.+)\\|\\1$/', $line, $matches)) {
			$race['plp'][$matches[1]] = $matches[2];
		}

		if ($outputRace && array_key_exists('sesid', $outputRace)) {
			++$racesOutput;
			// Annoyingly, JSON_PRETTY_PRINT isn't suppored until PHP 5.4.0.
			//echo "<pre>" . json_encode($outputRace, JSON_HEX_TAG|JSON_HEX_AMP|JSON_PRETTY_PRINT) . "</pre>";
			donkey($outputRace);
			$race = FALSE;
			break; // Pickup races carry session IDs forward - let's just not deal with it right now!
		}
//$outputRace && !is_array($outputRace) && die("true but not?! ..". print_r($outputRace, true) . ".. @" . ftell($handle));
	} while (!feof($handle));
	fclose($handle);

	($racesOutput == 1) || die("$racesOutput races found, cannot cope");
}

function decode_ac_time($s) {
	if ($s == '16666:39:999' || $s == '0:00:000') return null;
	preg_match('/^(\\d+):(\\d+):(\\d{3})$/', $s, $matches) || die("Bad time $s");
	return bcadd(bcmul($matches[1], '60'), "{$matches[2]}.{$matches[3]}", 3);
}

function donkey($race) {
	global $location, $entries, $modReport;
	// Things we can't fill in. :-(
	global $track_length, $race_start_time;

	$location = $race['location'];
	$modReport .= "\n[table]";
	foreach ($race['sesid'] AS $slot) {
		$entry = &lookup_entry($slot, true, false);
		$entry['qualBestLapTime'] = $slot[Sessions::QUALIFY]['best'];
		$entry['qualLaps'] = $slot[Sessions::QUALIFY]['laps'];
		//TODO 'qualBestLapNo'
		$entry['GridPos'] = $slot[Sessions::QUALIFY]['pos'];
		$entry['raceTime'] = $slot[Sessions::RACE]['total'];
		$entry['raceLaps'] = $slot[Sessions::RACE]['laps'];
		$entry['raceBestLapTime'] = $slot[Sessions::RACE]['best'];
		//TODO 'raceBestLapNo'
		$entry['RacePos'] = $slot[Sessions::RACE]['pos'];
		//TODO 'LapsLed'
		$modReport .= "\n[tr][td]" . $slot['Driver'] . '[/td][td]' . $race['plp'][$slot['Driver']] . '[/td][/tr]';
	}
	$modReport .= '[/table]';
}

?>
