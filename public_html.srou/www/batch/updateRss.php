<?php
require_once('../smf/SSI.php');

header('Content-Type: text/plain');

if (true) {
	echo "Weather generation disabled\r\n";
} else {
$query = db_query("
	SELECT DISTINCT wu_station
	, MAX(event_date BETWEEN DATE_SUB(NOW(), INTERVAL 2 DAY) AND DATE_ADD(NOW(), INTERVAL 10 DAY)) AS update_flag
	FROM {$lm2_db_prefix}circuit_locations
	JOIN {$lm2_db_prefix}circuits ON circuit_location = id_circuit_location
	JOIN {$lm2_db_prefix}sim_circuits ON circuit = id_circuit
	LEFT JOIN {$lm2_db_prefix}events ON sim_circuit = id_sim_circuit
	WHERE wu_station IS NOT NULL
	GROUP BY wu_station
	", __FILE__, __LINE__);
while ($row = mysql_fetch_assoc($query)) {
	$links = lm2MakeWeatherLinks($row);
	if ($row['update_flag']) {
		if ($contents = file_get_contents($links['rssUrl'])) {
			file_put_contents($links['rssFile'], $contents);
		}
		echo "{$links['rssUrl']} -> {$links['rssFile']} = " . strlen($contents) . "\r\n";
	} else if (file_exists($links['rssFile'])) {
		unlink($links['rssFile']) || die("failed to unlink {$links['rssFile']}");
		echo "X {$links['rssFile']}\r\n";
	} else {
		echo "- {$links['rssFile']}\r\n";
	}
}
mysql_free_result($query);
}
?>
