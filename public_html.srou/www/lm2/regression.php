<?php

class RegressionTester {

	var $lm2_db_prefix;
	var $regr_db_prefix = 'gizmo71_regr.lm2_';
	var $temp_db_prefix;

	var $tables;

	function RegressionTester() {
		$this->lm2_db_prefix = $GLOBALS['lm2_db_prefix'];
		$this->temp_db_prefix = "{$this->lm2_db_prefix}TEMP_R_";

		$this->tables = array(
			"championship_points"=>array(
				'keys' => array("championship", "id"),
				'data' => array("points", "position", "champ_points_lost"),
				'debug' => array("tie_breaker", "tokens", "single_car"),
				'filter' => "championship IN (SELECT id_championship FROM {$this->regr_db_prefix}championships)",
			),
			"event_points" => array(
				'keys' => array("championship", "id", "event_entry"),
				'data' => array("points", "position", "is_dropped", "ep_penalty_points"),
				'debug' => array(),
				'filter' => "championship IN (SELECT id_championship FROM {$this->regr_db_prefix}championships)",
			),
			"event_entries" => array(
				'keys' => array("event", "member", "id_event_entry"),
				'data' => array("qual_pos", "race_pos", "race_time_adjusted", "penalty_points", "team", "race_pos_penalty"),
				'debug' => array("excluded_c", "car_class_c", "race_pos_tie_break"),
			),
			"penalties" => array(
				'keys' => array("id_penalty"),
				'data' => array("extra_positions_lost"),
				'debug' => array("event_entry"),
				),
		);
	}

	function check() {
/*
		echo "<P STYLE='color: blue'>Hacking data:";
		db_query("
			DELETE FROM {$this->lm2_db_prefix}championship_points
			WHERE id = 1
			", __FILE__, __LINE__);
		echo " " . db_affected_rows();
		db_query("
			INSERT INTO {$this->lm2_db_prefix}championship_points
			(championship, id, points, position)
			VALUES	(666, 666, 42, 69)
			,	(10, 1, 13, 7)
			,	(39, 1, 175, 180)
			,	(48, 1, 1234, 1)
			", __FILE__, __LINE__);
		echo " " . db_affected_rows();
		db_query("
			DELETE FROM {$this->lm2_db_prefix}event_points
			WHERE id = 1 AND championship = 38
			", __FILE__, __LINE__);
		echo " " . db_affected_rows();
		echo "</P>\n";
*/

		echo "<P>Comparing regression test data... (does not do any standings generation)</P>\n";

		$unmatched = 0;
		$maxShown = 10;

		foreach ($this->tables as $table => $fields) {
			$field_list = "";
			$sep = "";
			foreach (array_merge($fields['keys']) AS $field) {
				$field_list .= "$sep a.$field";
				$sep = ",";
			}
			foreach (array_merge($fields['data'], $fields['debug']) AS $field) {
				$field_list .= "$sep n.$field AS {$field}_NEW";
				$sep = ",";
				$field_list .= "$sep r.$field AS {$field}_OLD";
			}

			db_query("
				CREATE TEMPORARY TABLE {$this->temp_db_prefix}all_entries
				(INDEX (" . implode(", ", $fields['keys']) . "))
				SELECT DISTINCT " . implode(", ", $fields['keys']) . " FROM {$this->regr_db_prefix}$table
				UNION DISTINCT
				SELECT DISTINCT " . implode(", ", $fields['keys']) . " FROM {$this->lm2_db_prefix}$table
				" . (array_key_exists('filter', $fields) ? "WHERE {$fields['filter']}" : "") . "
				", __FILE__, __LINE__);

			$using = "USING (". implode(", ", $fields['keys']) . ")";
			$where = "WHERE NOT (";
			$sep = "";
			foreach ($fields['data'] AS $field) {
				$where .= "{$sep}r.$field <=> n.$field";
				$sep = " AND ";
			}
			$where .= ")";

			$sql = "
				SELECT $field_list
				FROM {$this->temp_db_prefix}all_entries AS a
				LEFT JOIN {$this->regr_db_prefix}$table AS r USE INDEX (PRIMARY) $using
				LEFT JOIN {$this->lm2_db_prefix}$table AS n USE INDEX (PRIMARY) $using
				$where
				ORDER BY 1, 2, 3
				";
			echo "<PRE>$sql</PRE>\n"; ob_flush();

			($query = lm2_query("$sql LIMIT " . ($maxShown + 1), __FILE__, __LINE__)) || die("failed to run $sql");
			$localUnmatched = 0;
			$sep = "<PRE>";
			$closer = '';
			while ($row = mysql_fetch_assoc($query)) {
				$this->squishUnchanged($row, array_merge($fields['data'], $fields['debug']));
				echo $sep . print_r($row, true)  . "\n"; ob_flush();
				$sep = "<BR/>";
				$closer = "</PRE>";
				++$unmatched;
				if (++$localUnmatched >= $maxShown) {
					echo "<BR/><I STYLE='color: purple'>There are more rows...</I>\n";
					break; // Don't get carried away!
				}
			}
			echo $closer;
			mysql_free_result($query);

			db_query("DROP TEMPORARY TABLE {$this->temp_db_prefix}all_entries", __FILE__, __LINE__);
		}

		if ($unmatched > 0) {
			echo "<P><B STYLE='color: red'>At least $unmatched mismatched rows</B></P>\n";
		} else {
			echo "<P><B STYLE='color: green'>Passed!</B></P>\n";
		}
	}

	function squishUnchanged(&$row, $fields) {
		foreach ($fields as $field) {
			if ($row["{$field}_NEW"] === $row["{$field}_OLD"]) {
				$row["{$field}"] = $row["{$field}_NEW"];
				unset($row["{$field}_NEW"], $row["{$field}_OLD"]);
			}
		}
	}

}

(new RegressionTester())->check();

?>
