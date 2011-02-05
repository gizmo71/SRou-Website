<?php
require_once("../smf/SSI.php");
require_once("include.php");
$inhibitTimings = true;

srand(time());

$current = null;

//FIXME: can we get session times of day from somewhere so day/night temperatures are sensible?
//FIXME: consider writing the info somewhere as a driver-visible forecast.

if (($location = $_REQUEST['location'])) {
	$query = lm2_query("SELECT latitude_n, longitude_e, wu_station
		FROM {$lm2_db_prefix}circuit_locations
		WHERE id_circuit_location = $location
		" , __FILE__, __LINE__);
	($location_row = mysql_fetch_assoc($query)) || die("can't find location $location");
	mysql_free_result($query);

	$links = lm2MakeWeatherLinks($location_row);

	if ($xml = file_get_contents($links['rssUrl'])) {
		file_put_contents($links['rssFile'], $xml);
	}

	($dom = DOMDocument::load($links['rssFile'])) || die("Error while loading RSS");
	
	$root = $dom->documentElement;
	($root->localName == 'rss') || die("root node is not rss");
	($root->getAttribute('version') == '2.0') || die("only version 2.0 of RSS");
	($channel = get_single_element($root, 'channel')) || die("can't find any channels");
	($items = $channel->getElementsByTagName('item')) || die("can't find any items");

	$current = lm2GetElementText(get_single_element($items->item(0), 'description'));
	$pubDate = strtotime(get_single_element_text($items->item(0), 'pubDate'));
	$forecast1 = get_single_element_text($items->item(1), 'description');
	$forecast2 = get_single_element_text($items->item(2), 'description');

	$current = split_current($current);

	/*XXX: consider setting the min/max based on latitude, and perhaps date and time
	  At the very least adjust live reading to account for local time. */
	$maxInitAmbient = 35;
	$minInitAmbient = 15;

	$InitialConditions = lookup_conditions($current['Conditions']);
	$ambient = max(min(valueWithDefault($current['Temperature'], rand(15, 25)), $maxInitAmbient), $minInitAmbient);
	$gmtOffset = $location_row['longitude_e'] * 86400.0 / 360.0;
	//$gmtHour = print_r(getdate(time()), true);
	$condDesc = '0-61.65 dry, 61.66-99.99 rain';
	echo "<HTML><HEAD><TITLE>SimRacing.org.uk weather.txt generation</TITLE></HEAD>\n"
		. "<BODY><FORM><TABLE>\n"
		. makeDataRow($condDesc, 'InitialConditions', $InitialConditions, 0, 89.99, false)
		. makeDataRow('&deg;C', 'AmbientTemperature', $ambient, max(5, $ambient - 5), min(40, $ambient + 5))
		. "<TR><TD COLSPAN='5'><HR/></TD></TR>"
		. makeDataRow('minutes', 'Practice1', 0, null, null, true)
		. makeDataRow('minutes', 'Practice2', 0, null, null, true)
		. makeDataRow('minutes', 'Qualify1', 0, null, null, true)
		. makeDataRow('minutes', 'Qualify2', 0, null, null, true)
		. makeDataRow('minutes', 'Warmup', 0, null, null, true)
		. makeDataRow('minutes', 'Race', 120)
		. "<TR><TD COLSPAN='5'>Please set the following based on forecast below.</TD></TR>"
		. makeDataRow("0 stable, 20 default, 40 changeable, 1000 wild", 'Changeability', 20)
		. "<TR><TD COLSPAN='5'><HR/></TD></TR>"
		. "<TR><TD COLSPAN='3'><INPUT TYPE='SUBMIT' VALUE='Generate weather.txt'></TD>"
		. "<TD COLSPAN='2'><INPUT TYPE='CHECKBOX' NAME='inBrowser' VALUE='1'> In-browser test</TD></TR>\n"
		. "</TABLE></FORM>"
		. sprintf("<P>%.2f&deg;%s, %.2f&deg;%s, GMT offset %+.1f hours</P>",
			abs($location_row['latitude_n']), $location_row['latitude_n'] > 0 ? 'N' : 'S',
			abs($location_row['longitude_e']), $location_row['longitude_e'] > 0 ? 'E' : 'W',
			$gmtOffset / 3600)
		. sprintf("<P>Feed published %s (%.1f hours ago) at absolute local time %s<BR/>&bull; %s<BR/>&bull; %s</P>\n",
			gmdate("H:i T", $pubDate), (time() - $pubDate) / 3600, gmdate("H:i", $pubDate + $gmtOffset), $forecast1, $forecast2)
		. "<!--\n" . htmlentities($rss, ENT_QUOTES) . "\n-->\n"
		. "</BODY></HTML>\n";
	return;
}

function makeDataRow($notes, $name, $default, $min = null, $max = null, $hide = false) {
	return "<TR" . ($hide ? " STYLE='display: none'" : "") . ">
		<TD>$name</TD><TD><INPUT TYPE='EDIT' SIZE='5' NAME='$name' VALUE='$default'></TD>
		<TD>$notes</TD>
		<TD>" . (is_null($min) ? "" : "Min <INPUT TYPE='EDIT' SIZE='5' NAME='Min$name' VALUE='$min'>") . "</TD>
		<TD>" . (is_null($max) ? "" : "Max <INPUT TYPE='EDIT' SIZE='5' NAME='Max$name' VALUE='$max'>") . "</TD>
		</TR>\n";
}

function split_current($current) {
	$currents = str_replace('Â°', '&#176;', $current); // Upsets the regexps...
	$currents = str_replace('°', '&#176;', $current); // Upsets the regexps...
	$current = array();
	foreach (preg_split('/\s*\|\s*/', $currents, -1, PREG_SPLIT_NO_EMPTY) as $item) {
		preg_match('/^\s*([^:]+)\s*:\s*(.*)\s*$/', $item, $matches) || die("malformed item '$item' from '$currents'");
		$key = $matches[1];
		$value = $matches[2];

		switch ($key) {
		case 'Temperature':
			preg_match('/^-?\d+(?:\.\d+)?&#176;F \/ (-?\d+(?:\.\d+)?)&#176;C$/', $value, $matches) || die("bad $key $value");
			$value = $matches[1];
			break;
		case 'Humidity':
			preg_match('/^(\d+|N\/A)%$/', $value, $matches) || die("bad $key $value");
			$value = $matches[1];
			break;
		case 'Pressure':
			if ($value == 'in / hPa') {
				$value = null;
			} else {
				preg_match('/^(?:-)?\d+(?:\.\d+)?in \/ ((?:-)?\d+(?:\.\d+)?)hPa(?:\s+\((?:Rising|Falling|Steady)\))?$/', $value, $matches) || die("bad $key $value");
				$value = $matches[1];
			}
			break;
		}

		if (!is_null($value)) {
			$current[$key] = $value;
		}
	}
	return $current;
}

function valueWithDefault($value, $default) {
	return (double) (is_null($value) || $value == '' ? $default : $value);
}

function lookup_conditions($conditions, $null_if_missing = false) {
	global $lm2_db_prefix;

	if (is_null($conditions) || $conditions == '' || $conditions == 'Unknown') {
		return 50;
	}

	$conditions = sqlString($conditions);
	
	$query = lm2_query("SELECT condition_gtr AS value FROM {$lm2_db_prefix}wu_conditions WHERE condition_text = $conditions", __FILE__, __LINE__);
	$row = mysql_fetch_assoc($query);
	mysql_free_result($query);

	if ($row) {
		if (!is_null($row['value']))
			return $row['value'];
	} else {
		lm2_query("insert into {$lm2_db_prefix}wu_conditions (condition_text, condition_gtr) values ($conditions, null)", __FILE__, __LINE__);
	}

	if ($null_if_missing) {
		return null;
	}

	die("please <A HREF='/lm2/index.php?action=refdata&refData=wth'>set the condition value</A> for $conditions");
} 

// End of setup stuff, start of actual weather file generation.

$sessionLengths = array(
	"TestDay"=>0,
	"Practice1"=>90,
	"Practice2"=>90,
	"Qualify1"=>45,
	"Qualify2"=>45,
	"Warmup"=>15,
	"Race"=>180,
);
foreach ($sessionLengths as $session=>$lengthDefault) {
	if (!is_null($length = $_REQUEST[$session])) {
		$sessionLengths[$session] = $length;
	}
}

$state = array(
	'conditions' => $_REQUEST['InitialConditions'],
	'ambientTemp' => $_REQUEST['AmbientTemperature'],
	'trackTemp' => null, // Made up from other conditions.
	'onPathWetness' => 0.0,
	'offPathWetness' => 0.0,
	'tempMin' => $_REQUEST['MinAmbientTemperature'],
	'tempMax' => $_REQUEST['MaxAmbientTemperature'],
	'conditionsDelta' => $_REQUEST['Changeability'],
	'minConditions' => $_REQUEST['MinInitialConditions'],
	'maxConditions' => $_REQUEST['MaxInitialConditions'],
);

$state['conditions'] || die("no initial conditions");

function generateSession($name) {
	global $state, $sessionLengths;

	$changesPerSession = 3; // Likely number of changes in a decent length session.

	// Consider using the conditions to drive this too.
	$trackTempOffset = -$state['conditions'] / 10;
	$state['trackTemp'] = $state['ambientTemp'] + rand($trackTempOffset, $trackTempOffset + 10);

	$minutes = $sessionLengths[$name];
	// For the race, add a few minutes to cover the 2 minute grid formation and the pace lap.
	// For other sessions, a shorter amount covers the initial delay and extra time at the end.
	$minute = $name == 'Race' ? 4.5 : 1.5;
	$minutes += $minutes ? $minute : 0;
	printf(" %s\n"
	  	. " {\n"
	  	. "  Conditions=%.2f\n"
	  	. "  OnPathWetness=%.2f\n"
	  	. "  OffPathWetness=%.2f\n"
	  	. "  AmbientTemp=%.2f\n"
	  	. "  TrackTemp=%.2f\n",
	  	$name, $state['conditions'], $state['onPathWetness'], $state['offPathWetness'], $state['ambientTemp'], $state['trackTemp']);
	while ($state['conditionsDelta'] && ($minute += rand(1, max($minutes / $changesPerSession, 15))) < $minutes) {
		changeConditions();
		printf("  Minute=%.2f\n"
		  	. "  {\n"
		  	. "   Conditions=%.2f\n"
		  	. "   AmbientTemp=%.2f\n"
		  	. "  }\n",
		  	$minute, $state['conditions'], $state['ambientTemp']);
	}
	echo " }\n";

	if ($state['conditionsDelta'] && $minutes > 0) {
		changeConditions();
	}

	// 0 dry; 1 damp; 2-75 wet; 76-99 flooded
	$dampnessCutoff = 60.0;
	$state['onPathWetness'] = ($state['conditions'] < $dampnessCutoff
		? 0.0 : 1.0 + ($state['conditions'] - $dampnessCutoff) / (99.99 - $dampnessCutoff) * 98.99);
	$state['offPathWetness'] = ($state['onPathWetness'] == 0 ? 0.0 : $state['onPathWetness'] + rand(0, 25));
}

function changeConditions() {
	global $state;

	$condPre = $state['conditions'];
	slide('conditions', $state['conditionsDelta'], $state['minConditions'], $state['maxConditions']);
	$condPost = $state['conditions'];

	$tempSlide = ($condPre - $condPost) / 5.0;
	$state['tempMin'] += $tempSlide;
	$state['tempMax'] += $tempSlide;

	if ($state['tempMin'] < 5.0) {
		$state['tempMax'] += 5.0 - $state['tempMin'];
		$state['tempMin'] = 5.0;
	}

	slide('ambientTemp', 4, $state['tempMin'], $state['tempMax']); //FIXME: Slide based on conditions...
}

function slide($what, $delta, $min, $max) {
	global $state;
	$minC = $state[$what] - $delta;
	$maxC = $state[$what] + $delta;
	if ($minC < $min) $minC = $min;
	if ($maxC > $max) $maxC = $max;
	$state[$what] = rand($minC, $maxC); //FIXME: exponential or something?
}

ob_start();
echo "Weather\n{\n";
generateSession("TestDay");
generateSession("Practice1");
generateSession("Practice2");
generateSession("Qualify1");
generateSession("Qualify2");
generateSession("Warmup");
generateSession("Race");
echo "}\n";
$content = ob_get_clean();

// Now do what you want with the resulting file?

//($f = fopen("../../downloads/weather.txt", 'wb')) || die("couldn't save file");
//fwrite($f, $content) || die("fwrite failed");
//fclose($f);

header('Content-Type: text/plain');
if ($_REQUEST['inBrowser'] != '1') {
	header('Content-Disposition: attachment; filename="weather.txt"');
}
echo $content;
?>