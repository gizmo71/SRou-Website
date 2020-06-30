<?php

$tables = array(
	"{$db_prefix}log_boards" => array(
		identity => array('ID_BOARD', 'ID_MEMBER'),
		maxNums => array('ID_MSG')
	),
	"{$db_prefix}log_topics" => array(
		identity => array('ID_TOPIC', 'ID_MEMBER'),
		maxNums => array('ID_MSG')
	),
	"{$db_prefix}members" => array(
		identity => array('ID_MEMBER'),
		maxNums => array('ID_MSG_LAST_VISIT', 'totalTimeLoggedIn', 'lastLogin', 'posts')
//TODO: surely "posts" should be included as part of the "recount" page? Does it not work?
	),
);

foreach ($tables as $table => $params) {
	echo "<b>$table</b>...\n";
	$query = lm2_query(($sql = "
		SELECT " . implode(", ", array_merge($params['identity'], $params['maxNums'])) . "
		FROM $table
		"), __FILE__, __LINE__);
echo "<pre>$sql</pre>";
	while ($row = mysql_fetch_assoc($query)) {
		foreach ($params['maxNums'] as $maxNum) {
			$sql = "UPDATE $table
				SET $maxNum = {$row[$maxNum]}
				WHERE $maxNum < {$row[$maxNum]}";
			foreach ($params['identity'] as $col) {
				$sql .= " AND $col = {$row[$col]}";
			}
			lm2_query($sql, __FILE__, __LINE__);
//if ($row['ID_MEMBER'] == 1) echo "<pre>\tUpdated " . print_r($row, true) . " in log_boards with $sql</pre>\n";
		}
	}
	mysql_free_result($query);
}

?>

<p>Updates complete.</p>
