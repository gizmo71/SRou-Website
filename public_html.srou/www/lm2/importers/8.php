<?php
// GPL importer

include("semikv.php");

function showFileChoosers() {
	global $lm2_db_prefix;
?>
    <TR><TD COLSPAN="4"><I>Please report any problems via <A HREF="/smf/index.php?board=49.0">UKGPL2</A>.</I></TD></TR>
<?php
	show_mod_selector(); // "mod" input field
?>
    <TR><TD>HTML Export</TD><TD><INPUT size="120" name="export" type="file" /></TD></TR>
    <TR><TD><a href="http://pub.i-line.cz/rpy/">rpydump</a> semikv Report</TD><TD><INPUT size="120" name="semikv" type="file" /></TD></TR>
    <TR><TD COLSPAN="4" ALIGN="RIGHT">Please use both files wherever possible.</TD></TR>
<?php
}

function doImport() {
	global $race_start_time;

	// Run the list of mods off the database somehow. Classes?
	(is_null($mod = $_REQUEST['mod']) || $mod == '') && die("no mod selected");

	function maybeReadFile($file) {
		global $race_start_time;

		$file = $_FILES[$file];
		if ($file['size'] == 0) {
			return null;
		}
		echo '<P>' . $file['tmp_name'] . ' from ' . $file['name'] . ' size ' . $file['size'] . '</P>';

		if (is_null($race_start_time)
			&& preg_match('/(\d{4})\.(\d{2})\.(\d{2})\.(\d{2})\.(\d{2})(?:\.html|\.htm|_Complete\.txt)/i', $file['name'], $matches))
		{
			$race_start_time = mktime($matches[4], $matches[5], 0, $matches[2], $matches[3], $matches[1]);
			//echo "<PRE>acquired time from filename: $race_start_time = " . strftime("%c", $race_start_time) . "</PRE>\n";
		}

		return file_get_contents($file['tmp_name']);
	}

	global $location;
	global $track_length;

	$winnerTime = null;

	if ($htmlExport = maybeReadFile('export')) {
		$htmlExport = mb_convert_encoding($htmlExport, 'UTF-8', 'ISO-8859-1');
		$winnerTime = parse_gpl_html($htmlExport);
	} else {
		$location = '-';
		$track_length = null;
	}

	$location .= '/';

	if ($semikvReport = maybeReadFile('semikv')) {
		$winnerTime = parse_semikv($semikvReport);
	} else {
		$location .= '-';
	}

	is_null($winnerTime) && die("no winner's time - no import?");

	$race_start_time -= $winnerTime;

	global $entries;
	foreach ($entries as $key=>&$entry) {
		if (!$entry['Car']['Vehicle'] && !$entry['Car']['VehicleFile']) {
			echo "<P><I>No car data at all for #{$entry['slot']} {$entry['Driver']}/{$entry['LobbyName']}, so ignoring</I></P>\n"; 
			unset($entries[$key]);
		} else {
			$entry['Car']['VehicleType'] = $mod;
			lookup_driver($entry, $entry['Driver'], $entry['LobbyName']);
		}
	}
}

function parse_semikv($report) {
	global $fatal_errors, $location;

	$semikv = new SemiKV(explode("\n", $report));
	$tables = $semikv->parse();
	$tables || die("rpydump not in semikv format\n<PRE>" . htmlentities($report, ENT_QUOTES) . "</PRE>");

	($eventInfo = $tables['Event Info'][0]) || die("No Event Info table");
	$location .= $eventInfo['track'];

	foreach ($tables['Entry List'] as $e) {
		$slot = array(
			'#'=>$e['startnum'],
			'Driver'=>$e['driver1_name'],
			'Vehicle'=>$e['chassis'],
			'VehicleNumber'=>$e['year'],
			'Team'=>$e['team'],
		);
		$entry = &lookup_entry($slot, null, true);
		$entry['Car']['UpgradeCode'] = htmlCarAsHex($entry['Car']['VehicleFile']);
		$entry['Car']['VehicleFile'] = $e['engine'];
		$entry['LobbyName'] = $entry['LobbyName'] ? "{$entry['LobbyName']} ({$e['driver1_nat']})" : $e['driver1_nat'];
	}

	$winnerTime = null;
	$offsetFromHtml = null;
	foreach ($tables['Race Results'] as $e) {
		$slot = array('#'=>$e['startnum']);
		$entry = &lookup_entry($slot, true, true);
		$lochint = " for " . htmlentities(print_r($e, true));
		check_and_copy($entry['Driver'], $e['drivername'], "Driver$lochint");
		check_and_copy($entry['RacePos'], $e['pos'], "RacePos$lochint");
		check_and_copy($entry['raceLaps'], $e['laps'], "raceLaps$lochint");

		$dummy = $semikv->timeAsSeconds($e['clock']);
		if ($entry['raceTime']) {
			if (!$offsetFromHtml) {
				$offsetFromHtml = $dummy - $entry['raceTime'];
			}
			$dummy -= $offsetFromHtml;
		}
		check_and_copy($entry['raceTime'], $dummy, "raceTime$lochint");

		if ($e['remark'] == 'DNF') {
			$dummy = $entry['reason'] ?: 0;
		} else if (!$e['remark']) {
			$dummy = null;
		} else {
			die("Unexpected remark {$e['remark']}");
		}
		check_and_copy($entry['reason'], $dummy, "reason$lochint");

		if ($entry['RacePos'] == 1) {
			$winnerTime = $entry['raceTime'];
		}
	}
	echo "<p><i>Offset from HTML {$offsetFromHtml}s</i></p>";

	foreach ($tables['Race Fastest Laps'] as $e) {
		$slot = array('#'=>$e['startnum']);
		$entry = &lookup_entry($slot, true, true);
		$lochint = " for " . htmlentities(print_r($e, true));
		check_and_copy($entry['Driver'], $e['drivername'], "Driver$lochint");
		$dummy = $semikv->timeAsSeconds($e['laptime']);
		check_and_copy($entry['raceBestLapTime'], $dummy, "raceBestLapTime$lochint");
		check_and_copy($entry['raceBestLapNo'], $e['lap'], "raceBestLapNo$lochint");
	}

	if (!$tables['Practice Results']) {
		echo "<p><b>Warning</b>: no qualifying practice results</p>\n";
		$tables['Practice Results'] = array();
	}
	foreach ($tables['Practice Results'] as $e) {
		$slot = array('#'=>$e['startnum']);
		$entry = &lookup_entry($slot, true, true);
		$lochint = " for " . htmlentities(print_r($e, true));
		check_and_copy($entry['Driver'], $e['drivername'], "Driver$lochint");
//TODO: from "table=Session 1 (PRACTICE) Car 16 laptimes" if available: check_and_copy($entry['qualLaps'], $e[''], "qualLaps$lochint");
		$dummy = $semikv->timeAsSeconds($e['laptime']);
		check_and_copy($entry['qualBestLapTime'], $dummy, "qualBestLapTime$lochint");
	}

	return $winnerTime;
}

function htmlCarAsHex($htmlCarCode) {
	return $htmlCarCode ? bin2hex($htmlCarCode) : null;
}

function parse_gpl_html($htmlExport) {
	global $fatal_errors;

	// Remove IE5 stupidity...
	$htmlExport = preg_replace('%\s*<(?:/)?TBODY>\s*%i', '', $htmlExport);
	$htmlExport = preg_replace('%<(?:p\s+|span)[^>]*>%i', '', $htmlExport);
	$htmlExport = preg_replace('%<o:p></o:p>%i', '', $htmlExport);
	$htmlExport = preg_replace('%</(?:span|p)>%i', '', $htmlExport);
	$htmlExport = preg_replace('%</?div[^>]*>%i', '', $htmlExport);

	preg_match("%<H2[^>]*>\\s*(.*?)\\s*<BR>\\s*(?:Novice|Intermediate|Pro|Grand Prix).*?</H2>.*?"
		. "<H3[^>]*>.*?<BR>\\s*(\\d\\d)/(\\d\\d)/(\\d\\d)\\s*</H3>.*?"
		// Practice times
		. "<TABLE[^>]*>\\s*<CAPTION[^>]*>.*?</CAPTION>(.*?)</TABLE>.*?"
		// Grid
		. "<TABLE[^>]*>(.*?)</TABLE>\\s*<BR>\\s*<BR>\\s*"
		// Race results
		. "<TABLE[^>]*>\\s*<CAPTION[^>]*>.*?</CAPTION>\\s*"
		. "<TR[^>]*>\\s*</TR>\\s*<TR[^>]*>\\s*"
		. "<TH[^>]*>\\s*Race Length: (?:(\\d+):)?(\\d{1,2}):(\\d\\d\\.\\d\\d)"
		. "\\s+-\\s+(\\d+)L\\s*</TH>\\s*"
		. "</TR>\\s*<TR[^>]*>\\s*</TR>\\s*"
		// Finishing order and details
		. "((?:<TR[^>]*>\\s*<TD.*?</TR>\\s*)+)"
		// Fastest lap
		. "(?:<TR[^>]*>\\s*</TR>\\s*)*"
		. "<TR[^>]*>\\s*<TH[^>]*>.*</TH>\\s*</TR>\\s*"
		. "<TR[^>]*>\\s*<TD[^>]*>\\s*</TD>\\s*"
		. "<TD[^>]*>\\s*(\\d+)\\s*</TD>\\s*"
		. "<TD[^>]*>\\s*(.*?)\\s*</TD>\\s*"
		. "<TD[^>]*>\\s*(\\S{3})\\s*</TD>\\s*"
		. "<TD[^>]*>\\s*(?:(\\d{1,2}):)?(\\d{1,2}\\.\\d\\d)\\s*</TD>\\s*"
		. "</TR>\\s*</TABLE>%is", $htmlExport, $matches)
		|| die("bad Export format (overall)\n" . htmlentities($htmlExport, ENT_QUOTES));

//echo "<!-- " . htmlentities(print_r($matches, true), ENT_QUOTES) . " -->\n";

	$sub = 1;

	global $location;
	$location = $matches[$sub++];

	is_numeric($whenDay = $matches[$sub++]) || die("bad day '$whenDay'");
	is_numeric($whenMonth = $matches[$sub++]) || die("bad month '$whenMonth'");
	is_numeric($whenYear = $matches[$sub++]) || die("bad year '$whenYear'");

	$pHtml = $matches[$sub++];
	$gHtml = $matches[$sub++];
	$winnerTime = parseGPLTime($matches[$sub++], $matches[$sub++], $matches[$sub++]);
//echo "<PRE>winnerTime $winnerTime</PRE>\n";
	is_numeric($laps = $matches[$sub++]) || die("bad laps '$laps'");
	$rHtml = $matches[$sub++];

	is_numeric($flNumber = $matches[$sub++]) || die("bad fastest lap number '$flNumber'");;
	$flName = $matches[$sub++];
	$flChassis = $matches[$sub++];
	$flTime = parseGPLTime(null, $matches[$sub++], $matches[$sub++]);
//echo "<PRE>flTime $flTime</PRE>\n";

	global $race_start_time;
	if (is_null($race_start_time)) {
	    if ($whenMonth > 12) { // Well this MUST be wrong!
	        $i = $whenMonth;
	        $whenMonth = $whenDay;
	        $whenDay = $i;
	    }
	    $whenYear += $whenYear > 90 ? 1900 : 2000;
		$race_start_time = mktime(23, 59, 59, $whenMonth, $whenDay, $whenYear);
		//echo "<PRE>acquired time from html export: $race_start_time</PRE>";
	}

	// Grid positions.
	// Do this first because ALL drivers are listed here, even ones who didn't qualify or race.

	$grids = preg_match_all("%<TD[^>]*>"
		. "\\s*#(\\d+) \\((\\d+)\\)\\s*" // Number (Position)
		. "<BR>\\s*(.*?)\\s*<BR>" // Driver
		. "\\s*(?:(?:(\\d+):)?(\\d{1,2}\\.\\d\\d)|---)\\s*</TD>%is", $gHtml, $matches, PREG_SET_ORDER);
	($grids > 0 && $grids <= 20) || die("bad grid HTML\n" . htmlentities($gHtml, ENT_QUOTES));

	for ($match = 0; $match < $grids; ++$match) {
		$lochint = " for " . htmlentities($matches[$match][0]);

		$slot = array('Lobby Username'=>$matches[$match][3]);
		is_numeric($slot['#'] = $matches[$match][1]) || die("bad car number '{$slot['#']}'");
		$entry = &lookup_entry($slot, false, true);

		is_numeric($entry['GridPos'] = $matches[$match][2]) || die("bad position '{$entry['GridPos']}'$lochint");
		$entry['qualBestLapTime'] = parseGPLTime(null, $matches[$match][4], $matches[$match][5]);
//echo "<PRE>GRID {$entry['GridPos']} #{$slot['#']} :" . print_r($entry, true) . "</PRE>\n";
	}

	// Next, race positions.

	$races = preg_match_all("%<TR([^>]*)>\\s*"
		. "<TD[^>]*>\\s*(\\d+)\\s*</TD>\\s*" // Position
		. "<TD[^>]*>\\s*(\\d+)\\s*</TD>\\s*" // Number
		. "<TD[^>]*>\\s*(.+?)\\s*</TD>\\s*" // Driver
		. "<TD[^>]*>\\s*(\\S{3})\\s*</TD>\\s*" // Car
		. "<TD[^>]*>\\s*(?:(\\d+\\.\\d) (mph|km/h)|(?:(\\d+):)?(\\d{1,2}\\.\\d\\d)|-(\\d+)L|([A-Za-z](?:\\s*[A-Za-z])*))\\s*</TD>\\s*"
		. "</TR>%is", $rHtml, $matches, PREG_SET_ORDER);
	($races > 0 && $races <= 20) || die("bad race HTML\n" . htmlentities($rHtml, ENT_QUOTES));

	for ($match = 0; $match < $races; ++$match) {
		$lochint = " for " . htmlentities($matches[$match][0]);

		$slot = array('Lobby Username'=>$matches[$match][4], 'VehicleFile'=>$matches[$match][5]);
		is_numeric($slot['#'] = $matches[$match][3]) || die("bad car number '{$slot['#']}'");
		$entry = &lookup_entry($slot, false, true);

		is_numeric($entry['RacePos'] = $matches[$match][2]) || die("bad race position '{$entry['RacePos']}'$lochint");

		($winnerSpeed = $matches[$match][6]) == '' || is_numeric($winnerSpeed) || die("bad winner's speed '$winnerSpeed'");
		if ($matches[$match][7] == "mph") {
			$winnerSpeed *= 1.6093; // Turn it into km/h.
		} else if ($matches[$match][7] == "") {
			$winnerSpeed == '' || die("winner had a speed but no units");
		} else if ($matches[$match][7] != "km/h") {
			die("winner's speed must be mph or km/h but was {$matches[$match][7]}");
		}

		if (is_null($entry['reason'] = translateRetirementReason($matches[$match][11])) && stripos($matches[$match][1], 'red') !== false) {
			$entry['reason'] = -2; // Disco - it's a heuristic though!
		}

		$timeBehind = parseGPLTime(null, $matches[$match][8], $matches[$match][9]);
		($lapsBehind = $matches[$match][10]) == '' || is_numeric($lapsBehind) || die("bad laps behind '$lapsBehind'");

		if ($winnerSpeed != '') {
			($entry['RacePos'] == 1) || die("car {$slot['#']} had a speed but was not the winner!");
			$entry['raceTime'] = $winnerTime;
			$entry['raceLaps'] = $laps;
			global $track_length;
			$track_length = round($winnerTime * ($winnerSpeed * 1000.0 / 3600.0) / $laps, 4);
		} else if ($entry['RacePos'] == 1) {
			die("car {$slot['#']} had no speed but was the winner!");
		} else if (is_numeric($lapsBehind)) {
			$entry['raceLaps'] = $laps - $lapsBehind;
		} else if (is_numeric($timeBehind)) {
			$entry['raceTime'] = $winnerTime + $timeBehind;
			$entry['raceLaps'] = $laps;
		}

		if ($slot['#'] == $flNumber && $entry['LobbyName'] == $flName && $entry['Car']['VehicleFile'] == $flChassis) {
			$entry['raceBestLapTime'] = $flTime;
		}
	}

	// Finally, practice positions.

	$pracs = preg_match_all("%<TR[^>]*>\\s*"
		. "<TD[^>]*>\\s*(\\d+)\\s*</TD>\\s*" // Position
		. "<TD[^>]*>\\s*(\\d+)\\s*</TD>\\s*" // Number
		. "<TD[^>]*>\\s*(.+?)\\s*</TD>\\s*" // Driver
		. "<TD[^>]*>\\s*(\\S{3})\\s*</TD>\\s*" // Car
		. "<TD[^>]*>\\s*(?:(\\d+):)?(\\d{1,2}\\.\\d\\d)\\s*</TD>\\s*" // Time
		. "</TR>%is", $pHtml, $matches, PREG_SET_ORDER);
	($pracs <= 20) || die("bad practice HTML\n" . htmlentities($pHtml, ENT_QUOTES));

	for ($match = 0; $match < $pracs; ++$match) {
		$slot = array('Lobby Username'=>$matches[$match][3], 'VehicleFile'=>$matches[$match][4]);
		is_numeric($slot['#'] = $matches[$match][2]) || die("bad car number '{$slot['#']}'");
		$entry = &lookup_entry($slot, false, true);

		is_numeric($entry['unused_PracticePos'] = $matches[$match][1]) || die("bad qualifying practice position '{$entry['unused_PracticePos']}'$lochint");
		$time = parseGPLTime(null, $matches[$match][5], $matches[$match][6]);
		($entry['qualBestLapTime'] == $time) || die("mismtached grid/practice times {$entry['qualBestLapTime']}/$time{$lochint}"); 
	}

	return $winnerTime;
}

function parseGPLTime($h, $m, $s) {
	if (!is_numeric($s))
		return null;
	$time = $s * 1.0;
	if (is_numeric($m))
		$time += $m * 60;
	if (is_numeric($h))
		$time += $h * 60 * 60;

	return $time;
}

function translateRetirementReason($retirementReason) {
	global $lm2_db_prefix;

	if (is_null($retirementReason) || $retirementReason == '') {
		return null;
	}

	// Reasons which we want to map especially.
	$reasons = array(
		'Accident'=>6,
		'Clutch'=>3,
		'DQ'=>-1,
		'Incident'=>6,
		'No Fuel'=>9,
		'Retired'=>0,
		'Retired(could be DQ)'=>0,
		'Susp'=>4,
	);

	if (array_key_exists($retirementReason, $reasons)) {
		return $reasons[$retirementReason];
	}

	// Otherwise, we are going to look through the existing ones for a match...

	$retirementReason = strtolower($retirementReason);

	$code = null;

	$query = db_query("
		SELECT retirement_reason
		FROM {$lm2_db_prefix}retirement_reasons
		WHERE LOWER(reason_desc) = " . sqlString($retirementReason) . "
		", __FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($query)) {
		is_null($code) || die("ambiguous retirement reason '$retirementReason'");
		$code = $row['retirement_reason'];
	}
	mysql_free_result($query);

	// ... and if we don't find one, add it.

	if (is_null($code)) {
		echo "<P><I>Adding unknown retirement reason '$retirementReason'</I></P>\n";

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
			VALUES ($code, " . sqlString($retirementReason) . ")
			", __FILE__, __LINE__);
	}

	return $code;
}

?>
