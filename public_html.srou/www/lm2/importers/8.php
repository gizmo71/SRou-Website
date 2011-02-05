<?php
// GPL importer

function showFileChoosers() {
	global $lm2_db_prefix;
?>
    <TR><TD COLSPAN="4"><I>Please report any problems via <A HREF="/smf/index.php?board=49.0">UKGPL2</A>.</I></TD></TR>
    <TR><TD>Mod/class</TD><TD><SELECT name="mod" onSelect="alert('foo\n' + form.submit_button);">
    	<OPTION VALUE="" SELECTED>Please select a mod...</OPTION>
<?php
	$query = db_query("
		SELECT type, mod_desc
		FROM {$lm2_db_prefix}sim_mods
		WHERE id_sim = 8
		", __FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($query)) {
		print "<OPTION VALUE='${row['type']}'>${row['mod_desc']}</OPTION>\n";
	}
	mysql_free_result($query);
?>
    </SELECT> <SPAN STYLE="color: red">You <B>must</B> select a mod before proceeding!</SPAN></TD></TR>
    <TR><TD>HTML Export</TD><TD><INPUT size="120" name="export" type="file" /></TD></TR>
    <TR><TD>GPLRA Report</TD><TD><INPUT size="120" name="report" type="file" /></TD></TR>
    <TR><TD COLSPAN="4" ALIGN="RIGHT">Please use both files wherever possible.</TD></TR>
<?php
}

function doImport() {
	global $fatal_errors;
	global $race_start_time;

	// Run the list of mods off the database somehow. Classes?
	(is_null($mod = $_REQUEST['mod']) || $mod == '') && die("no mod selected");

	function maybeReadFile($file) {
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
		$winnerTime = parse_gpl_html($htmlExport);
	} else {
		$location = '-';
		$track_length = null;
	}

	$location .= '/';

	if ($gplraReport = maybeReadFile('report')) {
		$winnerTime = parse_gplra($gplraReport);
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
//echo "<PRE>$key = " . print_r($entry, true) . "</PRE>\n";
		}
	}

//	array_push($fatal_errors, "not written yet!");
//foreach (array_unique($fatal_errors) AS $fatal_error) echo "$fatal_error<BR/>\n";
//die("stop! it's not ready yet! location was $location, track length $track_length");
}

function parse_gplra($gplraReport) {
	global $fatal_errors;

	(preg_match_all("%(?:This replay file does not contain practice data\\.\\s+|"
		. "Track: .*?\\s+(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun) \\S{3} \\d{2} \\d{2}:\\d{2}:\\d{2} \\d{4}\\s+"
		. "PRACTICE TIMES\\s+"
		. "Pos\\s+No\\s+Driver\\s+Team\\s+Nat\\s+Time\\s+Diff\\s+Laps\\s+"
		. "(\\d.*?\\S\\s+?)" // The actual practice results rows.
		. "All times are official\\s+Generated with GPL Replay Analyser)\\s+"
		. "Track: (.*?)\\s+((?:Mon|Tue|Wed|Thu|Fri|Sat|Sun) \\S{3} \\d{2} \\d{2}:\\d{2}:\\d{2} \\d{4})\\s+"
		. "RACE RESULTS \\(After (\\d+) laps\\)\\s+"
		. "Pos\\s+No\\s+Driver\\s+Team\\s+Nat\\s+Laps\\s+Race Time\\s+Diff\\s+(?:Problem\\s+)?"
		. "(\\d.*?\\S\\s+?)" // The actual race results rows.
		. "Race results are unofficial\s+\\(Replay might have been saved before end of race\\)\\s+"
		. "RACE FASTEST LAPS\\s+"
		. "Pos\\s+Driver\\s+Time\\s+Lap\\s+"
		. "(\\d.*?\\S\\s+?)" // The actual fastest lap rows.
		. "LEADERS"
		. "%is", $gplraReport, $matches, PREG_SET_ORDER) == 1)
		|| die("bad GPLRA format (overall)\n<PRE>" . htmlentities($gplraReport, ENT_QUOTES) . "</PRE>");

	// Overwrite the date and time if reading the GPLRA as it's more accurate.
	global $race_start_time;
	($ftime = strptime($matches[0][3], "%a %b %d %H:%M:%S %Y")) || die("unrecognised date {$matches[0][3]}");
	$race_start_time = mktime(
		$ftime['tm_hour'], $ftime['tm_min'], $ftime['tm_sec'],
		$ftime['tm_mon'] + 1, $ftime['tm_mday'], $ftime['tm_year'] + 1900);
	//echo "<PRE>acquired time from GPLRA: $race_start_time = " . strftime("%c", $race_start_time) . "</PRE>\n";

	$qualText = $matches[0][1];
	$laps = $matches[0][4];
	$raceText = $matches[0][5];
	$flapsText = $matches[0][6];
	global $location;
	$location .= $matches[0][2];
echo "<PRE>track $location</PRE>";

	$gplraTimeRE = "(?:(?:(?:\\d+h)?\\d+m)?\\d+\\.\\d{3}s)";
	$gplraRE_driver = "(?:\\s\\S|\\S.).{29}";
	$gplraRE_car = "[A-Za-z].{7}";

	// Race results.

	$races = preg_match_all("%"
		. "\\s*(\\d+)\\s+" // Pos
		. "(\\d+) " // No
		. "($gplraRE_driver) " // Driver
		. "($gplraRE_car) " // Car (Team)
		. "(\\S{3})\\s+" // Nationality
		. "(\\d+)\\s+" // Laps
		. "($gplraTimeRE|DidNotStart)\\s+" // Time
		. "(?:$gplraTimeRE|\\d+ lap\\(s\\))?" // Diff
		. "([A-Za-z](?:\\s*[A-Za-z])*)?\\s+" // Problem
		. "%", $raceText, $matches, PREG_SET_ORDER);
	($races > 0 && $races <= 20) || die("bad race report ($races entries)\n" . htmlentities($raceText, ENT_QUOTES));

	$winnerTime = null;
	for ($match = 0; $match < $races; ++$match) {
		$lochint = " for " . htmlentities($matches[$match][0]);

		$slot = array('Driver'=>trim($matches[$match][3]), 'Vehicle'=>trim($matches[$match][4]));
		is_numeric($slot['#'] = $matches[$match][2]) || die("bad car number '{$slot['#']}'$lochint");
		$entry = &lookup_entry($slot, false, true);

//echo "<PRE>HTML {$entry['RacePos']}#{$entry['raceLaps']}/{$entry['raceTime']}@{$entry['reason']}";
		check_and_copy($entry['RacePos'], $matches[$match][1], "RacePos$lochint");
		check_and_copy($entry['raceLaps'], $matches[$match][6], "raceLaps$lochint");
		check_and_copy($entry['raceTime'], ($dummy = parseGPLRATime($matches[$match][7])), "raceTime$lochint");
		check_and_copy($entry['reason'], ($dummy = translateRetirementReason($matches[$match][8])), "reason$lochint");
//echo " GPLRA {$entry['RacePos']}#{$entry['raceLaps']}/{$entry['raceTime']}@{$entry['reason']}  {$matches[$match][3]}</PRE>\n";

		if ($entry['raceLaps'] == 0 && $matches[$match][7] == 'DidNotStart') {
			$entry['raceLaps'] = null;
		}

		if ($entry['RacePos'] == 1) {
			$winnerTime = $entry['raceTime'];
		}
	}

	// Race fastest laps

	$flaps = preg_match_all("%"
		. "(\\d+)\\s+" // Pos
		. "($gplraRE_driver)\\s+" // Driver
		. "($gplraTimeRE|No time)\\s+" // Time
		. "(\\d+)?\\s+" // Lap
		. "%", $flapsText, $matches, PREG_SET_ORDER);
	($flaps > 0 && $flaps <= 20) || die("bad fastest laps report\n" . htmlentities($flapsText, ENT_QUOTES));

	for ($match = 0; $match < $flaps; ++$match) {
		$lochint = " for " . htmlentities($matches[$match][0]);

		// No slot number so we have to match on driver name. Pff.
		if ($bestLapTime = parseGPLRATime($matches[$match][3])) {
			global $entries;
			foreach ($entries AS $key=>&$entry) {
				if ($entry['Driver'] == trim($matches[$match][2]) && $entry['raceLaps'] > 0) {
					check_and_copy($entry['raceBestLapTime'], $bestLapTime, "raceBestLapTime$lochint");
					check_and_copy($entry['raceBestLapNo'], $matches[$match][4], "raceBestLapNo$lochint");
				}
			}
		}
	}

	// Qualifying Practice

	$grids = preg_match_all("%"
		. "\\s*(\\d+)\\s+" // Pos
		. "(\\d+)\\s+" // No
		. "($gplraRE_driver)  " // Driver
		. "($gplraRE_car) " // Car (Team)
		. "(\\S{3})\\s+" // Nationality
		. "($gplraTimeRE| No time)\\s+" // Time
		. "(?:$gplraTimeRE| No time)?\\s+" // Difference
		. "(\\d+)\\s+" // Laps
		. "%", $qualText, $matches, PREG_SET_ORDER);
	($grids <= 20) || die("bad grid report\n" . htmlentities($qualText, ENT_QUOTES));

	for ($match = 0; $match < $grids; ++$match) {
		$lochint = " for " . htmlentities($matches[$match][0]);

		$slot = array('Driver'=>trim($matches[$match][3]), 'Vehicle'=>trim($matches[$match][4]));
		is_numeric($slot['#'] = $matches[$match][2]) || die("bad car number '{$slot['#']}'");
		$entry = &lookup_entry($slot, false, true);

//echo "<PRE>HTML {$entry['qualLaps']}/{$entry['qualBestLapTime']}";
		check_and_copy($entry['qualLaps'], $matches[$match][7], "qualLaps$lochint");
		check_and_copy($entry['qualBestLapTime'], ($dummy = parseGPLRATime($matches[$match][6])), "qualBestLapTime$lochint");
//echo " GPLRA {$entry['qualLaps']}/{$entry['qualBestLapTime']}  {$matches[$match][3]}</PRE>\n";
	}

	return $winnerTime;
}

function parseGPLRATime($t) {
	if (!$t || ($t = trim($t)) == "No time" || $t == "DidNotStart" || substr($t, -8) == " laps(s)") {
		return null;
	}

	$time = 0.0;
	$seconds = 1.0;

	$seps = array('s', 'm', 'h', '?');
	foreach ($seps as $i=>$sep) {
		(substr($t, -1) == $sep) || die("invalid time $t, didn't end with $sep");
		($n = stripos($t, $seps[$i + 1])) === false && ($n = -1);
		$time += substr($t, $n + 1) * $seconds;
		$seconds *= 60.0;
		if ($n == -1)
		    break;
		$t = substr($t, 0, $n + 1);
	}

	return $time;
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