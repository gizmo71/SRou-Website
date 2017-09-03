<?php require("../smf/SSI.php"); ?>
<?php
// LM2 KML feed
//XXX won't work after Feb 2015 - see https://developers.google.com/maps/support/kmlmaps - will need some sort of MyMaps thing

is_numeric($location = $_REQUEST['location']) || $location = 0;

require('include.php');

ob_start();

$doc = new DOMDocument('1.0', $context['character_set']);
$doc->appendChild($Document = $doc->createElement('Document'));
make_text_tag($doc, 'name', "SimRacing.org.uk circuit" . ($location ? "" : "s"), $Document);

$query = lm2_query("SELECT latitude_n"
	. ", longitude_e"
	. ", 0 AS elevation"
	. ", location_url"
	. ", brief_name"
	. ", full_name"
	. ", id_circuit"
	. ", id_circuit_location"
	. ", iso3166_code"
	. ", iso3166_name"
	. " FROM ${lm2_db_prefix}circuit_locations"
	. ", ${lm2_db_prefix}circuits"
	. ", ${lm2_db_prefix}iso3166"
	. " WHERE id_circuit_location = circuit_location"
	. " AND id_iso3166 = iso3166_code"
	. " AND " . ($location == 0 ? "latitude_n IS NOT NULL AND longitude_e IS NOT NULL" : "id_circuit_location = $location")
	. " GROUP BY id_circuit_location"
	. " ORDER BY iso3166_name, brief_name"
	, __FILE__, __LINE__);
$iso3166_code = null;
$Folder = null;
while ($row = $smcFunc['db_fetch_assoc']($query)) {
	if ($iso3166_code != $row['iso3166_code'] || !$Folder) {
		$Document->appendChild($Folder = $doc->createElement('Folder'));
		make_text_tag($doc, 'name', $row['iso3166_name'], $Folder);
		$iso3166_code = $row['iso3166_code'];
	}

//	if ($row['id_circuit_location'] == $location) {
//		$Document->appendChild($LookAt = $doc->createElement('LookAt'));
//		make_text_tag($doc, 'longitude', $row['longitude_e'], $LookAt);
//		make_text_tag($doc, 'latitude', $row['latitude_n'], $LookAt);
//	}

	$Folder->appendChild($Placemark = $doc->createElement('Placemark'));

	make_text_tag($doc, 'visibility', "1", $Placemark);
	make_text_tag($doc, 'name', $row['brief_name'], $Placemark);
	$desc = "{$row['full_name']}<BR/>"
		. "<A HREF='{$_SERVER['SROU_HOST_WWW']}/index.php?ind=lm2&location={$row['id_circuit_location']}'>SimRacing.org.uk Profile</A>";
	if ($row['location_url']) {
		$desc .= "\n<BR/><A HREF='{$row['location_url']}'>Official web site</A>";
	}
	make_cdata_tag($doc, 'description', $desc, $Placemark);

	$Placemark->appendChild($Point = $doc->createElement('Point'));
	make_text_tag($doc, 'coordinates', "{$row['longitude_e']},{$row['latitude_n']},{$row['elevation']}", $Point);
}
$smcFunc['db_free_result']($query);

// All the hard stuff done, bring it on home.

$comments = ob_get_clean();

header("Content-Type: application/vnd.google-earth.kml+xml; charset={$context['character_set']}");
header("Content-Disposition: inline; filename=SRou-location-$location.kml");
echo $doc->saveXML();
echo $comments;
?>
