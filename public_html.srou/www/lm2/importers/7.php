<?php
// Importer for Race '07

function showFileChoosers() {
?>
    <TR><TD COLSPAN="4"><I>For Race '07 double headers, first import Qual.txt and Race1.txt against race 1, and generate standings.
      <BR/>Then import just Race2.txt against race 2, and use the 'set starting positions' link which appears after import.
      <BR/>Then generate standings again.</I></TD></TR>
    <TR><TD>Qual</TD><TD><INPUT size="120" name="qual" type="file" /></TD></TR>
    <TR><TD><B>Race</B></TD><TD><INPUT size="120" name="race" type="file" /></TD></TR>
<?php
}

function check_sim_header($simName, $version, $isRace) {
	($simName == 'RACE 07') || die("Not a recognised Race '07 import $simName");
	$versions = '/^(1\.0\.[12]|1\.1\.0|1\.2\.1)\.\d+$/i';
	preg_match($versions, $version) || die("Unsupported version of Race '07 (must match $versions)");
}

include("isiini.php");

function hackSlot(&$slot) {
	//$slot['Lobby Username'] = "{$slot['SteamId']}/{$slot['SteamUser']}";
	// Unfortunately the steam information is set to 0/empty string when a user disconnects before the end of the session. Useless.
	$slot['Lobby Username'] = $slot['Driver']; // So we replicate from the early days of the Race '07 importer.
}
?>