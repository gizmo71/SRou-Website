<?php
require_once("../smf/SSI.php");
require_once("include.php"); // In case we're coming in for a redirect...
if (!is_null($smf_topic = $_REQUEST['smf_topic'])) {
	global $sc, $boardurl;
	$sc || die("no sesc");
	$url = "$boardurl/index.php?action=";
	$extra = "";
	if ($smf_topic == '') {
		(sscanf($_REQUEST['date'], "%d-%d-%d %d:%d:%d", $day, $month, $year, $dummy, $dummy, $dummy) == 6) || die("invalid date");
		$url .= "calendar;sa=post;month=$month;year=$year;day=$day;board=";

		$smf_board = null;
		$query = lm2_query("
			SELECT smf_board
			FROM {$GLOBALS['lm2_db_prefix']}event_boards
			JOIN {$GLOBALS['lm2_db_prefix']}event_group_tree ON contained = {$_REQUEST['group']}
			JOIN {$GLOBALS['lm2_db_prefix']}event_groups ON event_group = container
			ORDER BY depth
			", __FILE__, __LINE__);
		while ($row = mysql_fetch_assoc($query)) {
			$url .= "{$row['smf_board']};";
			break;
		}
		mysql_free_result($query);

		$query = lm2_query("
			SELECT full_desc, short_desc AS name, CONCAT(';theme=',series_theme) AS theme
			FROM {$lm2_db_prefix}event_groups
			WHERE id_event_group = {$_REQUEST['group']}
			", __FILE__, __LINE__);
		($row = mysql_fetch_assoc($query)) || die("group {$_REQUEST['group']} not found");
		$group = $row['name'];
		$groupFull = $row['full_desc'];
		$groupTheme = $row['theme'];
		mysql_fetch_assoc($query) && die("topic {$_REQUEST['group']} found more than once!");
		mysql_free_result($query);

		$query = lm2_query("
			SELECT id_circuit, brief_name AS name
			FROM {$lm2_db_prefix}sim_circuits
			, {$lm2_db_prefix}circuits
			, {$lm2_db_prefix}circuit_locations
			WHERE id_sim_circuit = {$_REQUEST['simcircuit']}
			AND id_circuit = circuit AND id_circuit_location = circuit_location
			" , __FILE__, __LINE__);
		($row = mysql_fetch_assoc($query)) || die("circuit {$_REQUEST['simcircuit']} not found");
		$circuit = $row['name'];
		$id_circuit = $row['id_circuit'];
		mysql_fetch_assoc($query) && die("topic {$_REQUEST['simcircuit']} found more than once!");
		mysql_free_result($query);

//TODO: move this into Post.php...
		$extra .= "/evtitle=" . urlencode("$group $circuit")
			. "/subject=" . urlencode("$groupFull - {$_REQUEST['circuit']} - {$txt['months_short'][$month]} $day")
			. "/message=" . urlencode("COPY THE TEXT IN!"
				. "\nPassword: [iurl=#event_password]see above[/iurl]"
				. "\n(2) Driver lists can be found on the [url=$boardurl/index.php?action=LM2R;group={$_REQUEST['group']}$groupTheme]championship standings page[/url]")
			. "/lm2group={$_REQUEST['group']}"
			. "/lm2circuit=$id_circuit"
			. "/lm2sim={$_REQUEST['sim']}"
			. "/";
	} else {
		$query = lm2_query("SELECT id_event, id_first_msg AS id_msg"
			. " FROM {$db_prefix}topics t, {$db_prefix}calendar c"
			. " WHERE t.id_topic = $smf_topic AND t.id_topic = c.id_topic"
			, __FILE__, __LINE__);
		($row = mysql_fetch_assoc($query)) || die("topic $smf_topic not found");
		$event = $row['id_event'];
		$msg = $row['id_msg'];
		mysql_fetch_assoc($query) && die("topic $smf_topic found more than once!");
		mysql_free_result($query);
		$url .= "post&msg=$msg&topic=$smf_topic.0&calendar&eventid=$event&";
	}
	$url .= "sesc=$sc";

	$url .= $extra;
	header("Location: $url");
	$url = htmlentities($url, ENT_QUOTES);
	//echo "<A HREF='$url'>Click here!</A>";
	exit(0);
}
?>

<SCRIPT LANGUAGE="JavaScript">
function smfTopicLinker(rowNum) {
	var rdForm = document.forms.rdForm;
	var smfTopic = rdForm["smf_topic" + rowNum];
	smfTopic = smfTopic.options[smfTopic.selectedIndex].value;
	var url = 'refdata.php?smf_topic=' + smfTopic;
	var text = '*';
	if (smfTopic == "") {
		var date = rdForm["event_date" + rowNum].value;
		var eventGroup = selectedValue(rdForm["event_group" + rowNum]);
		var eventId = rdForm["id" + rowNum].value;
		var simCircuit = selectedValue(rdForm["sim_circuit" + rowNum]);
		if (date == "" || simCircuit == -1 || eventGroup == -1) {
			url = "javascript:alert('You cannot create or edit an event without a date, group or circuit')";
			text = '&nbsp;';
		} else {
			url += "&type=" + selectedValue(rdForm["event_type" + rowNum])
				+ "&sim=" + selectedValue(rdForm["sim" + rowNum])
				+ "&event=" + eventId
				+ "&group=" + eventGroup
				+ "&simcircuit=" + simCircuit
				+ "&circuit=" + selectedText(rdForm["sim_circuit" + rowNum])
				+ "&date=" + date;
		}
	}
	var a = document.getElementById("smfTopic" + rowNum);
	if (a.href != url) a.href = url;
	if (a.innerHTML != text) a.innerHTML = text;
}

function selectedText(select) {
	return select.options[select.selectedIndex].text;
}
function selectedValue(select) {
	return select.options[select.selectedIndex].value;
}
</SCRIPT>

<SCRIPT LANGUAGE="JavaScript" SRC="calendar1.js"></SCRIPT>
<FORM ENCTYPE="multipart/form-data" METHOD="POST" NAME="rdForm">
<?php
$mkp_db_prefix = 'gizmo71_smf.mkp_';

require("refdata/fields.php");

$addMagic = "{ADD}";

class RefDataFieldID extends RefDataField {
	var $allowDelete = false;

	function RefDataFieldID($name, $allowDelete = false) {
		$this->name = $name;
		$this->allowDelete = is_bool($allowDelete) ? "$allowDelete" : $allowDelete;
	}

	function render($row, $rownum) {
		if (is_null($value = $row[$this->getName()])) {
			global $addMagic;
			$value = $addMagic;
			$type = "CHECKBOX";
			$text = "<SMALL>Add</SMALL>";
		} else {
			$type = "HIDDEN";
			//is_numeric($text = $value) || die('ID field must be numeric; value "$value" is not');
			if (eval("return {$this->allowDelete};")) {
				$text = "<SMALL>Delete</SMALL><INPUT TITLE=\"$value\" NAME=\"delete$rownum\" TYPE=\"CHECKBOX\" VALUE=\"$value\" />";
			} else {
				$text = $value;
			}
		}
		return "<INPUT TITLE=\"$value\" NAME=\"id$rownum\" TYPE=\"$type\" VALUE=\"$value\" />$text";
	}
}

// Shared field definitions.

$classRefDataFieldFK = new RefDataFieldFK("class",
	"SELECT id_class AS id, class_description AS description"
	. " FROM {$lm2_db_prefix}classes"
	. " ORDER BY display_sequence");
$memberRefDataFieldFK = new RefDataFieldFK("member",
	"SELECT driver_member AS id
	, CONCAT(driver_name, IF(driver_member > 10000000, ' (UKGPL historic)', IF(id_member IS NULL AND driver_member <> 10000000, ' (defunct)', IF(driver_member <> $guest_member_id AND memberName <> realName, CONCAT(' (', memberName, ')'), '')))) AS description, 1 AS is_html
	, id_member IS NULL AND driver_member NOT IN (SELECT member FROM {$lm2_db_prefix}sim_drivers UNION SELECT member FROM {$lm2_db_prefix}event_entries) AND driver_member <> 10000000 AS hide
	FROM {$lm2_db_prefix}drivers
	LEFT JOIN {$db_prefix}members ON id_member = driver_member
	ORDER BY driver_member >= 10000000, description", false, '12em');
$simRefDataFieldFK = new RefDataFieldFK("sim",
	"SELECT id_sim AS id, sim_name_short AS description"
	. " FROM {$lm2_db_prefix}sims"
	. " ORDER BY description");
$simRefDataFieldFKReadOnly = new RefDataFieldFK("sim",
	"SELECT id_sim AS id, sim_name AS description, 1 AS hide"
	. " FROM {$lm2_db_prefix}sims"
	. " ORDER BY description");
$eventTypeRefDataFieldFK = new RefDataFieldFK("event_type", array('C'=>'Champ', 'N'=>'Non-Ch', 'F'=>'Fun'), false);

function eventGroupRefDataFieldFKsql($hidePredicate = "is_protected = 1") {
	global $lm2_db_prefix;
	return "SELECT id_event_group AS id
		, short_desc AS description
		, ($hidePredicate) AS hide
		FROM {$lm2_db_prefix}event_groups
		ORDER BY short_desc";
}

function moderatorRefDataFieldFKSQL(/*$group...*/) {
	func_num_args() || die("moderatorRefDataFieldFKSQL must be called with a list of membergroup IDs");

	global $db_prefix;
	$groups = func_get_args();

	return "
		SELECT id_member AS id, realName AS description, 1 AS is_html
		FROM {$db_prefix}members
		WHERE CONCAT(',', id_group, ',', additionalGroups, ',') REGEXP ',(" . implode("|", $groups) . "),'
		ORDER BY description
	";
}

$carRefDataFieldFKSQL = "SELECT id_car AS id, CONCAT(manuf_name, ' ', car_name) AS description
	FROM {$lm2_db_prefix}manufacturers
	JOIN {$lm2_db_prefix}cars ON id_manuf = manuf
	ORDER BY description";

// Base class for all editable reference data tables.

class RefData {
	var $lm2_db_prefix;
	var $db_prefix;

	function RefData() {
		$this->lm2_db_prefix = $GLOBALS['lm2_db_prefix'];
		$this->db_prefix = $GLOBALS['db_prefix'];
	}

	function getName() { die("abstract getName"); }
	function getTable() { die("abstract getTable"); }
	function getFields() { die("abstract getFields"); }
	// Null if rows can't be added or the fake row values if it can.
	function addRow() { return null; }

	function getFilters() {
		return array(''=>array(name=>'None', predicate=>'1'));
	}

	function getDefaultSortOrder() {
		return "U0";
	}

	function rebuild() { }

	function show_notes() { }

	function makeSql($what, $from, $where) {
		return "SELECT $what FROM $from WHERE $where";
	}
}

// The actual reference tables.

class Cars extends RefData {
	function getName() { return "Cars"; }
	function getTable() { return "{$this->lm2_db_prefix}cars"; }

	function getFields() {
		return Array(
			new RefDataFieldID("id_car", '$row["entries"] == 0'),
			new RefDataFieldFK("manuf",
				"SELECT id_manuf AS id, manuf_name AS description"
				. " FROM {$this->lm2_db_prefix}manufacturers"
				. " ORDER BY description"),
			new RefDataFieldEdit("car_name", 70),
		);
	}

	function addRow() {
		global $filterId;
		$defaultManuf = substr($filterId, 0, 1) == 'm' ? substr($filterId, 1) : -1;
		return array(manuf=>$defaultManuf);
	}

	function getFilters() {
		$filters = array('m'=>array('name'=>'Manufacturers', 'nested'=>array()));

		$query = lm2_query("
			SELECT DISTINCT id_manuf, manuf_name
			FROM {$this->lm2_db_prefix}manufacturers
			ORDER BY manuf_name
			", __FILE__, __LINE__);
		while ($row = mysql_fetch_assoc($query)) {
			$filters['m']['nested']["m{$row['id_manuf']}"] = array(name=>$row['manuf_name'], predicate=>"manuf = " . sqlString($row['id_manuf']));
		}
		mysql_free_result($query);

		return $filters;
	}

	function getDefaultSortOrder() {
		return "U2";
	}

	function makeSql($what, $from, $where) {
		return "
			SELECT COUNT(rated_car) + COUNT(car) AS entries, $what FROM ($from)
			LEFT JOIN {$this->lm2_db_prefix}sim_cars ON id_car = car
			LEFT JOIN {$this->lm2_db_prefix}car_ratings ON id_car = rated_car
			WHERE $where
			GROUP BY id_car";
	}
}

class Manufacturers extends RefData {
	function getName() { return "Manufacturers"; }
	function getTable() { return "{$this->lm2_db_prefix}manufacturers"; }

	function getFields() {
		return Array(
			new RefDataFieldID("id_manuf", '$row["entries"] == 0'),
			new RefDataFieldEdit("manuf_name", 20),
			new RefDataFieldEdit("manuf_image", 25),
			new RefDataFieldEdit("manuf_width", 6),
			new RefDataFieldEdit("manuf_height", 6),
			new RefDataFieldEdit("manuf_bgcolor", 6),
			new RefDataFieldEdit("manuf_url", 60),
		);
	}

	function addRow() {
		return array();
	}

	function getDefaultSortOrder() {
		return "U1";
	}

	function makeSql($what, $from, $where) {
		return "
			SELECT COUNT(manuf) AS entries, $what FROM ($from)
			LEFT JOIN {$this->lm2_db_prefix}cars ON id_manuf = manuf
			WHERE $where
			GROUP BY id_manuf";
	}
}

class Tyres extends RefData {
	function getName() { return "Tyres"; }
	function getTable() { return "{$this->lm2_db_prefix}tyres"; }

	function getFields() {
		return Array(
			new RefDataFieldID("id_tyre", '$row["entries"] == 0'),
			new RefDataFieldEdit("id_tyre", 1),
			new RefDataFieldEdit("tyre_description", 15),
			new RefDataFieldEdit("width", 6),
			new RefDataFieldEdit("height", 6),
			new RefDataFieldEdit("bgcolor", 6),
			new RefDataFieldEdit("url", 60),
		);
	}

	function addRow() {
		return array();
	}

	function getDefaultSortOrder() {
		return "U2";
	}

	function makeSql($what, $from, $where) {
		return "
			SELECT COUNT(tyres) AS entries, $what FROM ($from)
			LEFT JOIN {$this->lm2_db_prefix}sim_cars ON id_tyre = tyres
			WHERE $where
			GROUP BY id_tyre";
	}
}

class SimCars extends RefData {
	function getName() { return "SimCars"; }
	function getTable() { return "{$this->lm2_db_prefix}sim_cars"; }

	function getFields() {
		return Array(
			new RefDataFieldID("id_sim_car", '$row["entries"] == 0'),
			$GLOBALS['simRefDataFieldFKReadOnly'],
			new RefDataFieldReadOnly("vehicle"),
			new RefDataFieldReadOnly("team"),
			new RefDataFieldReadOnly("number"),
			new RefDataFieldReadOnly("file"),
			new RefDataFieldReadOnly("type"),
			new RefDataFieldUpgradeCodeReadOnly("upgrade_code"),
			new RefDataFieldFK("car", $GLOBALS['carRefDataFieldFKSQL']),
			new RefDataFieldFK("tyres",
				"SELECT id_tyre AS id, tyre_description AS description"
				. " FROM {$this->lm2_db_prefix}tyres"
				. " ORDER BY tyre_description"),
			new RefDataFieldEdit("notes", 80),
		);
	}

	function getFilters() {
		$filters = array(
			'u'=>array('name'=>'Unmapped', 'predicate'=>"car = -1 OR sim = -1 OR tyres = '-'"),
			's'=>array('name'=>'Sim', 'nested'=>array()),
			'c'=>array('name'=>'Car', 'nested'=>array()),
			'o'=>array('name'=>'Sim/Mod', 'nested'=>array()),
		);

		$query = lm2_query("
			SELECT id_sim AS id, sim_name AS description
			FROM {$this->lm2_db_prefix}sims
			WHERE id_sim <> -1
			", __FILE__, __LINE__);
		while ($row = mysql_fetch_assoc($query)) {
			$filters['s']['nested']["s{$row['id']}"] = array('name'=>$row['description'], 'predicate'=>"sim = " . sqlString($row['id']));
		}
		mysql_free_result($query);

		$combo = "CONCAT(sim, '/', IFNULL(type, 'null'))";
		$query = lm2_query("
			SELECT DISTINCT $combo AS id, CONCAT(sim_name, ' ', IFNULL(type, ' ')) AS description
			FROM {$this->lm2_db_prefix}sim_cars
			JOIN {$this->lm2_db_prefix}sims ON id_sim = sim
			WHERE sim <> -1
			ORDER BY description
			", __FILE__, __LINE__);
		while ($row = mysql_fetch_assoc($query)) {
			$filters['o']['nested']["o{$row['id']}"] = array('name'=>$row['description'], 'predicate'=>"$combo = " . sqlString($row['id']));
		}
		mysql_free_result($query);

		$query = lm2_query($GLOBALS['carRefDataFieldFKSQL'], __FILE__, __LINE__);
		while ($row = mysql_fetch_assoc($query)) {
			$filters['c']['nested']["c{$row['id']}"] = array(name=>$row['description'], predicate=>"car = " . sqlString($row['id']));
		}
		mysql_free_result($query);

		return $filters;
	}

	function makeSql($what, $from, $where) {
		return "SELECT COUNT(sim_car) AS entries, $what FROM ($from)
			LEFT JOIN {$this->lm2_db_prefix}cars ON id_car = car
			LEFT JOIN {$this->lm2_db_prefix}event_entries ON id_sim_car = sim_car
			WHERE $where GROUP BY id_sim_car";
	}

	function getDefaultSortOrder() {
		return "U8";
	}
}

class Classification extends RefData {
	function getName() { return "Classification"; }
	function getTable() { return "{$this->lm2_db_prefix}car_classification"; }

	function getFields() {
		return Array(
			new RefDataFieldID("id_car_classification", true),
			new RefDataFieldFK("event_group", eventGroupRefDataFieldFKsql("is_protected = 1 AND parent IS NOT NULL")),
			new RefDataFieldFK("car", $GLOBALS['carRefDataFieldFKSQL']),
			new RefDataFieldFK("car_class", "
				SELECT id_class AS id, class_description AS description, display_sequence < 0 AS hide
				FROM {$this->lm2_db_prefix}classes
				ORDER BY display_sequence"),
		);
	}

	function addRow() {
		global $filterId;
		return array(
			event_group=>substr($filterId, 0, 1) == 'g' ? substr($filterId, 1) : -1,
			car=>substr($filterId, 0, 1) == 'c' ? substr($filterId, 1) : -1,
			car_class=>substr($filterId, 0, 1) == 'l' ? substr($filterId, 1) : '-'
		);
	}

	function getDefaultSortOrder() {
		return "U1";
	}

	function getFilters() {
		$filters = array(
			'l'=>array('name'=>'Class', 'nested'=>array()),
			'g'=>array('name'=>'Group', 'nested'=>array()),
			'c'=>array('name'=>'Car', 'nested'=>array())
		);

		$query = lm2_query("
			SELECT DISTINCT id_class AS id, class_description AS description
			FROM {$this->lm2_db_prefix}classes
			JOIN {$this->lm2_db_prefix}car_classification ON id_class = car_class
			ORDER BY display_sequence
			", __FILE__, __LINE__);
		while ($row = mysql_fetch_assoc($query)) {
			$filters['l']['nested']["l{$row['id']}"] = array(name=>$row['description'], predicate=>"car_class = " . sqlString($row['id']));
		}
		mysql_free_result($query);

		$query = lm2_query("
			SELECT DISTINCT id_event_group AS id, short_desc AS description
			FROM {$this->lm2_db_prefix}event_groups
			WHERE id_event_group IN (SELECT event_group FROM {$this->lm2_db_prefix}car_classification) OR NOT is_protected
			ORDER BY description", __FILE__, __LINE__);
		while ($row = mysql_fetch_assoc($query)) {
			$filters['g']['nested']["g{$row['id']}"] = array(name=>$row['description'], predicate=>sprintf("event_group = %d", $row['id']));
		}
		mysql_free_result($query);

		$query = lm2_query($GLOBALS['carRefDataFieldFKSQL'], __FILE__, __LINE__);
		while ($row = mysql_fetch_assoc($query)) {
			$filters['c']['nested']["c{$row['id']}"] = array(name=>$row['description'], predicate=>"car = " . sqlString($row['id']));
		}
		mysql_free_result($query);

		return $filters;
	}

	function show_notes() {
		global $lm2_view_prefix;
		$query = lm2_query("
			SELECT car_class_c, car_class, eg_c.short_desc AS c, eg_e.short_desc AS e
			, GROUP_CONCAT(DISTINCT CONCAT(manuf_name, ' ', car_name)) AS car
			FROM {$lm2_view_prefix}lm2_classifications
			JOIN {$this->lm2_db_prefix}event_entries USING (id_event_entry)
			JOIN {$this->lm2_db_prefix}events ON id_event = event
			JOIN {$this->lm2_db_prefix}event_groups eg_e ON id_event_group = event_group
			JOIN {$this->lm2_db_prefix}sim_cars ON id_sim_car = sim_car
			JOIN {$this->lm2_db_prefix}cars ON id_car = {$this->lm2_db_prefix}sim_cars.car
			JOIN {$this->lm2_db_prefix}manufacturers ON id_manuf = manuf
			LEFT JOIN {$this->lm2_db_prefix}car_classification USING (id_car_classification)
			LEFT JOIN {$this->lm2_db_prefix}event_groups eg_c ON eg_c.id_event_group = {$this->lm2_db_prefix}car_classification.event_group
			WHERE car_class_c <> IFNULL(car_class, '-')
			GROUP BY car_class_c, car_class, eg_c.short_desc, eg_e.short_desc
			", __FILE__, __LINE__);
		while ($row = mysql_fetch_assoc($query)) {
			echo "<BR/>Mismatch? " . htmlentities(print_r($row, true), ENT_QUOTES) . "\n";
		}
		mysql_free_result($query);
	}
}

class Classes extends RefData {
	function getName() { return "Classes"; }
	function getTable() { return "{$this->lm2_db_prefix}classes"; }

	function getFields() {
		return Array(
			new RefDataFieldID("id_class", '$row["entries"] == 0'),
			new RefDataFieldEdit("id_class", 10),
			new RefDataFieldEdit("class_description", 15),
			new RefDataFieldEdit("display_sequence", 2),
			new RefDataFieldEdit("class_bgcolor", 6),
			new RefDataFieldFK("class_ballast_scheme", "
				SELECT ballast_scheme AS id, GROUP_CONCAT(ballast_delta ORDER BY ballast_position, ',') AS description
				FROM {$this->lm2_db_prefix}ballast_schemes
				GROUP BY ballast_scheme", true, 100),
			//TODO: numeric type for these two...
			new RefDataFieldEdit("class_min_ballast", 4),
			new RefDataFieldEdit("class_max_ballast", 4),
		);
	}

	function addRow() {
		return array();
	}

	function getDefaultSortOrder() {
		return "U3";
	}

	function makeSql($what, $from, $where) {
		return "
			SELECT COUNT(id_car_classification) + COUNT(id_championship) AS entries, $what FROM ($from)
			LEFT JOIN {$this->lm2_db_prefix}car_classification ON id_class = car_class
			LEFT JOIN {$this->lm2_db_prefix}championships ON id_class REGEXP CONCAT('^(',class,')\$') AND NOT 'utter bogosity catch all test' REGEXP CONCAT('^(',class,')\$')
			WHERE $where
			GROUP BY id_class";
	}
}

class SimCircuits extends RefData {
	function getName() { return "SimCircuits"; }
	function getTable() { return "{$this->lm2_db_prefix}sim_circuits"; }

	function getFields() {
		return Array(
			new RefDataFieldID("id_sim_circuit", true),
			new RefDataFieldFK("circuit", "SELECT id_circuit AS id, CONCAT(brief_name, IFNULL(CONCAT(' (', layout_name, ')'), '')) AS description"
				. " FROM {$this->lm2_db_prefix}circuits, {$this->lm2_db_prefix}circuit_locations"
				. " WHERE id_circuit_location = circuit_location"
				. " ORDER BY description"),
			$GLOBALS['simRefDataFieldFKReadOnly'],
			new RefDataFieldEdit("sim_name", 30, 100),
			new RefDataFieldEdit("iracing_track_id", 6), //TODO: INT(6)
			new RefDataFieldEdit("length_metres", 10), //TODO: DECIMAL(9,4)
		);
	}

	function addRow() {
		return array(sim=>-1, circuit=>-1);
	}

	function getFilters() {
		$filters = array(
			'n'=>array('name'=>'NoMatch', 'predicate'=>'0'),
			''=>array('name'=>'None', 'predicate'=>'1'),
			's'=>array('name'=>'Sim', 'nested'=>array()),
			'l'=>array('name'=>'Location', 'nested'=>array()),
			'i'=>array('name'=>'iRacing no data', 'predicate'=>'sim = 9 AND (length_metres IS NULL OR iracing_track_id IS NULL)'),
		);

		$query = lm2_query("SELECT id_sim AS id, sim_name AS description"
			. " FROM {$this->lm2_db_prefix}sims", __FILE__, __LINE__);
		while ($row = mysql_fetch_assoc($query)) {
			$filters['s']['nested']["s{$row['id']}"] = array('name'=>$row['description'], 'predicate'=>"sim = " . sqlString($row['id']));
		}
		mysql_free_result($query);

		$query = db_query("
			SELECT id_circuit_location AS id, CONCAT(brief_name, ' (', iso3166_code, ')') AS description
			FROM {$this->lm2_db_prefix}circuit_locations
			ORDER BY description
			", __FILE__, __LINE__);
		while ($row = mysql_fetch_assoc($query)) {
			$filters['l']['nested']["l{$row['id']}"] = array(name=>$row['description'],
				predicate=>sprintf("circuit IN (SELECT id_circuit FROM {$this->lm2_db_prefix}circuits WHERE circuit_location = %s)", sqlString($row['id'])));
		}
		mysql_free_result($query);

		return $filters;
	}

	function getDefaultSortOrder() {
		return "U1";
	}
}

class CircuitRefData extends RefData {
	function rebuild() {
		echo "</BR>Adding any missing generic Circuits &amp; SimCircuits...\n";

		lm2_query("INSERT INTO {$this->lm2_db_prefix}circuits"
			. "(circuit_location)"
			. "SELECT id_circuit_location"
			. " FROM {$this->lm2_db_prefix}circuit_locations"
			. " LEFT JOIN {$this->lm2_db_prefix}circuits ON id_circuit_location = circuit_location"
			. " WHERE id_circuit IS NULL"
			, __FILE__, __LINE__);

		lm2_query("INSERT INTO {$this->lm2_db_prefix}sim_circuits"
			. "(circuit)"
			. "SELECT id_circuit"
			. " FROM {$this->lm2_db_prefix}circuits"
			. " LEFT JOIN {$this->lm2_db_prefix}sim_circuits ON id_circuit = circuit AND sim = -1"
			. " WHERE id_sim_circuit IS NULL"
			, __FILE__, __LINE__);
	}
}

class Circuits extends CircuitRefData {
	function getName() { return "Circuits"; }
	function getTable() { return "{$this->lm2_db_prefix}circuits"; }

	function getFields() {
		return Array(
			new RefDataFieldID("id_circuit", '$row["entries"] == 0'),
			new RefDataFieldFK("circuit_location", "SELECT id_circuit_location AS id, brief_name AS description"
				. " FROM {$this->lm2_db_prefix}circuit_locations"
				. " ORDER BY description"),
			new RefDataFieldEdit("layout_name", 30),
			new RefDataFieldEdit("layout_notes", 64),
		);
	}

	function addRow() {
		global $filterId;
		$defaultLocation = substr($filterId, 0, 1) == 'l' ? substr($filterId, 1) : -1;
		return array('circuit_location'=>-1, 'circuit_location'=>$defaultLocation);
	}

	function getDefaultSortOrder() {
		return "U1";
	}

	function getFilters() {
		$filters = array(
			'n'=>array('name'=>'NoMatch', 'predicate'=>'0'),
			''=>array('name'=>'None', 'predicate'=>'1'),
			'l'=>array('name'=>'Location', 'nested'=>array()),
		);

		$query = db_query("
			SELECT id_circuit_location AS id, CONCAT(brief_name, ' (', iso3166_code, ')') AS description
			FROM {$this->lm2_db_prefix}circuit_locations
			ORDER BY description
			", __FILE__, __LINE__);
		while ($row = mysql_fetch_assoc($query)) {
			$filters['l']['nested']["l{$row['id']}"] = array(name=>$row['description'], predicate=>"circuit_location = " . sqlString($row['id']));
		}
		mysql_free_result($query);

		return $filters;
	}

	function makeSql($what, $from, $where) {
		return "SELECT COUNT(id_event) AS entries, $what FROM ($from) LEFT JOIN {$this->lm2_db_prefix}sim_circuits ON id_circuit = circuit LEFT JOIN {$this->lm2_db_prefix}events ON id_sim_circuit = sim_circuit WHERE $where GROUP BY id_circuit";
	}
}

class Locations extends CircuitRefData {
	function getName() { return "Locations"; }
	function getTable() { return "{$this->lm2_db_prefix}circuit_locations"; }

	function getFields() {
		return Array(
			new RefDataFieldID("id_circuit_location", true),
			new RefDataFieldEdit("brief_name", 16),
			new RefDataFieldEdit("full_name", 64),
			new RefDataFieldFK("is_fantasy", array(0=>'No', 1=>'Yes'), false),
			new RefDataFieldFK("iso3166_code", "SELECT id_iso3166 AS id, iso3166_name AS description"
				. " FROM {$this->lm2_db_prefix}iso3166"
				. " ORDER BY description"),
			new RefDataFieldEdit("latitude_n", 10), //FIXME: need a numeric field type - DOUBLE in this case.
			new RefDataFieldEdit("longitude_e", 10), //FIXME: need a numeric field type - DOUBLE in this case.
			new RefDataFieldEdit("wu_station", 25),
			new RefDataFieldEdit("location_url", 80),
		);
	}

	function addRow() {
		global $filterId;
		$defaultIso3166 = substr($filterId, 0, 1) == 'c' ? substr($filterId, 1) : 'XX';
		return array('iso3166_code'=>$defaultIso3166);
	}

	function getDefaultSortOrder() {
		return "U1";
	}

	function getFilters() {
		$filters = array(
			'nwx'=>array(name=>'No Weather', predicate=>'wu_station IS NULL'),
			'f'=>array(name=>'Fantasy', predicate=>'is_fantasy'),
		);

		$query = db_query("
			SELECT DISTINCT id_iso3166 AS id, iso3166_name AS description
			FROM {$this->lm2_db_prefix}iso3166
			ORDER BY description
			", __FILE__, __LINE__);
		while ($row = mysql_fetch_assoc($query)) {
			$filters['c']['nested']["c{$row['id']}"] = array(name=>$row['description'], predicate=>"iso3166_code = " . sqlString($row['id']));
		}
		mysql_free_result($query);

		$filters[''] = array(name=>'None', predicate=>'1');
		return $filters;
	}

	function show_notes() {
		echo "<P><A HREF='http://en.wikipedia.org/wiki/List_of_auto_racing_tracks#Argentina'>Motor Racing Circuits</A> "
			. "| <A HREF='http://en.wikipedia.org/wiki/List_of_Formula_One_circuits#A'>F1 Circuits</A></P>\n";
	}
}

class Countries extends RefData {
	function getName() { return "Countries"; }
	function getTable() { return "{$this->lm2_db_prefix}iso3166"; }

	function getFields() {
		return Array(
			new RefDataFieldID("id_iso3166", true),
			new RefDataFieldEdit("id_iso3166", 2),
			new RefDataFieldEdit("iso3166_name", 100),
		);
	}

	function addRow() {
		return array();
	}

	function show_notes() {
		echo "<P><A HREF='http://en.wikipedia.org/wiki/ISO_3166-1_alpha-2#Officially_assigned_code_elements'>ISO3166-1 alpha-2</A></P>\n";
	}

	function getDefaultSortOrder() {
		return "U2";
	}
}

class RefDataFieldFKPollChoice extends RefDataFieldFK {
	function RefDataFieldFKPollChoice($name) {
		$this->name = $name;
		$this->width = "10em";
		array_push($this->map, array(id=>null, description=>""));
	}

	function useJS() {
		return false;
	}

	function getMap($row) {
		static $champMaps = array();

		$champ = $row['champ_group_champ'];
		$map = $champMaps[$champ];

		if (is_null($map)) {
			$map = $this->map;
			if (!is_null($champ)) {
				$query = lm2_query("
					SELECT DISTINCT id_choice AS id, label AS description, 1 AS is_html
					FROM {$GLOBALS['lm2_db_prefix']}championships
					JOIN {$GLOBALS['lm2_db_prefix']}event_group_tree ON contained = event_group
					JOIN {$GLOBALS['lm2_db_prefix']}event_groups ON id_event_group = container
					JOIN {$GLOBALS['db_prefix']}topics ON reg_topic = id_topic
					JOIN {$GLOBALS['db_prefix']}poll_choices ON {$GLOBALS['db_prefix']}poll_choices.id_poll = {$GLOBALS['db_prefix']}topics.id_poll
					WHERE id_championship = $champ
					ORDER BY description
					", __FILE__, __LINE__);
				while ($row = mysql_fetch_assoc($query)) {
					array_push($map, $row);
				}
				mysql_free_result($query);
			}

			$champMaps[$champ] = $map;
		}

		return $map;
	}
}

class Championships extends RefData {
	function getName() { return "Championships"; }
	function getTable() { return "{$this->lm2_db_prefix}championships"; }

	function getFields() {
		$membergroup_fk_sql = "SELECT id_group AS id, groupName AS description"
			. " FROM {$GLOBALS['db_prefix']}membergroups"
			. " WHERE minPosts = -1"
			. " ORDER BY description";

		return Array(
			new RefDataFieldID("id_championship", true),
			new RefDataFieldFK("scoring_scheme", scoringSchemeRefDataFieldFKSQL("1"), false, "12em"),
			new RefDataFieldEdit("class", 10),
			new RefDataFieldFK("reg_class_regexp", "
				SELECT class_regexp AS id, CONCAT('^(', class_regexp, ')\$ - ', description) AS description
				FROM {$GLOBALS['lm2_db_prefix']}reg_classes
				WHERE class_regexp IS NOT NULL
				ORDER BY description
			", true, "6em"), 
			new RefDataFieldEdit("champ_class_desc", 15),
			new RefDataFieldEdit("champ_sequence", 2),
			new RefDataFieldFK("event_group", eventGroupRefDataFieldFKsql()),
			new RefDataFieldFK("champ_type", array('D'=>'Drivers', 'T'=>'Teams', 'M'=>'Manufacturers'), false),
			new RefDataFieldFK("champ_master", "
				SELECT id_championship AS id, CONCAT(short_desc, '/', champ_type) AS description, is_protected OR champ_type <> 'D' AS hide
				FROM {$GLOBALS['lm2_db_prefix']}championships
				JOIN {$GLOBALS['lm2_db_prefix']}event_groups ON id_event_group = event_group
				ORDER BY description
			", true, "7em"),
			new RefDataFieldFK("slave_to_race_pos", array('0'=>'No', '1'=>'Yes'), false),
			new RefDataFieldEdit("max_rank", 2),
			new RefDataFieldEdit("best", 2),
			new RefDataFieldEdit("rounds", 2),
			new RefDataFieldEdit("ballast_bonus", 5), //FIXME: need a numeric field type - DECIMAL(4,3) in this case.
			new RefDataFieldFK("tie_break_mode", array('S'=>'SRou', 'U'=>'UKGPL', 'T'=>'UKGPL Teams'), false),
		);
	}

	function addRow() {
		global $filterId;
		$eventGroup = substr($filterId, 0, 1) == 'g' ? substr($filterId, 1) : -1;
		return array('scoring_scheme'=>-1, 'tie_break_mode'=>'S', 'event_group'=>$eventGroup, 'slave_to_race_pos'=>'0');
	}

	function getFilters() {
		$filters = array(
			"unprot"=>array('name'=>'Unprotected', 'predicate'=>"event_group IN (SELECT id_event_group FROM {$this->lm2_db_prefix}event_groups WHERE is_protected <> 1)"),
			""=>array('name'=>'None', 'predicate'=>'1'),
			"g"=>array('name'=>'Group', 'nested'=>array()),
		);

		$query = lm2_query("
			SELECT id_event_group AS id, short_desc AS description
			FROM {$this->lm2_db_prefix}event_groups
			ORDER BY is_protected < 2, is_protected > 0, description
			" , __FILE__, __LINE__);
		while ($row = mysql_fetch_assoc($query)) {
			$filters['g']['nested']["g{$row['id']}"] = array('name'=>$row['description'], 'predicate'=>"event_group = {$row['id']}");
		}
		mysql_free_result($query);

		return $filters;
	}

	function getDefaultSortOrder() {
		return "U5";
	}
}

class ChampGroups extends RefData {
	function getName() { return "ChampGroups"; }
	function getTable() { return "{$this->lm2_db_prefix}champ_groups"; }

	function getFields() {
		return Array(
			new RefDataFieldID("id_champ_group", true),
			new RefDataFieldFK("champ_group_champ", "
				SELECT DISTINCT id_championship AS id, CONCAT(short_desc,' ',champ_class_desc) AS description
				FROM {$this->lm2_db_prefix}championships, {$this->lm2_db_prefix}event_groups
				WHERE event_group = id_event_group
				AND is_protected <> 1
				AND champ_type = 'D'
				ORDER BY short_desc, champ_sequence
				", true),
			new RefDataFieldFK("champ_group_membergroup", "SELECT id_group AS id, groupName AS description
				FROM {$GLOBALS['db_prefix']}membergroups
				ORDER BY minPosts, description
				", false),
			new RefDataFieldEdit("champ_group_type", 1),
			new RefDataFieldFKPollChoice("champ_group_poll_choice"),
		);
	}

	function addRow() {
		return array(champ_group_type=>'F', champ_group_membergroup=>null);
	}

	function show_notes() {
		echo "<P>Types: <B>F</B>ull time,
			<B>1</B>st reserve (if set, driver must be in one of these groups),
			<B>2</B>nd/<B>3</B>rd reserve,
			<B>L</B>icensed (if set, driver must be in this group)
			<BR/>
			Leave the champ group poll choice blank to set a default for unregistered drivers. In such cases the membergroup is ignored.</P>";
	}

	function getDefaultSortOrder() {
		return "U1";
	}
}

class RefDataFieldFKSmfTopic extends RefDataFieldFK {
	function __construct() {
		global $db_prefix, $lm2_db_prefix;
		parent::__construct("smf_topic", "SELECT DISTINCT {$db_prefix}topics.id_topic AS id
			, CONCAT(DATE_FORMAT(startDate, '%m%d'), ' ', title) AS description
			, startDate < DATE_SUB(" . php2timestamp(time()) . ", INTERVAL 35 DAY) OR smf_topic IS NOT NULL AS hide
			, 1 AS is_html
			FROM ({$db_prefix}topics, {$db_prefix}calendar, {$db_prefix}messages)
			LEFT JOIN {$lm2_db_prefix}events ON smf_topic = {$db_prefix}topics.id_topic
			WHERE {$db_prefix}calendar.id_topic = {$db_prefix}topics.id_topic
			AND startDate BETWEEN DATE_SUB(" . php2timestamp(time()) . ", INTERVAL 60 DAY) AND DATE_ADD(" . php2timestamp(time()) . ", INTERVAL 1 YEAR)
			AND {$db_prefix}messages.id_msg = {$db_prefix}topics.id_first_msg
			ORDER BY startDate DESC
			" , true, "12em");
	}

	function render($row, $rownum) {
		$name = $this->getName();
		$html = parent::render($row, $rownum);
		$html .= "&nbsp;<A ID='smfTopic$rownum' onMouseOver=\"smfTopicLinker($rownum)\" STYLE=\"width: 1em\">.</A>";
		return $html;
	}

	function makeSelect($rownum) {
		return parent::makeSelect($rownum) . " onChange='smfTopicLinker($rowname)'";
	}
}

function trim_incident_subject(&$item, $key) {
	$item['description'] = preg_replace('/^\\s*Incident report(s?)[:,]\\s+/i', '', $item['description']);
}

class RefDataFieldFKIncidentTopic extends RefDataFieldFK {
	function __construct() {
		global $db_prefix, $lm2_db_prefix, $incidentReportForum;
		parent::__construct("incident_topic",
			"SELECT DISTINCT t.id_topic AS id, TRIM(subject) AS description"
			. ", incident_topic IS NOT NULL AS hide"
			. " FROM ({$db_prefix}messages m, {$db_prefix}topics t)"
			. " LEFT JOIN {$lm2_db_prefix}events ON t.id_topic = incident_topic"
			. " WHERE t.id_board = $incidentReportForum AND id_msg = id_first_msg AND m.id_board = t.id_board"
			. " AND subject LIKE 'Incident report%'"
			. " ORDER BY modifiedTime DESC"
			, true, "7em");
	}

	var $trimmed = false;

	function getMap($row) {
		$map = parent::getMap($row);

		if (!$this->trimmed) {
			array_walk($map, trim_incident_subject);
			$this->trimmed = true;
		}

		return $map;
	}
}

class Events extends RefData {
	function getName() { return "Events"; }
	function getTable() { return "{$this->lm2_db_prefix}events"; }

	function getFields() {
		global $circuit_html_clause, $db_prefix, $lm2_db_prefix;
		global $lm2_mods_group_server, $lm2_mods_group_ukgpl, $lm2_mods_group_court;
		return Array(
			new RefDataFieldID("id_event", '$row["event_type"] == "F" || $row["entries_c"] == 0'), // Cascading takes care of entries.
			new RefDataFieldFK("event_group", eventGroupRefDataFieldFKsql()),
			new RefDataFieldFK("sim_circuit",
				"SELECT id_sim_circuit AS id
				, CONCAT(IF(sim = -1,'',CONCAT(id_sim_circuit,': ')),$circuit_html_clause) AS description
				, sim <> -1 AS hide
				FROM {$this->lm2_db_prefix}sim_circuits
				JOIN {$this->lm2_db_prefix}circuits ON circuit = id_circuit
				JOIN {$this->lm2_db_prefix}circuit_locations ON id_circuit_location = circuit_location
				ORDER BY sim, description", false, "12em"),
			new RefDataFieldDate("event_date"),
			new RefDataFieldEdit("event_seconds", 5),
			new RefDataFieldReadOnly("event_status"),
			$GLOBALS['simRefDataFieldFK'],
			$GLOBALS['eventTypeRefDataFieldFK'],
			new RefDataFieldFKSmfTopic(),
			new RefDataFieldEdit("event_password", 10),
			new RefDataFieldEdit("notes", 7),
			new RefDataFieldEdit("iracing_subsession", 7, 11), //TODO: integer
			new RefDataFieldFK("server_starter_override", moderatorRefDataFieldFKSQL($lm2_mods_group_server, $lm2_mods_group_ukgpl), true, "5em"),
			new RefDataFieldFKIncidentTopic(),
			new RefDataFieldFK("event_moderator", moderatorRefDataFieldFKSQL($lm2_mods_group_court, $lm2_mods_group_ukgpl), true, "5em"),
			new RefDataFieldReadOnly("moderation_start"),
			new RefDataFieldDate("report_published"),
			new RefDataFieldReadOnly("entries_c"),
		);
	}

	function show_notes() {
		echo '<P><I>Events marked as Fun races will be deleted after approximately 6 weeks. To keep an event in perpetuity, mark is as Championship or Non-Championship.</I>
			<BR/>See also <A HREF="/smf/index.php?topic=3366">How to post events</A> in the LM2i board.</P>';
	}

	function addRow() {
		global $filterId;
		$defaultGroup = substr($filterId, 0, 1) == 'g' ? substr($filterId, 1) : -1;
		return array(event_group=>$defaultGroup, sim_circuit=>-1, event_status=>'U', event_type=>'C', sim=>-1);
	}

	function getFilters() {
		$pred = "({$this->lm2_db_prefix}events.event_date >= DATE_SUB(" . php2timestamp(time()) . ", INTERVAL 30 DAY) OR event_status = 'U' OR event_date IS NULL)";

		$filters = array(
			'u'=>array('name'=>'Unannounced', 'predicate'=>"(smf_topic IS NULL or event_password IS NULL) AND $pred AND {$this->lm2_db_prefix}events.event_date <= DATE_ADD(" . php2timestamp(time()) . ", INTERVAL 28 DAY) AND IFNULL(entries_c, 0) = 0"),
			'r'=>array('name'=>'Recent',      'predicate'=>"$pred"),
			'c'=>array('name'=>'Champ',       'predicate'=>"event_type = 'C' AND $pred"),
			'n'=>array('name'=>'Non-Champ',   'predicate'=>"event_type = 'N' AND $pred"),
			'f'=>array('name'=>'Fun',         'predicate'=>"event_type = 'F'"),
			's'=>array('name'=>'Sim',    'nested'=>array()),
			'g'=>array('name'=>'Group',  'nested'=>array()),
			'p'=>array('name'=>'Parent', 'nested'=>array()),
			't'=>array('name'=>'Circuits',  'nested'=>array()),
		);

		$query = lm2_query("
			SELECT DISTINCT id_sim AS id, sim_name AS description
			FROM {$this->lm2_db_prefix}sims
			JOIN {$this->lm2_db_prefix}events ON id_sim = sim
			WHERE $pred
			ORDER BY description
			", __FILE__, __LINE__);
		while ($row = mysql_fetch_assoc($query)) {
			$filters['s']['nested']["s{$row['id']}"] = array(name=>$row['description'], predicate=>"$pred AND sim = " . sqlString($row['id']));
		}
		mysql_free_result($query);

		$query = lm2_query("
			SELECT DISTINCT id_event_group AS id, short_desc AS description
			FROM {$this->lm2_db_prefix}event_groups
			JOIN {$this->lm2_db_prefix}events ON id_event_group = event_group
			WHERE NOT is_protected
			ORDER BY description", __FILE__, __LINE__);
		while ($row = mysql_fetch_assoc($query)) {
			$filters['g']['nested']["g{$row['id']}"] = array(name=>$row['description'], predicate=>sprintf("event_group = %d", $row['id']));
		}
		mysql_free_result($query);

		$query = lm2_query("
			SELECT DISTINCT pg.id_event_group AS id, pg.short_desc AS description
			FROM {$this->lm2_db_prefix}event_groups pg
			JOIN {$this->lm2_db_prefix}event_groups cg ON cg.parent = pg.id_event_group
			JOIN {$this->lm2_db_prefix}events ON cg.id_event_group = event_group
			ORDER BY description", __FILE__, __LINE__);
		while ($row = mysql_fetch_assoc($query)) {
			$filters['p']['nested']["p{$row['id']}"] = array(name=>$row['description'], predicate=>sprintf(
				"(event_group = %d OR event_group IN (SELECT id_event_group FROM {$this->lm2_db_prefix}event_groups WHERE parent = %d))", $row['id'], $row['id']));
		}
		mysql_free_result($query);

		global $circuit_html_clause;
		$query = db_query("
			SELECT id_circuit AS id, $circuit_html_clause AS description
			FROM {$this->lm2_db_prefix}circuits
			JOIN {$this->lm2_db_prefix}circuit_locations ON id_circuit_location = circuit_location
			ORDER BY description
			", __FILE__, __LINE__);
		while ($row = mysql_fetch_assoc($query)) {
			$filters['t']['nested']["lt{$row['id']}"] = array(name=>$row['description'],
				predicate=>sprintf("sim_circuit IN (SELECT id_sim_circuit FROM {$this->lm2_db_prefix}sim_circuits WHERE circuit = %d)", $row['id']));
		}
		mysql_free_result($query);

		return $filters;
	}

	function getDefaultSortOrder() {
		return "U3";
	}
}

class RefDataFieldFKEvent extends RefDataFieldReadOnlySql {
	function render($row, $rownum) {
		return lm2MakeEventLink($row['id_event'], $row['smf_topic']) . parent::render($row, $rownum) . "</A>";
	}
}

class EventEntries extends RefData {
	function getName() { return "Entries"; }
	function getTable() { return "{$this->lm2_db_prefix}event_entries"; }

	function getFields() {
		global $circuit_html_clause, $db_prefix, $lm2_db_prefix;
		return Array(
			new RefDataFieldID("id_event_entry", '$row["reg_class"] == "G"'),
			new RefDataFieldReadOnly("is_protected_c"),
			new RefDataFieldFKEvent("event", false, "
				SELECT CONCAT(short_desc, ' ', brief_name)
				FROM {$this->lm2_db_prefix}event_groups
				JOIN {$this->lm2_db_prefix}sim_circuits
				JOIN {$this->lm2_db_prefix}circuits ON circuit = id_circuit 
				JOIN {$this->lm2_db_prefix}circuit_locations ON circuit_location = id_circuit_location 
				WHERE event_group = id_event_group AND sim_circuit = id_sim_circuit
			", 8),
			new RefDataFieldReadOnlySql('sim_driver', true, "
				SELECT CONCAT(driving_name, IF(lobby_name <> driving_name, CONCAT('/', lobby_name), ''), '->', CONCAT(driver_name, IF(driver_member > 10000000, ' (UKGPL historic)', '')))
				FROM {$this->lm2_db_prefix}drivers
				WHERE sim_driver_member = driver_member
			", 7),
			$GLOBALS['memberRefDataFieldFK'],
			new RefDataFieldFK("driver_type", array('A'=>'AI', 'S'=>'Server', 'G'=>'Non-scoring Guest'), true, '3em'),
			new RefDataFieldFK("team", "SELECT id_team AS id
				, CONCAT(team_name, CASE team_is_fake WHEN 2 THEN ' (UKGPL)' WHEN 1 THEN ' (admin)' ELSE '' END) AS description
				, team_is_fake <> 1 AS hide
				FROM {$lm2_db_prefix}teams
				ORDER BY description", true, "10em"),
			new RefDataFieldFK("reg_class", "
				SELECT class_code AS id, CONCAT(class_code, ' - ', description) AS description
				FROM {$GLOBALS['lm2_db_prefix']}reg_classes
				WHERE class_code IS NOT NULL
				ORDER BY description
			", true, "4em"), 
			new RefDataFieldReadOnlySql("sim_car", false, "
				CONCAT((SELECT vehicle FROM {$this->lm2_db_prefix}sim_cars WHERE sim_car = id_sim_car)
				, ' (', (SELECT class_description FROM {$this->lm2_db_prefix}classes WHERE car_class_c = id_class)
				, ')')", 13),
			new RefDataFieldEdit("qual_best_lap_time", 7, 9),
			new RefDataFieldEdit("qual_pos", 2),
			//new RefDataFieldReadOnly("qual_pos_class"),
			new RefDataFieldEdit("start_pos", 2),
			//new RefDataFieldReadOnly("start_pos_class"),
			new RefDataFieldEdit("race_pos_sim", 2),
			new RefDataFieldReadOnly("race_pos"),
			//new RefDataFieldReadOnly("race_pos_penalty"),
			new RefDataFieldEdit("race_laps", 2),
			new RefDataFieldEdit("race_time_actual", 7, 9),
			new RefDataFieldReadOnly("race_time_adjusted"),
			new RefDataFieldEdit("race_best_lap_time", 7, 9),
			//new RefDataFieldReadOnly("ballast_driver"),
			//new RefDataFieldReadOnly("penalty_points"),
			//new RefDataFieldReadOnly("excluded_c"),
			new RefDataFieldFK("retirement_reason", "
				SELECT retirement_reason AS id, reason_desc AS description
				FROM {$this->lm2_db_prefix}retirement_reasons
				ORDER BY description
				", true, "4em"),
		);
	}

	function show_notes() {
?>
		<P><B><NOBR>This page should be use to correct errors in the import only.</NOBR> <NOBR>Do <I>not</I> adjust times or positions as the result of penalties.</NOBR></B>
		<BR/><TT>race_pos_sim</TT>: <SMALL>locks drivers to specific positions; where null, the system will 'fill in' positions around them</SMALL>
		<BR/><TT>qual_pos</TT>: <SMALL>classified qualifying results, normally generated from times;</SMALL>
			<SMALL><TT>start_pos</TT>: actual grid position, in case it doesn't match qualifying</SMALL>
		<BR/><I>Only the first 50 entries are displayed.</I>
		</P>
<?php
	}

	function getFilters() {
		global $lm2_ukgpl_migration_sim_driver, $lm2_guest_member_id, $ID_MEMBER, $isProtectedValueMap;
		$notMig = "$lm2_ukgpl_migration_sim_driver <> sim_driver";
		$notProtNotMig = "NOT is_protected_c AND $notMig";

		$filters = array(
			'w'=>array('name'=>'Wrong member', 'nested'=>array()),
			't'=>array('name'=>'Wrong driver type', 'nested'=>array()),
			'e'=>array('name'=>'Event',  'nested'=>array()),
			'd'=>array('name'=>'Driver', 'nested'=>array()),
		);
		if ($ID_MEMBER == 1) {
			$filters['s'] = array('name'=>'SimDriver', 'nested'=>array());
			$filters['g'] = array('name'=>'Protected event guest drivers without type', 'predicate'=>"is_protected_c AND member = $lm2_guest_member_id AND driver_type IS NULL");
		}

//TODO: this needs tidying up so that only the relevant filters are shown...
		$query = lm2_query("
			SELECT DISTINCT is_protected_c AS id, is_protected_c AS description
			FROM {$this->lm2_db_prefix}event_entries
			" . ($ID_MEMBER == 1 ? "" : "WHERE is_protected_c = 0") . "
			ORDER BY id
			", __FILE__, __LINE__);
		while ($row = mysql_fetch_assoc($query)) {
			if (array_key_exists($row['id'], $isProtectedValueMap)) {
				$row['description'] = "Prot? {$isProtectedValueMap[$row['id']]}";
			}
			
			$subdivs = $row['id']
				? array('Live'=>' AND sim_driver_member <= 10000000', 'Historic'=>' AND sim_driver_member > 10000000')
				: array(''=>'');
			foreach ($subdivs as $id=>$pred) {
				$filters['w']['nested']["w{$row['id']}$id"] = array(name=>"{$row['description']} $id", predicate=>"
					$notMig AND is_protected_c = {$row['id']} AND member <> sim_driver_member$pred");
			}
		}
		mysql_free_result($query);

		$filters['t']['nested']['tANY'] = array(name=>'Any', predicate=>"
			$notProtNotMig AND IF(member = $lm2_guest_member_id, 1, 0) <> IF(driver_type IS NULL, 0, 1)");
		$query = lm2_query("
			SELECT DISTINCT id_sim AS id, sim_name AS description
			FROM {$this->lm2_db_prefix}sims
			WHERE id_sim IN (SELECT sim FROM {$this->lm2_db_prefix}sim_drivers)
			ORDER BY description
			", __FILE__, __LINE__);
		while ($row = mysql_fetch_assoc($query)) {
			$filters['t']['nested']["t{$row['id']}"] = array(name=>$row['description'], predicate=>"
				{$filters['t']['nested']['tANY']['predicate']} AND event IN (SELECT id_event FROM {$this->lm2_db_prefix}events WHERE sim = {$row['id']})");
		}
		mysql_free_result($query);

		$query = lm2_query("
			SELECT DISTINCT id_event AS id, CONCAT(short_desc, ' ', brief_name) AS description
			FROM {$this->lm2_db_prefix}events
			JOIN {$this->lm2_db_prefix}sim_circuits ON sim_circuit = id_sim_circuit
			JOIN {$this->lm2_db_prefix}circuits ON circuit = id_circuit
			JOIN {$this->lm2_db_prefix}circuit_locations ON circuit_location = id_circuit_location
			JOIN {$this->lm2_db_prefix}event_groups ON event_group = id_event_group
			WHERE entries_c > 0 AND NOT is_protected
			ORDER BY event_date DESC
			", __FILE__, __LINE__);
		while ($row = mysql_fetch_assoc($query)) {
			$filters['e']['nested']["e{$row['id']}"] = array(name=>$row['description'], predicate=>"event = {$row['id']}");
		}
		mysql_free_result($query);

		$query = lm2_query("
			SELECT DISTINCT driver_member AS id, CONCAT(driver_name, IF(driver_member > 10000000, ' (UKGPL historic)', '')) AS description
			FROM {$this->lm2_db_prefix}drivers
			WHERE driver_member IN (SELECT member FROM {$this->lm2_db_prefix}event_entries)
			ORDER BY description
			", __FILE__, __LINE__);
		while ($row = mysql_fetch_assoc($query)) {
			$filters['d']['nested']["d{$row['id']}"] = array(name=>$row['description'], predicate=>"member = {$row['id']} AND NOT is_protected_c");
		}
		mysql_free_result($query);

		if (array_key_exists('s', $filters)) {
			$query = lm2_query("
				SELECT DISTINCT id_sim_drivers AS id, CONCAT(driving_name,'/',lobby_name,'->',driver_name) AS description
				FROM {$this->lm2_db_prefix}sim_drivers
				JOIN {$this->lm2_db_prefix}drivers ON driver_member = member
				WHERE id_sim_drivers IN (SELECT sim_driver FROM {$this->lm2_db_prefix}event_entries WHERE IF(member = $lm2_guest_member_id, 1, 0) <> IF(driver_type IS NULL, 0, 1))
				ORDER BY IF(member = $lm2_guest_member_id, 1, 0), description
				", __FILE__, __LINE__);
			while ($row = mysql_fetch_assoc($query)) {
				$filters['s']['nested']["s{$row['id']}"] = array(name=>$row['description'], predicate=>"sim_driver = {$row['id']}");
			}
			mysql_free_result($query);
		}

		return $filters;
	}

	function getDefaultSortOrder() {
		return "U12";
	}

	function rebuild() {
		global $filterId;
		if ($eventId = (substr($filterId, 0, 1) == 'e' ? substr($filterId, 1) : null)) {
			echo "</BR>Resetting positions...\n";
			reset_unadjusted_positions($eventId);
		}
	}

	function makeSql($what, $from, $where) {
		return "SELECT $what, id_event, smf_topic, sd.* FROM ($from)
			JOIN (SELECT id_sim_drivers, lobby_name, driving_name, member AS sim_driver_member FROM {$this->lm2_db_prefix}sim_drivers) AS sd ON sim_driver = id_sim_drivers
			JOIN {$this->lm2_db_prefix}events ON id_event = event WHERE $where LIMIT 50";
	}
}

class EventBoards extends RefData {
	function getName() { return "Boards"; }
	function getTable() { return "{$this->lm2_db_prefix}event_boards"; }

	function getFields() {
		return Array(
			new RefDataFieldID("id_event_board", true),
			new RefDataFieldFK("event_group", eventGroupRefDataFieldFKsql(), true),
			$GLOBALS['eventTypeRefDataFieldFK'],
			new RefDataFieldFK("smf_board", "
				SELECT id_board AS id, name AS description
				FROM {$this->db_prefix}boards
				ORDER BY boardOrder"),
		);
	}

	function addRow() {
		return array();
	}

	function getDefaultSortOrder() {
		return "U1";
	}

	function getFilters() {
		$filters = array(
			'none'=>array(name=>'None', predicate=>'1'),
		);
		return $filters;
	}
}

$isProtectedValueMap = array(0=>'No', 1=>'Yes', 2=>'Hid');

class EventGroups extends RefData {
	function getName() { return "EventGroups"; }
	function getTable() { return "{$this->lm2_db_prefix}event_groups"; }

	function getFields() {
		global $mkp_db_prefix, $lm2_series_details_topic, $isProtectedValueMap;
		global $lm2_mods_group_server, $lm2_mods_group_ukgpl;
		$sixMonthsAgo = time() - 86400 * 30 * 6;
		return Array(
			new RefDataFieldID("id_event_group", true),
			new RefDataFieldEdit("short_desc", 8),
			new RefDataFieldEdit("long_desc", 32),
			new RefDataFieldReadOnly("full_desc"),
			new RefDataFieldFK("parent",
				"SELECT id_event_group AS id, short_desc AS description"
				. " FROM {$this->lm2_db_prefix}event_groups"
				. " ORDER BY description", true),
			new RefDataFieldFK("series_details", "
				SELECT id_msg AS id, subject AS description, id_msg IN (SELECT series_details FROM {$this->lm2_db_prefix}event_groups) AS hide, 1 AS is_html
				FROM {$this->db_prefix}messages
				WHERE id_topic = $lm2_series_details_topic
				AND id_msg <> (SELECT id_first_msg FROM {$this->db_prefix}topics WHERE id_topic = $lm2_series_details_topic)
				ORDER BY description", true, "10em"),
			new RefDataFieldFK("series_theme", "
				SELECT id_theme AS id, value AS description
				FROM {$this->db_prefix}themes
				WHERE variable = 'name'
				ORDER BY description", true, "4em"),
			new RefDataFieldFK("penalty_group", "
				SELECT penalty_group AS id, penalty_group_desc AS description
				FROM {$this->lm2_db_prefix}penalty_groups
				ORDER BY description", false, "5em"),
//			new RefDataFieldFK("mkp_pid", "
//				SELECT id, title AS description
//				FROM {$mkp_db_prefix}pages
//				ORDER BY title
//				", true, "12em"),
			new RefDataFieldFK("server_starter", moderatorRefDataFieldFKSQL($lm2_mods_group_server, $lm2_mods_group_ukgpl), true, "5em"),
			new RefDataFieldFK("is_protected", $isProtectedValueMap, false),
			new RefDataFieldFK("reg_topic", "
				SELECT {$this->db_prefix}topics.id_topic AS id, subject AS description, postertime < $sixMonthsAgo AS hide, 1 AS is_html
				FROM {$this->db_prefix}messages, {$this->db_prefix}topics, {$this->db_prefix}polls
				WHERE {$this->db_prefix}topics.id_poll = {$this->db_prefix}polls.id_poll
				AND id_first_msg = id_msg
				AND {$this->db_prefix}messages.id_topic = {$this->db_prefix}topics.id_topic
				ORDER BY description", true, "14em"),
			new RefDataFieldReadOnly("sequence_c"),
			new RefDataFieldReadOnly("depth_c"),
		);
	}

	function addRow() {
		return array('is_protected'=>0, 'penalty_group'=>1);
	}

	function getDefaultSortOrder() {
		return "U1";
	}

	function rebuild() {
		echo "</BR>Rebuilding event group tree...\n";

		// Cannot use TRUNCATE within a transaction.
		lm2_query("DELETE FROM {$this->lm2_db_prefix}event_group_tree", __FILE__, __LINE__);

		// The "parent <> self" is to exclude the Team Management Only pseudogroup.
		$query = lm2_query("
			SELECT id_event_group, parent, full_desc FROM {$this->lm2_db_prefix}event_groups
			WHERE parent IS NULL OR parent <> id_event_group
			", __FILE__, __LINE__);
		while ($row = mysql_fetch_assoc($query)) {
			$this->write_tree($row['id_event_group'], $row['parent'], $row['full_desc']);
		}
		mysql_free_result($query);

		$dispSeq = 0;
		$this->set_seq(0, $dispSeq, 0);

		echo "</BR>Resetting cached protection status on event entries...";

		lm2_query("
			UPDATE {$this->lm2_db_prefix}event_entries
			JOIN {$this->lm2_db_prefix}events ON id_event = event
			JOIN {$this->lm2_db_prefix}event_groups ON id_event_group = event_group
			SET is_protected_c = is_protected
			", __FILE__, __LINE__);

		echo " points...";

		lm2_query("
			UPDATE {$this->lm2_db_prefix}event_entries
			JOIN {$this->lm2_db_prefix}event_points ON id_event_entry = event_entry
			SET {$this->lm2_db_prefix}event_points.is_protected_c = {$this->lm2_db_prefix}event_entries.is_protected_c
			", __FILE__, __LINE__);

		echo " and championships...\n";

		lm2_query("
			UPDATE {$this->lm2_db_prefix}championship_points
			JOIN {$this->lm2_db_prefix}championships ON id_championship = championship
			JOIN {$this->lm2_db_prefix}event_groups ON id_event_group = event_group
			SET is_protected_c = is_protected
			", __FILE__, __LINE__);
	}

	function set_seq($parent, &$dispSeq, $depth) {
		// The "parent <> self" is to exclude the Team Management Only pseudogroup.
		$query = lm2_query("
			SELECT id_event_group, full_desc
			FROM {$this->lm2_db_prefix}event_groups
			WHERE IFNULL(parent, 0) = $parent
			ORDER BY (
				SELECT MAX(event_date) FROM {$this->lm2_db_prefix}events WHERE event_group IN (
					SELECT contained from {$this->lm2_db_prefix}event_group_tree WHERE container = id_event_group)
			) DESC, long_desc
			", __FILE__, __LINE__);
		while ($row = mysql_fetch_assoc($query)) {
			lm2_query("UPDATE {$this->lm2_db_prefix}event_groups
				SET sequence_c = " . ++$dispSeq . ", depth_c = $depth
				WHERE id_event_group = {$row['id_event_group']}
				", __FILE__, __LINE__);
			$this->set_seq($row['id_event_group'], $dispSeq, $depth + 1);
		}
		mysql_free_result($query);
	}

	function fullName($group) {
		global $lm2_db_prefix;

		$desc = '';
		$sep = '';
		while ($group) {
			$query = db_query("SELECT parent, long_desc FROM {$lm2_db_prefix}event_groups WHERE id_event_group = $group", __FILE__, __LINE__);
			($row = mysql_fetch_assoc($query)) || die("can't find group $group");
			$desc = "{$row['long_desc']}$sep$desc";
			$sep = ' ';
			$group = $row['parent'];
			mysql_fetch_assoc($query) && die("multiple groups matching $group!");
			mysql_free_result($query);
		}
		return $desc;
	}

	function write_tree($group, $parent, $desc) {
		if (($fullDesc = $this->fullName($group)) != $desc) {
			lm2_query("
				UPDATE {$this->lm2_db_prefix}event_groups
					SET full_desc = " . sqlString($fullDesc) . "
					WHERE id_event_group = $group
					", __FILE__, __LINE__);
		}

		$depth = 0;
		lm2_query("
			INSERT INTO {$this->lm2_db_prefix}event_group_tree
			(container, contained, depth) VALUES ($group, $group, $depth)
			", __FILE__, __LINE__);
		while (!is_null($parent)) {
			++$depth;
			lm2_query("
				INSERT INTO {$this->lm2_db_prefix}event_group_tree
				(container, contained, depth) VALUES ($parent, $group, $depth)
				", __FILE__, __LINE__);
			$query = lm2_query("
				SELECT parent FROM {$this->lm2_db_prefix}event_groups
				WHERE id_event_group = $parent
				", __FILE__, __LINE__);
			($row = mysql_fetch_assoc($query)) || die("parent $parent not found");
			$parent = $row['parent'];
			mysql_fetch_assoc($query) && die("parent $parent ambiguous");
			mysql_free_result($query);
		}
	}

	function getFilters() {
		$filters = array(
			''=>array(name=>'Unprotected', predicate=>'NOT is_protected'),
			'hid'=>array(name=>'Hidden', predicate=>'is_protected = 2'),
			'none'=>array(name=>'None', predicate=>'1'),
			'mkp'=>array(name=>'MkPortal', predicate=>'mkp_pid IS NOT NULL AND series_details IS NULL'),
			'notheme'=>array(name=>'No Theme', predicate=>'series_theme IS NULL'),
			'p'=>array('name'=>'Parent', 'nested'=>array()),
		);

		$query = lm2_query("
			SELECT DISTINCT id_event_group AS id, short_desc AS description
			FROM {$this->lm2_db_prefix}event_groups
			WHERE id_event_group IN (SELECT parent FROM {$this->lm2_db_prefix}event_groups)
			ORDER BY description", __FILE__, __LINE__);
		$filters['p']['nested']["pNULL"] = array('name'=>"Root", predicate=>"parent IS NULL");
		while ($row = mysql_fetch_assoc($query)) {
			$filters['p']['nested']["p{$row['id']}"] = array('name'=>$row['description'], predicate=>"parent = {$row['id']}");
		}
		mysql_free_result($query);

		return $filters;
	}
}

class PenaltyGroups extends RefData {
	function getName() { return "PenaltyGroups"; }
	function getTable() { return "{$this->lm2_db_prefix}penalty_groups"; }

	function getFields() {
		return Array(
			new RefDataFieldID("penalty_group", true),
			new RefDataFieldEdit("penalty_group_desc", 30),
			new RefDataFieldEdit("penalty_group_months", 1),
			new RefDataFieldFK("penalty_group_mode", array('S'=>'SRou', 'U'=>'UKGPL'), false),
		);
	}

	function addRow() {
		return array('penalty_group_desc'=>"UKGPL Sx Dy", penalty_group_months=>"9", penalty_group_mode=>'S');
	}

	function getDefaultSortOrder() {
		return "U1";
	}
}

class ScoringSchemes extends RefData {
	function getName() { return "Scoring"; }
	function getTable() { return "{$this->lm2_db_prefix}scoring_schemes"; }

	function getFields() {
		return Array(
			new RefDataFieldID("id_scoring_scheme", true), //XXX: don't allow deletes where in use
			new RefDataFieldEdit("scoring_scheme_name", 20),
			new RefDataFieldFK("points_scheme",
				"SELECT id_points_scheme AS id, GROUP_CONCAT(points ORDER BY position) AS description"
				. " FROM {$this->lm2_db_prefix}points_schemes"
				. " GROUP BY id_points_scheme"
				. " ORDER BY description", true),
			new RefDataFieldEdit("minimum_distance", 4), //FIXME: need a numeric field type - DECIMAL(3,2) in this case.
			new RefDataFieldFK("scoring_type", array('T'=>'Traditional', 'C'=>'Cumulative', 'A'=>'Average'), false),
			new RefDataFieldFK("zeros_count", array('0'=>'No', '1'=>'Yes'), true),
			new RefDataFieldEdit("car_change_penalty", 4), //FIXME: need a numeric field type - DOUBLE(4,2) in this case.
			new RefDataFieldEdit("free_car_changes", 1), //FIXME: need a numeric field type - INT(1) in this case.
			new RefDataFieldEdit("single_car_penalty", 3), //FIXME: need a numeric field type - DECIMAL(2,1) in this case.
			new RefDataFieldEdit("max_tokens", 3), //FIXME: need a numeric field type - INT(3) in this case.
			new RefDataFieldEdit("overspend_penalty", 2), //FIXME: need a numeric field type - INT(2) in this case.
		);
	}

	function addRow() {
		return array();
	}

	function getDefaultSortOrder() {
		return "U1";
	}
}

function simDriverIsUsed($id_sim_driver, $row) {
	global $lm2_db_prefix, $guest_member_id;

	/* Not needded now that all entries retain their sim driver.
	if ($row['member'] != $guest_member_id) {
		return false;
	}*/

	$entries = -1;
	$query = lm2_query("SELECT COUNT(*) AS entries"
		. " FROM {$lm2_db_prefix}event_entries"
		. " WHERE sim_driver = $id_sim_driver",
		__FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($query)) {
		$entries = max($entries, $row['entries']);
	}
	mysql_free_result($query);

	return $entries == 0;
}

class SimDrivers extends RefData {
	function getName() { return "SimDrivers"; }
	function getTable() { return "{$this->lm2_db_prefix}sim_drivers"; }

	function getFields() {
		return Array(
			new RefDataFieldID("id_sim_drivers", 'simDriverIsUsed($value, $row)'),
			$GLOBALS['memberRefDataFieldFK'],
			$GLOBALS['simRefDataFieldFKReadOnly'],
			new RefDataFieldReadOnly("driving_name"),
			new RefDataFieldReadOnly("lobby_name"),
		);
	}

	function getFilters() {
		global $guest_member_id;

		$filters = array(
			'u'=>array('name'=>'Unmapped', 'predicate'=>"member = 0"),
			'g'=>array('name'=>'Guest', 'nested'=>array()),
			's'=>array('name'=>'Sims', 'nested'=>array()),
			'd'=>array('name'=>'Driver', 'nested'=>array()),
			'l'=>array('name'=>'UKGPL Historic', 'predicate'=>"member > 10000000"),
		);

		$query = lm2_query("
			SELECT id_sim AS id, sim_name AS description
			FROM {$this->lm2_db_prefix}sims
			WHERE id_sim <> -1
			", __FILE__, __LINE__);
		$filters['g']['nested']["gAny"] = array('name'=>"{Any sim}", 'predicate'=>"member = $guest_member_id");
		while ($row = mysql_fetch_assoc($query)) {
			$filters['s']['nested']["s{$row['id']}"] = array('name'=>$row['description'], 'predicate'=>"sim = " . sqlString($row['id']));
			$filters['g']['nested']["g{$row['id']}"] = 
				array('name'=>$row['description'], 'predicate'=>"member = $guest_member_id AND sim = " . sqlString($row['id']));
		}
		mysql_free_result($query);

		$query = lm2_query("
			SELECT DISTINCT driver_member AS id, CONCAT(driver_name,IF(driver_member > 10000000,' (UKGPL historic)','')) AS description
			FROM {$this->lm2_db_prefix}drivers
			WHERE driver_member IN (SELECT member FROM {$this->lm2_db_prefix}sim_drivers)
			ORDER BY description
			", __FILE__, __LINE__);
		while ($row = mysql_fetch_assoc($query)) {
			$filters['d']['nested']["d{$row['id']}"] = array(name=>$row['description'], predicate=>"member = {$row['id']}");
		}
		mysql_free_result($query);

		return $filters;
	}

	function rebuild() {
		$query = db_query("
			SELECT id_sim_drivers, IFNULL(driving_name, lobby_name) AS name
			FROM {$this->lm2_db_prefix}sim_drivers
			WHERE member = 10000000
			" , __FILE__, __LINE__);
		while ($row = mysql_fetch_assoc($query)) {
			$id = $this->getNextFreeHistoricId();
			echo "<P>{$row['id_sim_drivers']} = " . htmlentities($row['name'], ENT_QUOTES) . " -> $id</P>\n";
			db_query("
				INSERT INTO {$this->lm2_db_prefix}drivers
				(driver_member, driver_name)
				VALUES ($id, " . sqlString($row['name']) . ")
				" , __FILE__, __LINE__);
			db_query("
				UPDATE {$this->lm2_db_prefix}sim_drivers
				SET member = $id
				WHERE id_sim_drivers = {$row['id_sim_drivers']}
				" , __FILE__, __LINE__);
		}
		mysql_free_result($query);
	}

	function getNextFreeHistoricId() {
		$query = db_query("
			SELECT MAX(driver_member) + 1 AS id
			FROM {$this->lm2_db_prefix}drivers
			WHERE driver_member < 16000000
			" , __FILE__, __LINE__);
		while ($row = mysql_fetch_assoc($query)) {
			return $row['id'];
			mysql_free_result($query);
		}
		die("err... how can there be no ID?!");
	}

	function getDefaultSortOrder() {
		return "U3";
	}

	function show_notes() {
		global $lm2_guest_member_id;
		global $filterId;

		$matchMemberIds = "0";
		$sdSimClause = "";
		if (substr($filterId, 0, 1) == 'g') {
			$matchMemberIds .= ", $lm2_guest_member_id";
			if (is_numeric($sim = substr($filterId, 1))) {
				$sdSimClause = "AND {$this->lm2_db_prefix}sim_drivers.sim = $sim";
			}
		}

		$query = lm2_query("
			SELECT DISTINCT driving_name, CONCAT(driver_name, ' (', sim_name, ')') AS member_name
			FROM {$this->lm2_db_prefix}sim_drivers
			LEFT JOIN {$this->lm2_db_prefix}driver_details USING (driving_name)
			LEFT JOIN {$this->db_prefix}members ON memberName = driving_name OR driving_name SOUNDS LIKE memberName OR driving_name SOUNDS LIKE realName
			JOIN {$this->lm2_db_prefix}drivers ON driver = driver_member
			JOIN {$this->lm2_db_prefix}sims ON {$this->lm2_db_prefix}driver_details.sim = id_sim
			WHERE member IN ($matchMemberIds) $sdSimClause
			" , __FILE__, __LINE__);
		$closer = '';
		$sep = '<P>';
		while ($row = mysql_fetch_assoc($query)) {
			echo "{$sep}<TT>{$row['driving_name']}</TT> may be <TT>{$row['member_name']}</TT>\n";
			$sep = '<BR/>';
			$closer = '</P>';
		}
		mysql_free_result($query);
		echo $closer;
	}
}

class Weather extends RefData {
	function getName() { return "Weather"; }
	function getTable() { return "{$this->lm2_db_prefix}wu_conditions"; }

	function getFields() {
		return Array(
			new RefDataFieldID("id_condition", true),
			new RefDataFieldReadOnly("condition_text"),
			new RefDataFieldEdit("condition_gtr", 5), //FIXME: need a numeric field type - DOUBLE(4,1) in this case.
		);
	}

	function getFilters() {
		$filters = array(
			'u'=>array('name'=>'Unmapped', 'predicate'=>"condition_gtr IS NULL"),
			'*'=>array('name'=>'All', 'predicate'=>"1"),
		);
		return $filters;
	}

	function getDefaultSortOrder() {
		return "U2";
	}

	function show_notes() {
		global $boardurl;
		echo "<P>0-61.65 dry; 61.66-90 rain; 90-99 monsoon (<A HREF='$boardurl/index.php?topic=2677'>details</A>)</P>\n";
	}
}

class Money extends RefData {
	function getName() { return "Money"; }
	function getTable() { return "{$this->lm2_db_prefix}money"; }

	function getFields() {
		return Array(
			new RefDataFieldID("id_money", true),
			new RefDataFieldDate("money_date", true),
			$GLOBALS['memberRefDataFieldFK'],
			new RefDataFieldEdit("amount", 8), //FIXME: need a numeric field type - DOUBLE(6,2) in this case.
			new RefDataFieldFK("anonymous", array(0=>'No', 1=>'Yes'), false),
			new RefDataFieldEdit("notes", 80), //FIXME: need a numeric field type - DOUBLE(4,1) in this case.
		);
	}

	function getFilters() {
		$filters = array(
			'N'=>array('name'=>'None - please respect privacy', 'predicate'=>"0"),
			'3'=>array('name'=>'Last 3 months', 'predicate'=>"money_date >= DATE_SUB(" . php2timestamp(time()) . ", INTERVAL 3 MONTH)"),
			'd'=>array('name'=>'Donations', 'predicate'=>"amount > 0"),
			's'=>array('name'=>'Spending', 'predicate'=>"amount < 0"),
		);
		return $filters;
	}

	function getDefaultSortOrder() {
		return "-1";
	}

	function addRow() {
		global $lm2_guest_member_id;
		return array('member'=>$lm2_guest_member_id, 'anonymous'=>1);
	}
}

//class Test extends RefData {
//	function getName() { return "Test"; }
//	function getTable() { return "{$this->lm2_db_prefix}test"; }
//
//	function getFields() {
//		return Array(
//			new RefDataFieldID("id_test", true),
//			new RefDataFieldEdit("id_test", 4),
//			new RefDataFieldReadOnly("test_desc", 25),
//			$GLOBALS['memberRefDataFieldFK'],
//			$GLOBALS['simRefDataFieldFK'],
//			$GLOBALS['classRefDataFieldFK'],
//			new RefDataFieldEdit("factor", 5),
//			new RefDataFieldEdit("editable_test", 50),
//			new RefDataFieldDate("my_date"),
//		);
//	}
//
//	function addRow() {
//		global $guest_member_id;
//		return array(test_desc=>null, member=>$guest_member_id, sim=>-1, 'class'=>'-');
//	}
//
//	function getFilters() {
//		$filters = parent::getFilters();
//		$filters['g'] = array(name=>'Guests', predicate=>'member = 2');
//		return $filters;
//	}
//}

$lm2_mods_group_refdata = 53;
$refDatas = Array(
//	'tst'=>new Test(),
	'mfc'=>array('refData'=>new Manufacturers(), 'groups'=>array($lm2_mods_group_refdata)),
	'cls'=>array('refData'=>new Classes(), 'groups'=>array($lm2_mods_group_refdata)),
	'clf'=>array('refData'=>new Classification(), 'groups'=>array($lm2_mods_group_refdata)),
	'car'=>array('refData'=>new Cars(), 'groups'=>array($lm2_mods_group_refdata)),
	'scr'=>array('refData'=>new SimCars(), 'groups'=>array($lm2_mods_group, $lm2_mods_group_server, $lm2_mods_group_ukgpl)),
	'tyr'=>array('refData'=>new Tyres(), 'groups'=>array($lm2_mods_group_refdata)),
	'cnt'=>array('refData'=>new Countries(), 'groups'=>array($lm2_mods_group_refdata)),
	'loc'=>array('refData'=>new Locations(), 'groups'=>array($lm2_mods_group_refdata)),
	'cir'=>array('refData'=>new Circuits(), 'groups'=>array($lm2_mods_group_refdata)),
	'sci'=>array('refData'=>new SimCircuits(), 'groups'=>array($lm2_mods_group_refdata)),
	'scs'=>array('refData'=>new ScoringSchemes(), 'groups'=>array($lm2_mods_group_refdata)),
	'chm'=>array('refData'=>new Championships()),
	'chg'=>array('refData'=>new ChampGroups()),
//	'rgp'=>array('refData'=>new RegPolls()),
	'evb'=>array('refData'=>new EventBoards(), 'groups'=>array($lm2_mods_group_refdata)),
	'evg'=>array('refData'=>new EventGroups()),
	'pgr'=>array('refData'=>new PenaltyGroups()),
	'evt'=>array('refData'=>new Events()),
	'eve'=>array('refData'=>new EventEntries(), 'groups'=>array($lm2_mods_group, $lm2_mods_group_ukgpl)),
	'sdr'=>array('refData'=>new SimDrivers(), 'groups'=>array($lm2_mods_group, $lm2_mods_group_server, $lm2_mods_group_ukgpl)),
	'wth'=>array('refData'=>new Weather(), 'groups'=>array($lm2_mods_group_server)),
	'mon'=>array('refData'=>new Money(), 'groups'=>array(1)),
);

// And now... the code!

echo "<P><SMALL>Table:";
$refData = $refDatas[$_REQUEST['refData']];
if ($refData && !($sortOrder = $_REQUEST['sortOrder'])) {
	$sortOrder = $refData['refData']->getDefaultSortOrder();
}
foreach ($refDatas as $name=>$rd) {
	$groups = array_key_exists('groups', $rd) ? $rd['groups'] : array($lm2_mods_group, $lm2_mods_group_ukgpl);
	if (count(array_intersect($groups, $user_info['groups'])) > 0) {
		$html = $rd['refData']->getName();
		if ($name != $_REQUEST['refData']) {
			$html = '<A HREF="index.php?action=refdata&refData=' . $name . '">' . $html . '</A>';
		}
		echo " <NOBR>$html</NOBR>";
	}
}
if ($refData) {
	$refData = $refData['refData'];
}
$filterWhere = "1";
$filterId = lm2ArrayValue($_REQUEST, 'rdFilt');
if (!is_null($refData)) {
	echo "<BR/>Filter:";
	foreach ($refData->getFilters() as $id=>$details) {
		$html = $details['name'];
		if (array_key_exists('nested', $details)) {
			$html .= "<SELECT STYLE='max-width: 10em;' onChange='top.location.href = \"index.php?action=refdata&refData={$_REQUEST['refData']}&rdFilt=\" + value + \"&sortOrder={$_REQUEST['sortOrder']}\"'>\n";
			$options = "";
			$seen = false;
			foreach ($details['nested'] as $idN=>$detailsN) {
				if (is_null($filterId)) {
					$filterId = $idN;
				}
				$sel = '';
				if ($idN == $filterId) {
					$filterWhere = $detailsN['predicate'];
					$sel = ' SELECTED';
					$seen = true;
				}
				$options .= "<OPTION VALUE='$idN'$sel>{$detailsN['name']}</OPTION>\n";
			}
			if (!$seen) {
				$options = "<OPTION VALUE='' SELECTED>Select...</OPTION>\n$options";
			}
			$html .= "$options</SELECT>\n";
		} else {
			if (is_null($filterId)) {
				$filterId = $id;
			}
			if ($id != $filterId) {
				$html = "<A HREF=\"index.php?action=refdata&refData={$_REQUEST['refData']}&rdFilt=$id&sortOrder={$_REQUEST['sortOrder']}\">$html</A>";
			} else {
				$filterWhere = $details['predicate'];
			}
		}
		echo "\n<NOBR>$html</NOBR>";
	}
}
echo "</SMALL></P>\n";

function rowSorter($row1, $row2) {
	global $sortDir, $sortField;
	$order = $sortField->compare($row1[$sortField->getName()], $row2[$sortField->getName()]);
	return $order * ($sortDir == '-' ? -1 : 1);
}

function makeSortLink($i, $dirUrlPrefix, $dirHtml) {
	global $sortOrder;
	$text = $dirHtml;
	if (($order = "$dirUrlPrefix$i") !== $sortOrder) {
		global $filterId;
		$baseUrl = "index.php?action=refdata&refData={$_REQUEST['refData']}&rdFilt=$filterId";
		$text = "<A HREF=\"$baseUrl&sortOrder=$dirUrlPrefix$i\">$text</A>";
	}
	return $text;
}

if (!is_null($refData)) {
	// Update any rows passed.

	$rownum = 0;
	while ($id = $_REQUEST["id$rownum"]) {
		$where = null;
		$values = array();
		foreach ($refData->getFields() as $i=>$field) {
			$name = $field->getName();
			$is_id = is_a($field, 'RefDataFieldID');
			$value = $field->sqlize(get_request_param(($is_id ? "id" : $name) . $rownum));
			if ($value == null) continue;
			if ($is_id) {
				is_null($where) || die("two where clause fields!");
				$where = ($value == $addMagic ? $addMagic : " WHERE $name = $value");
			} else {
				is_null($values[$name]) || die("two values for $name!");
				$values[$name] = $value;
			}
		}
		if ($id == $_REQUEST["delete$rownum"]) {
			$sql = "DELETE FROM " . $refData->getTable() . $where;
		} else if ($id == $addMagic) {
			$sql = "INSERT INTO " . $refData->getTable();
			$sep = "";
			$field_names = "";
			$field_values = "";
			foreach ($values as $name=>$value) {
				$field_names .= "$sep$name";
				$field_values .= "$sep$value";
				$sep = ", ";
			}
			$sql .= " ($field_names) VALUES ($field_values)";
		} else {
			$sql = "UPDATE " . $refData->getTable();
			$sep = " SET ";
			foreach ($values as $name=>$value) {
				$sql .= "$sep$name = $value";
				$sep = ", ";
			}
			$sql .= $where;
		}
		echo "<!-- $sql -->\n";
		lm2_query($sql, __FILE__, __LINE__);
		++$rownum;
	}
	if ($rownum > 0) {
		echo "<P>($rownum rows processed) <I>";
		$refData->rebuild();
		echo "</I></P>\n";
	}

	$refData->show_notes();

	// Generate table contents for page.

	echo "<TABLE>\n";
	$sep = "";
	echo "<TR>\n";
	$sql = "";
	$table = $refData->getTable();
	foreach ($refData->getFields() as $i=>$field) {
		if (!is_null($fSql = $field->getSql($table))) {
			$sql .= $sep . $fSql;
			$sep = ", ";
		}
		echo "<TH>"
			. "<SMALL>" . str_replace("_", "<BR/>", $field->getName()) . "<BR/>"
			. "<SMALL>"
			. makeSortLink($i, 'U', '<B>&uArr;</B>')
			. "&nbsp;"
			. makeSortLink($i, '-', '<B>&dArr;</B>')
			. "</SMALL>"
			. "</SMALL></TH>\n";
	}
	echo "</TR>\n";
	$sql = $refData->makeSql($sql, $table, $filterWhere);
echo "\n<!-- XXX $sql -->\n";
	$query = lm2_query($sql, __FILE__, __LINE__);
	$rownum = 0;

	$rows = array();
	while ($row = mysql_fetch_assoc($query)) {
		array_push($rows, $row);
	}

	// Has to be done after loading the rows so that to use or not to use JS decision can be made here.
	foreach ($refData->getFields() as $i=>$field) {
		if ($js = $field->buildJS()) {
			echo "<SCRIPT>\n$js\n</SCRIPT>\n";
		}
	}

	if (sscanf($sortOrder, "%c%d", $sortDir, $sortField) == 2) {
		// Why can't we do this in a single line?!
		$fields = $refData->getFields();
		$sortField = $fields[$sortField];

		usort($rows, "rowSorter");
	}

	foreach ($rows as $row) {
		make_row($row, $refData, $rownum++);
	}

	if (!is_null($row = $refData->addRow())) {
		for ($i = 0; $i < 10; ++$i) {
			make_row($row, $refData, $rownum++);
		}
	}
	mysql_free_result($query);

	echo "</TABLE>\n";
}

function make_row($row, $refData, $rownum) {
		echo "<TR>\n";
		foreach ($refData->getFields() as $i=>$field) {
			echo "  <TD ALIGN=\"RIGHT\"><NOBR>" . $field->render($row, $rownum) . "</NOBR></TD>\n";
		}
		echo "</TR>\n";
}

if (!is_null($refData)) {
	echo "<INPUT TYPE=SUBMIT VALUE='Update'>\n";
}
?>
</FORM>
<SCRIPT>
BUL_TIMECOMPONENT = true;
function showCal(field) {
	if (!field.myCalControl) {
		field.myCalControl = new calendar1(field);
	}
	field.myCalControl.popup();
}
</SCRIPT>