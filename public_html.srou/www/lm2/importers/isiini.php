<?php
// Generic importer bits for ISI-based sims using .INI style exports.

$vehicleType = $_REQUEST['mod'];

function doImport() {
	maybeParseIniFile('race', true); // Do this first!
	maybeParseIniFile('qual', false);
	maybeParseIniFile('qual1', false);
}

function maybeParseIniFile($file, $isRace) {
	$file = $_FILES[$file];

	if ($file['size'] == 0) {
		return;
	}

	echo '<P>' . $file['tmp_name'] . ' from ' . $file['name'] . ' size ' . $file['size'] . '</P>';
	$contents = file_get_contents($file['tmp_name']) . "\n[END]\n"; // Add [END] just in case it doesn't have one.

	return maybeParseFile($contents, $isRace);
}

// Note: if it isn't the race it's qualifying!
function maybeParseFile($contents, $isRace) {
	global $sim, $hackSlot;

	// Nuke any comments.
	$contents = preg_replace('|^\s*//.*$|m', '', $contents);

	$sections = parse_sections($contents);

	$simName = $sections['Header']['Game'];
	$version = $sections['Header']['Version'];
	check_sim_header($simName, $version, $isRace);
	//} else if ($simName == 'GTR') {
	//	($version == '1.400') || die("Only version 1.400 of GTR is supported");

	if ($isRace) {
		global $race_start_time;
		($race_start_time = parse_time_string($sections['Header']['TimeString'])) || die("missing or bad time");
	}

	unset($sections['Header']);

	($sections['Race']['RaceMode'] == '5') || die("Exports must be from online mode");

	(preg_match('/^GAMEDATA\\\\LOCATIONS\\\\(.+)\\.TRK$/i', $sections['Race']['Scene'], $location_match) == 1) || die("bad track location");
	$session_location = $location_match[1];
	($session_track_length = $sections['Race']['Track Length']) || die("no track length");

	global $location;
	global $track_length;
	if ($location == null) {
		$location = $session_location;
		$track_length = $session_track_length;
	} else if (strcasecmp($location, $session_location) != 0) {
		die("locations don't match: $location != $session_location");
	} else if ($session_track_length != $track_length) {
		die("track lengths don't match: $session_track_length != $track_length");
	}

	unset($sections['Race']);

	global $vehicleType;
	foreach ($sections AS $slotId=>$slot) {
		(preg_match('/^\\d+Slot(\\d{3})$/i', $slotId, $matches) == 1) || die("bad slot ID: $slotId");
		$slot['#'] = $matches[1];
		$slot['VehicleType'] = $vehicleType;

		if (function_exists("hackSlot")) {
			hackSlot($slot);
		}

		$entry = &lookup_entry($slot, $isRace);
		if ($entry == null) continue;

		if ($isRace) {
			$entry['raceTime'] = $slot['RaceTime'];
			$entry['raceLaps'] = $slot['Laps'];
			$entry['raceBestLapTime'] = $slot['BestLap']['Time'];
			$entry['raceBestLapNo'] = $slot['BestLap']['Lap'];
			($entry['reason'] = $slot['Reason']) > 9 && die("unexpected reason code {$entry['reason']}");
			if ($sim != 7) { // Avoid for Race '07 because it gets it wrong in double headers.
				$entry['qualBestLapTime'] = $slot['QualTime']; // Will be overwritten if GTR1 or GTL.
			}
			$entry['CarKG'] = $slot['PenaltyCar'];
			if (isset($slot['Penalty']) && !is_null($slot['Penalty'])) {
				$entry['DriverKG'] = $slot['Penalty'];
			}
		} else {
			$entry['qualLaps'] = $slot['Laps'];
			$entry['qualBestLapTime'] = $slot['BestLap']['Time'];
			$entry['qualBestLapNo'] = $slot['BestLap']['Lap'];
		}
	}
} // maybeParseFile - text version

function parse_sections($contents) {
	preg_match_all('/^\s*\[([^]]+)\]\s*$(.*)(?=^\s*\[[^]]+\]\s*$)/msU', $contents, $sections1, PREG_SET_ORDER);
	global $fatal_errors;

	$slot_prefix = 0;
	$sections = array();
	foreach ($sections1 AS $section) {
		$id = $section[1];
		if (preg_match('/^Slot\\d{3}$/', $id, $matches)) {
			$id = ++$slot_prefix . $id;
		}
		array_key_exists($id, $sections) && array_push($fatal_errors, "found [$id] twice!");
		$sections[$id] = parse_section_lines($section[2]);
	}

	unset($sections['END']); // Don't actually want these.

	return $sections;
} // End parse_sections()

function parse_section_lines($lineText) {
	preg_match_all('/^([^=]+)=(.*?)$/ms', $lineText, $lines1, PREG_SET_ORDER);
	$lines = array();
	$bestLap = null;
	$gtrBestLap = null;
	$dq = false;
	foreach ($lines1 AS $line) {
		// Do the trim here rather than in the regexp as \h doesn't work. :-(
		$name = trim($line[1]);
		$value = trim($line[2]);
		if ($name == 'Lap') {
			// Have to handle these specially because there's more than one of them.
			(sscanf($value, "(%d, %g, %s)", $lapIndex, $offset, $lapTime) == 3) || die("$line_no: bad lap $value");
				$lap = array(Lap=>$lapIndex, Time=>parseTime($lapTime, "reading individual Lap data"));
			if ($bestLap == null || $bestLap['Time'] > $lap['Time']) {
				$bestLap = $lap;
			}
		} else {
			if ($name == 'BestLap' || $name == 'RaceTime' || $name == 'QualTime') {
				if ($value == 'DQ') {
					($name == 'RaceTime') || die("$name cannot be DQ");
					$value = null;
					$dq = true;
				} else {
					$value = parseTime($value, "reading $name");
					if ($name == 'BestLap') {
						$gtrBestLap = $value;
						$value = null;
					}
				}
			}
			if (!is_null($value)) {
				$lines[$name] = $value;
			}
		}
	}

	if ($dq) {
		if (!is_null($lines['Reason'])) {
			echo "<P>Warning: discarding reason code {$lines['Reason']} to record DQ</P>\n";
		}
		$lines['Reason'] = "-1";
	}

	if (array_key_exists('BestLap', $lines)) {
		$gtrBestLap = array(Lap=>null, Time=>$lines['BestLap']);
	}

	if (is_null($bestLap)) {
		if (!is_null($gtrBestLap)) {
			$bestLap = array(Lap=>null, Time=>$gtrBestLap);
		}
	} else if (!is_null($gtrBestLap)) {
		if ($gtrBestLap != $bestLap['Time']) {
			echo "<P>Warning: best lap times do not match: $gtrBestLap != " .$bestLap['Time'] . "</P>";
		}
	}
	$lines['BestLap'] = $bestLap;

	return $lines;
} // End parse_section_lines()

?>