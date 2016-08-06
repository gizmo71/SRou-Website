<?php require("../smf/SSI.php"); ?>
<?php
// LM2 RSS feed

is_numeric($team = $_REQUEST['team']) || die("missing or invalid team ID");
if (!($rss_ver = $_REQUEST['rss'])) {
	$rss_ver = "2.0";
} else if ($rss_ver != "0.92" && $rss_ver != "2.0") {
	die("unknown RSS version $rss_ver");
}

require('include.php');

ob_start();

$query = lm2_query("SELECT team_name FROM ${lm2_db_prefix}teams WHERE id_team = $team", __FILE__, __LINE__);
($row = mysql_fetch_assoc($query)) || die("unknown team ID $team");
$team_name = $row['team_name'];
mysql_free_result($query);

$doc = new DOMDocument('1.0', 'iso-8859-1');
$doc->appendChild($rss = $doc->createElement('rss'));
$rss->setAttributeNode(new DOMAttr('version', $rss_ver));

$rss->appendChild($channel = $doc->createElement('channel'));

$top_title = "$team_name at SimRacing.org.uk";
$top_link = "http://www.simracing.org.uk/index.php?ind=lm2&team=$team";
make_text_tag($doc, 'title', $top_title, $channel);
make_text_tag($doc, 'link', $top_link, $channel);
make_cdata_tag($doc, 'description', "Latest $team_name results from SimRacing.org.uk events", $channel);
make_text_tag($doc, 'language', 'en-gb', $channel); // Optional.
make_text_tag($doc, 'copyright', 'Copyright 2006 David P Gymer', $channel); //XXX: proper year
if ((float) $rss_ver >= 2.0) {
	make_text_tag($doc, 'generator', 'LM2i', $channel);
}

// Required for 0.92!
$channel->appendChild($image = $doc->createElement('image'));
make_text_tag($doc, 'url', "http://www.simracing.org.uk/images/flags-22x14/gb.gif", $image);
make_text_tag($doc, 'title', $top_title, $image);
make_text_tag($doc, 'link', $top_link, $image);
make_text_tag($doc, 'width', '22', $image);
make_text_tag($doc, 'height', '14', $image);

//make_text_tag($doc, 'lastBuildDate', 'Tue, 10 Jun 2003 09:41:01 GMT', $channel); // Optional, 0.92 too.
//make_text_tag($doc, 'docs', 'http://www.rssboard.org/rss-specification', $channel);

$desc = "";
$query = lm2_query("SELECT id_event"
	. ", short_desc"
	. ", event_date"
	. ", race_pos"
	. ", race_pos_class"
	. ", brief_name AS circuit_html"
	. ", driver_name"
	. " FROM {$lm2_db_prefix}event_groups"
	. ", {$lm2_db_prefix}events"
	. ", {$lm2_db_prefix}sim_circuits"
	. ", {$lm2_db_prefix}circuits"
	. ", {$lm2_db_prefix}event_entries"
	. ", {$lm2_db_prefix}circuit_locations"
	. ", {$lm2_db_prefix}drivers"
	. " WHERE team = $team"
	. " AND member = driver_member"
	. " AND id_sim_circuit = sim_circuit"
	. " AND id_circuit = {$lm2_db_prefix}sim_circuits.circuit"
	. " AND id_circuit_location = circuit_location"
	. " AND id_event = event"
	. " AND id_event_group = event_group"
	. " ORDER BY event_date DESC, id_event, IFNULL(race_pos, 999)"
	. " LIMIT 20"
	, __FILE__, __LINE__);
$sep = "";
$sep2 = " ";
$event = -1;
while ($row = mysql_fetch_assoc($query)) {
	if ($event != $row['id_event']) {
		$event = $row['id_event'];
		// Consider formatting date without year and only pulling from last 12 months.
		$desc .= "$sep{$row['short_desc']}@" . html_entity_decode($row['circuit_html'], ENT_QUOTES) . ", "
			. format_timestamp(timestamp2php($row['event_date']), true) . ":";
		$sep = "<BR>";
		$sep2 = " ";
	}
	$desc .= "$sep2" . html_entity_decode($row['driver_name'], ENT_QUOTES) . " " . positionify($row['race_pos']);
	if ($row['race_pos'] != $row['race_pos_class']) {
		$desc .= " (" . positionify($row['race_pos_class']) . " in class)";
	}
	$sep2 = ", ";
}
mysql_free_result($query);

if ($desc) {
	make_item("$team_name recent results",
		"http://www.simracing.org.uk/index.php?ind=lm2&team=$team",
		$desc,
		$channel);
}

// Merge this with the modules/lm2/index.php version.
//FIXME: need to use event_group_tree for things like the overall ladder.
$query = lm2_query("SELECT position"
	. ", id_event_group"
	. ", id_championship"
	. ", short_desc AS event_group_desc"
	. ", champ_class_desc"
	. ", class"
	. ", MAX(event_date) AS last_event"
	. " FROM {$lm2_db_prefix}championship_points"
	. ", {$lm2_db_prefix}championships"
	. ", {$lm2_db_prefix}scoring_schemes"
	. ", {$lm2_db_prefix}event_groups"
	. ", {$lm2_db_prefix}events"
	. " WHERE id = $team"
	. " AND id_championship = championship"
	. " AND id_event_group = {$lm2_db_prefix}championships.event_group"
	. " AND id_event_group = {$lm2_db_prefix}events.event_group"
	. " AND id_scoring_scheme = scoring_scheme"
	. " AND champ_type = 'T'"
	. " GROUP BY id_championship"
	. " ORDER BY last_event DESC"
	. " LIMIT 6"
	, __FILE__, __LINE__);
while ($row = mysql_fetch_assoc($query)) {
	add_team_champ_item($channel, $row);
}
mysql_free_result($query);

function add_team_champ_item($channel, $rowC) {
	global $lm2_db_prefix, $team;

	$sep = "";
	$desc = "";
	$query = lm2_query("SELECT position"
		. ", IFNULL(points, 0) AS points"
		. ", team_name"
		. ", id_team"
		. " FROM {$lm2_db_prefix}championship_points"
		. ", {$lm2_db_prefix}teams"
		. " WHERE id = id_team"
		. " AND championship = {$rowC['id_championship']}"
		. " AND ((position BETWEEN {$rowC['position']}-1 AND {$rowC['position']}+1) OR position = 1)"
		. " ORDER BY position"
		, __FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($query)) {
		$name = htmlentities($row['team_name'], ENT_QUOTES);
		if ($team == $row['id_team']) {	
			$name = "<B>$name</B>";
		}
		$desc .= $sep . positionify($row['position']) . " $name {$row['points']}pts";
		$sep = "; ";
	}
	mysql_free_result($query);

	$query = lm2_query("SELECT id_championship"
		. " FROM {$lm2_db_prefix}championships"
		. " WHERE class = " . sqlString($rowC['class'])
		. " AND event_group = {$rowC['id_event_group']}"
		. " AND champ_type = 'D'"
		, __FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($query)) {
		$desc .= add_driver_champ($row['id_championship']);
	}
	mysql_free_result($query);

	make_item("{$rowC['event_group_desc']} {$rowC['champ_class_desc']}",
		"http://www.simracing.org.uk/index.php?ind=lm2&group={$rowC['id_event_group']}#ch{$rowC['id_championship']}",
		$desc, $channel);
}

function add_driver_champ($champ) {
	global $lm2_db_prefix, $team;

	$sep = "<BR>";
	$desc = "";
	$query = lm2_query("SELECT DISTINCT {$lm2_db_prefix}championship_points.position AS position"
		. ", IFNULL({$lm2_db_prefix}championship_points.points, 0) AS points"
		. ", driver_name"
		. " FROM {$lm2_db_prefix}championship_points"
		. ", {$lm2_db_prefix}event_points"
		. ", {$lm2_db_prefix}event_entries"
		. ", {$lm2_db_prefix}drivers"
		. " WHERE team = $team"
		. " AND id_event_entry = event_entry"
		. " AND {$lm2_db_prefix}championship_points.championship = $champ"
		. " AND {$lm2_db_prefix}championship_points.id = member"
		. " AND {$lm2_db_prefix}event_points.championship = $champ"
		. " AND member = driver_member"
		. " ORDER BY position"
		, __FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($query)) {
		$desc .= $sep . positionify($row['position']) . " {$row['driver_name']} {$row['points']}pts";
		$sep = "; ";
	}
	mysql_free_result($query);

	return $desc;
}

// Utility stuff.

function make_item($title, $link, $description, $parent) {
	global $rss_ver, $doc;

	$parent->appendChild($item = $doc->createElement('item'));

	if ((float) $rss_ver >= 2.0) { // Hack for Team Shark - these elements are valid for 0.92 too.
		make_text_tag($doc, 'title', $title, $item);
		make_text_tag($doc, 'link', $link, $item);
	} else {
		$description = "<B>$title</B><BR>$description";
	}
	//make_text_tag($doc, 'guid', "http://www.simracing.org.uk/BlaBlaBla", $item);
	//make_text_tag($doc, 'pubDate', 'Tue, 10 Jun 2003 09:41:01 GMT', $item);
	make_cdata_tag($doc, 'description', $description, $item);
}

function positionify($n) {
	if (is_null($n)) {
		return "Unclassified";
	}

	switch ($n % 100) {
	case 11:
	case 12:
	case 13:
		return "{$n}th";
	}

	switch ($n % 10) {
	case 1:
		return "{$n}st";
	case 2:
		return "{$n}nd";
	case 3:
		return "{$n}rd";
	default:
		return "{$n}th";
	}
}

// All the hard stuff done, bring it on home.

$comments = ob_get_clean();

header('Content-Type: text/xml; charset=iso-8859-1');
echo $doc->saveXML();
echo $comments;
?>