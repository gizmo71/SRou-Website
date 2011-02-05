<?php
// Importer for GTR2

function showFileChoosers() {
	global $lm2_db_prefix;
?>
    <TR><TD>Mod/class</TD><TD><SELECT name="mod" onSelect="alert('foo\n' + form.submit_button);">
    	<OPTION VALUE="" SELECTED>Please select a mod...</OPTION>
<?php
	$query = db_query("
		SELECT type, mod_desc
		FROM {$lm2_db_prefix}sim_mods
		WHERE id_sim = 4
		", __FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($query)) {
		print "<OPTION VALUE='${row['type']}'>${row['mod_desc']}</OPTION>\n";
	}
	mysql_free_result($query);
?>
    </SELECT> <SPAN STYLE="color: red">You <B>must</B> select a mod before proceeding!</SPAN></TD></TR>
    <TR><TD>Race</TD><TD><INPUT size="120" name="race" type="file" /></TD></TR>
<?php
}

function check_sim_header($simName, $version, $isRace) {
	$versions = array('1.000', '1.100');
	($simName == 'GTR2' && array_search($version, $versions) !== false) || die("GTR2 importer supports only: " . implode(', ', $versions));
	$isRace || die("Only the race export is required for GTR2");
	global $vehicleType;
	(is_null($vehicleType) || $vehicleType == '') && die("no mod selected");
}

include("isiini.php");
?>