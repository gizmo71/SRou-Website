<?php
require_once("$sourcedir/Subs-Post.php");
//require_once("../smf/SSI.php");
//require_once("include.php"); // In case we're coming in for a redirect...
?>

<FORM ENCTYPE="multipart/form-data" METHOD="POST" NAME="rdForm">
<?php
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

$carRefDataFieldFKSQL = "SELECT id_car AS id, CONCAT(manuf_name, ' ', car_name) AS description
	FROM {$lm2_db_prefix}cars
	JOIN {$lm2_db_prefix}manufacturers ON id_manuf = manuf
	ORDER BY description";

function driverRefDataFieldFKSQL($live) {
	global $lm2_db_prefix, $db_prefix, $guest_member_id;
	return "
		SELECT driver_member AS id
		, CONCAT(driver_name, IF(driver_member > 10000000, ' (UKGPL historic)', IF(id_member IS NULL, ' (defunct)',
			IF(driver_member <> $guest_member_id AND member_name <> real_name, CONCAT(' (', member_name, ')'), '')))) AS description
		, 1 AS is_html
		, driver_member " . ($live ? ">" : "<=") . " 10000000 AS hide
		FROM {$lm2_db_prefix}drivers
		LEFT JOIN {$db_prefix}members ON id_member = driver_member
		ORDER BY description";
}

function teamRefDataFieldFKSQL($live) {
	global $lm2_db_prefix;
	return "
		SELECT id_team AS id
		, CONCAT(team_name, CASE team_is_fake WHEN 2 THEN ' (UKGPL)' WHEN 1 THEN ' (admin)' ELSE '' END) AS description
		, team_is_fake " . ($live ? " <> 0" : " <> 2") . " AS hide
		FROM {$lm2_db_prefix}teams
		ORDER BY description";
}

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

// Note that this one is still in live use...
class CarRatings extends RefData {
	function getName() { return "CarRatings"; }
	function getTable() { return "{$this->lm2_db_prefix}car_ratings"; }

	function getFields() {
		return Array(
			new RefDataFieldID("id_car_rating", true),
			new RefDataFieldFK("rating_scoring_scheme", scoringSchemeRefDataFieldFKSQL("scoring_type = 'T'"), false, "12em"),
			new RefDataFieldFK("rated_car", $GLOBALS['carRefDataFieldFKSQL']),
			new RefDataFieldEdit("rating", 5), //FIXME: need a numeric field type - DECIMAL(3,1) in this case.
		);
	}

	function getFilters() {
		global $smcFunc;

		$filters = array(
			''=>array('name'=>'None', 'predicate'=>'1'),
			's'=>array('name'=>'Scheme', 'nested'=>array()),
		);

		$query = lm2_query("
			SELECT id_scoring_scheme AS id, scoring_scheme_name AS description
			FROM {$this->lm2_db_prefix}scoring_schemes
			JOIN " . $this->getTable() . " ON id_car_rating = id_scoring_scheme
			", __FILE__, __LINE__);
		while ($row = $smcFunc['db_fetch_assoc']($query)) {
			$filters['s']['nested']["s{$row['id']}"] = array('name'=>$row['description'], 'predicate'=>"rating_scoring_scheme = " . sqlString($row['id']));
		}
		$smcFunc['db_free_result']($query);

		return $filters;
	}

	function getDefaultSortOrder() {
		return "-3";
	}

	function addRow() {
		global $filterId;
		$defaultScheme = substr($filterId, 0, 1) == 's' ? substr($filterId, 1) : null;
		return array('rating_scoring_scheme'=>$defaultScheme);
	}
}

class MapBase extends RefData {
	function getTable() { global $lm2_ukgpl_prefix; return "{$lm2_ukgpl_prefix}_map_" . $this->getBaseEntity() . "s"; }
	
	function mapify($targetTable, $targetField, $joins = "") {
		global $smcFunc;

		lm2_query("
			UPDATE IGNORE $targetTable
			$joins
			JOIN " . $this->getTable() . " ON hist_" . $this->getBaseEntity() . " = $targetTable.$targetField AND approved
			SET $targetTable.$targetField = live_" . $this->getBaseEntity() . "
			", __FILE__, __LINE__);
		printf(" %d&nbsp;<TT>%s</TT>...", $smcFunc['db_affected_rows'](), $targetTable);
	}

	function checkForOverlap() {
		global $lm2_db_prefix, $smcFunc;

		$hist = "hist_" . $this->getBaseEntity();
		$live = "live_" . $this->getBaseEntity();

		$count = 0;

		// It's really important to verify that we're not merging two members or teams who appear in the same championship!
		// We only do event points because champ points always follow as a result.
		$query = lm2_query("
			SELECT full_desc, id_championship, champ_type, champ_class_desc, $live, GROUP_CONCAT(DISTINCT $hist) AS hist, COUNT(DISTINCT ep.id) AS dep
			FROM {$lm2_db_prefix}event_points AS ep
			JOIN {$lm2_db_prefix}championships ON championship = id_championship AND champ_type = '" . $this->getChampType() . "'
			JOIN {$lm2_db_prefix}event_groups ON event_group = id_event_group
			JOIN " . $this->getTable() . " ON ep.id IN ($hist, $live)
			GROUP BY id_championship, $live
			HAVING dep > 1
			", __FILE__, __LINE__);
		while ($row = $smcFunc['db_fetch_assoc']($query)) {
			echo "<BR/>" . print_r($row, true);
			++$count;
		}
		$smcFunc['db_free_result']($query);

		return $count;
	}

	function rebuild() {
		if ($this->checkForOverlap() == 0) {
			echo "<P>Migrating " . $this->getBaseEntity() . " data...\n";
			$this->rebuildActual();
			echo " done</P>\n";
		}
	}

	function getFilters() {
		global $lm2_db_prefix;
		return array(
			'u'=>array('name'=>"Unapproved", 'predicate'=>"NOT approved"),
			'a'=>array('name'=>"Approved", 'predicate'=>"approved"),
			'a'=>array('name'=>"Unprocessed", 'predicate'=>"hist_" . $this->getBaseEntity(). " IN (
				SELECT id FROM {$lm2_db_prefix}event_points
				JOIN {$lm2_db_prefix}championships ON championship = id_championship AND champ_type = '" . $this->getChampType() . "')"),
			''=>array('name'=>"None", 'predicate'=>"1"),
		);
	}

	function addRow() {
		return array();
	}

	function getDefaultSortOrder() {
		return "U1";
	}
}

class TeamMap extends MapBase {
	function getName() { return "TeamMap"; }
	function getBaseEntity() { return "team"; }
	function getChampType() { return "T"; }

	function getFields() {
		return Array(
			new RefDataFieldID("id_map_teams", true),
			new RefDataFieldFK("hist_team", teamRefDataFieldFKSQL(false), false, "30em"),
			new RefDataFieldFK("live_team", teamRefDataFieldFKSQL(true), false, "30em"),
			new RefDataFieldFK("approved", array(0=>'No', 1=>'Yes')),
		);
	}

	function rebuildActual() {
		global $lm2_db_prefix;

		$this->mapify("{$lm2_db_prefix}event_entries", "team");
		$champJoins = "JOIN {$lm2_db_prefix}championships ON id_championship = championship AND champ_type = '" . $this->getChampType() . "'";
		$this->mapify("{$lm2_db_prefix}event_points", "id", $champJoins);
		$this->mapify("{$lm2_db_prefix}championship_points", "id", $champJoins);
	}
}

class RefDataFieldFKDriver extends RefDataFieldFK {
	function __construct($name, $isLive) {
		parent::__construct($name, driverRefDataFieldFKSQL($isLive), false, "30em");
	}

	function render($row, $rownum) {
		global $lm2_guest_member_id, $boardurl;
		$html = parent::render($row, $rownum);
		$id_selected = $row[$this->getName()];
		$html .= $id_selected ? "<a href='$boardurl/index.php?action=profile&u=$lm2_guest_member_id&sa=racing_history&driver=$id_selected#aliases'>*</a>" : "-";
		return $html;
	}
}

class DriverMap extends MapBase {
	function getName() { return "DriverMap"; }
	function getBaseEntity() { return "driver"; }
	function getBaseName() { return "driver"; }
	function getChampType() { return "D"; }

	function getFields() {
		return Array(
			new RefDataFieldID("id_map_drivers", true),
			new RefDataFieldFKDriver("hist_driver", false),
			new RefDataFieldFKDriver("live_driver", true),
			new RefDataFieldFK("approved", array(0=>'No', 1=>'Yes')),
		);
	}

	function rebuildActual() {
		global $lm2_db_prefix;

		$this->mapify("{$lm2_db_prefix}team_drivers", "member");
		$this->mapify("{$lm2_db_prefix}event_entries", "member");
		$champJoins = "JOIN {$lm2_db_prefix}championships ON id_championship = championship AND champ_type = '" . $this->getChampType() . "'";
		$this->mapify("{$lm2_db_prefix}event_points", "id", $champJoins);
		$this->mapify("{$lm2_db_prefix}championship_points", "id", $champJoins);
		$this->mapify("{$lm2_db_prefix}event_ballasts", "eb_driver");
		$this->mapify("{$lm2_db_prefix}money", "member");
		$this->mapify("{$lm2_db_prefix}sim_drivers", "member");
	}

	function getFilters() {
		$filters = parent::getFilters();
		$filters['n'] = array('name'=>"Non-historic", 'predicate'=>"hist_driver <= 10000000");
		return $filters;
	}
}

function teamIsDeletable($id_team, $row) {
	global $lm2_db_prefix, $smcFunc;

	$entries = 0;

	$query = lm2_query("SELECT COUNT(*) AS entries FROM {$lm2_db_prefix}event_entries WHERE team = $id_team", __FILE__, __LINE__);
	while ($row = $smcFunc['db_fetch_assoc']($query)) {
		$entries += max($entries, $row['entries']);
	}
	$smcFunc['db_free_result']($query);

	$query = lm2_query("SELECT COUNT(*) AS entries FROM {$lm2_db_prefix}team_drivers WHERE team = $id_team", __FILE__, __LINE__);
	while ($row = $smcFunc['db_fetch_assoc']($query)) {
		$entries += max($entries, $row['entries']);
	}
	$smcFunc['db_free_result']($query);

	return $entries == 0;
}

class Teams extends RefData {
	function getName() { return "Teams"; }
	function getTable() { global $lm2_db_prefix; return "{$lm2_db_prefix}teams"; }

	function getFields() {
		return Array(
			new RefDataFieldID("id_team", 'teamIsDeletable($value, $row)'),
			new RefDataFieldEdit("team_name", 30, 50),
			new RefDataFieldEdit("url", 30, 150),
			new RefDataFieldFK("team_is_fake", array(0=>'No', 1=>'Administrative', 2=>'UKGPL Historic')),
			new RefDataFieldFK("created_by", driverRefDataFieldFKSQL(true), true, "30em"),
		);
	}

	function getFilters() {
		$filters = array(
			'f'=>array('name'=>'Administrative', 'predicate'=>"team_is_fake = 1"),
			'h'=>array('name'=>'UKGPL Historic', 'predicate'=>"team_is_fake = 2"),
			'r'=>array('name'=>'Real',           'predicate'=>"team_is_fake = 0"),
		);

		return $filters;
	}

	function addRow() {
		global $ID_MEMBER;
		return array('team_is_fake'=>1, 'created_by'=>$ID_MEMBER);
	}
}

$refDatas = Array(
	'crt'=>array('refData'=>new CarRatings()),
	'tea'=>array('refData'=>new Teams()),
	'tmp'=>array('refData'=>new TeamMap()),
	'dmp'=>array('refData'=>new DriverMap()),
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
			$html = '<A HREF="index.php?action=migration&refData=' . $name . '">' . $html . '</A>';
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
			$html .= "<SELECT onChange='top.location.href = \"index.php?action=migration&refData={$_REQUEST['refData']}&rdFilt=\" + value + \"&sortOrder={$_REQUEST['sortOrder']}\"'>\n";
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
				$html = "<A HREF=\"index.php?action=migration&refData={$_REQUEST['refData']}&rdFilt=$id&sortOrder={$_REQUEST['sortOrder']}\">$html</A>";
			} else {
				$filterWhere = $details['predicate'];
			}
		}
		echo "\n<NOBR>$html</NOBR>";
	}
}
echo "</SMALL></P>\n";

if ($refData) {
	$refData->show_notes();
}

echo "<TABLE>\n";

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
		$baseUrl = "index.php?action=migration&refData={$_REQUEST['refData']}&rdFilt=$filterId";
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

	// Generate table contents for page.

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
	while ($row = $smcFunc['db_fetch_assoc']($query)) {
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
	$smcFunc['db_free_result']($query);
}

function make_row($row, $refData, $rownum) {
		echo "<TR>\n";
		foreach ($refData->getFields() as $i=>$field) {
			echo "  <TD ALIGN=\"RIGHT\"><NOBR>" . $field->render($row, $rownum) . "</NOBR></TD>\n";
		}
		echo "</TR>\n";
}

echo "</TABLE>\n";

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
