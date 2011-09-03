<?php
// Importer for GTR2

function showFileChoosers() {
	global $lm2_db_prefix;
	show_mod_selector();
?>
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