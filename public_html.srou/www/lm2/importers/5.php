<?php
// Assetto Corsa importer

function showFileChoosers() {
?>
    <TR><TD>Server log</TD><TD><INPUT size="120" name="acServerLog" type="file" /></TD></TR>
<?php
}

class Sessions {
	const PREAMBLE = 999;
	const BOOK = 0;
	const PRACTICE = 1;
	const QUALIFY = 2;
	const RACE = 3;
}

function doImport() {
	$file = $_FILES["acServerLog"];
	($handle = fopen($file['tmp_name'], "r")) || die("can't open {$file['tmp_name']}");
	do {
		$line = fgets($handle);
		if ($line === FALSE) die("No server banner found (or import of wrong version)");
	} while (!preg_match('/^Assetto Corsa Dedicated Server v(\\d+.\\d+.\\d+)$/', $line, $matches));
	echo('<P>AC dedicated server version ' . htmlentities($matches[1], ENT_QUOTES) . "</P>");

	$racesOutput = 0;
	$race = FALSE;
	$sessionType = Sessions::PREAMBLE;
	do {
		$line = fgets($handle);
		$outputRace = FALSE;
		if ($line === FALSE) {
			$outputRace = $race;
		} else if (preg_match('/^SENDING session type : (\\d+)$/', $line, $matches)) {
			$newSessionType = (int)$matches[1];
			if ($newSessionType < $sessionType) {
				if ($sessionType == Sessions::RACE) $outputRace = $race;
				$race = array('location' => $currentTrack);
			}
			$sessionType = $newSessionType;
		} else if (preg_match('/^(?:TRACK=(\\S+)|Changed track:\\s+(\\S+))$/', $line, $matches)) {
			$currentTrack = $matches[1] ?: $matches[2];
		} else if (preg_match('/^Adding car: SID:(\d+) name=(.+) model=(\\S+) skin=(\\S+) guid=(\d+)$/', $line, $matches)) {
			$race['sesid'][$matches[1]] = array(
				'Lobby Username'=>$matches[5],
				'Driver'=>$matches[2],
				'#'=>$matches[1],
				'Vehicle'=>$matches[3],
				'_skin'=>$matches[4],
			);
		} else if (preg_match('/^SendLapCompletedMessage$/', $line, $matches)) {
//TODO: only really want the last one in the session or we might end up with drivers who leave mucking it up?
			while (preg_match('/^(\\d+)\\) (.*) BEST: (\\d+:\\d+:\\d+) TOTAL: (\\d+:\\d+:\\d+) Laps:(\\d+) SesID:(\d+)$/', ($line = fgets($handle)), $matches)) {
				if (!$matches[2]) continue; // Pickup races may have unfilled slots.
				($sesid = &$race['sesid'][$matches[6]]) || die("Unknown sesid in {$matches[0]}");
				($sesid['Driver'] == $matches[2]) || die("Name mismatch {$race['sesid'][$matches[1]]['Driver']} v. {$matches[2]}");
				$sesid[$sessionType] = array(
					'pos'=>$matches[1],
					'best'=>decode_ac_time($matches[3]),
					'total'=>decode_ac_time($matches[4]),
					'laps'=>$matches[5],
				);
			}
			preg_match('/^SendLapCompletedMessage END$/', $line) || die('Bad results block ' . htmlentities($line, ENT_QUOTES));
		} else if (preg_match('/^CHAT \\[(.+)\\]: PLP: running version (.+)\\|\\1$/', $line, $matches)) {
			$race['plp'][$matches[1]] = $matches[2];
		}

		if ($outputRace && array_key_exists('sesid', $outputRace)) {
			++$racesOutput;
			// Annoyingly, JSON_PRETTY_PRINT isn't suppored until PHP 5.4.0.
			//echo "<pre>" . json_encode($outputRace, JSON_HEX_TAG|JSON_HEX_AMP|JSON_PRETTY_PRINT) . "</pre>";
			donkey($outputRace);
		}
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
	global $fatal_errors, $location, $entries;
	// Things we can't fill in. :-(
	global $track_length, $race_start_time;

	$location = $race['location'];
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
		$plp = $race['plp'][$slot['Driver']];
		$entry['reason'] = $plp ? -1 : null;
array_push($fatal_errors, "{$slot['Driver']} --- $plp");
	}
}

?>
