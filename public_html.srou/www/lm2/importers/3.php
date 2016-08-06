<?php
// Importer for GT Legends

function showFileChoosers() {
	show_mod_selector();
?>
    <TR><TD>Qual</TD><TD><INPUT size="120" name="qual" type="file" /></TD></TR>
    <TR><TD><B>Race</B></TD><TD><INPUT size="120" name="race" type="file" /></TD></TR>
<?php
}

function check_sim_header($simName, $version, $isRace) {
	$versions = array('1.100');
	($simName == 'GT Legends' && array_search($version, $versions) !== false) || die("GTL importer supports only: " . implode(', ', $versions) . " - got '$simName' '$version'");
}

include("isiini.php");
?>