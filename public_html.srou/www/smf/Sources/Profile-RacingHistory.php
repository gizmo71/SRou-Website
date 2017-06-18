<?php

function lm2ProfileRacingHistory($memID) {
	global $context, $memberContext, $smcFunc;

	$driver = isset($_REQUEST['driver']) && is_numeric($_REQUEST['driver']) ? $_REQUEST['driver'] : $memID;

	$query = $smcFunc['db_query'](null, "
		SELECT driver_member, driver_name, id_member
		FROM {lm2_prefix}drivers
		LEFT JOIN {db_prefix}members ON id_member = driver_member
		WHERE driver_member = {int:driver}
		", array('driver'=>$driver));
	($context['lm2']['driver'] = $smcFunc['db_fetch_assoc']($query)) || die("unknown driver $driver");
	$smcFunc['db_free_result']($query);

	$context['page_title'] = "Racing History - {$context['lm2']['driver']['driver_name']}";
	if (!$context['lm2']['driver']['id_member']) {
		$context['page_title'] .= " (no longer registered)";
	}

	loadTemplate('Profile-RacingHistory');
}

//XXX: refactor all queries from below to above

function lm2ShowDriverProfile($driver) {
	global $lm2_circuit_link_clause, $lm2_circuit_html_clause, $lm2_penalty_points_clause;
	global $colsep;
	global $user_info, $context, $boardurl, $smcFunc;
	global $context, $lm2_db_prefix, $db_prefix;

	$ID_MEMBER = $user_info['id'];
	
	echo lm2_table_open("Aliases");
?><A NAME="aliases"></A>
	<TABLE BORDER='1' CELLPADDING='2' CELLSPACING='0'>
	<TR><TH>Sims</TH><TH>Driving Names</TH><TH>Lobby Names</TH></TR>
<?php
	$query = $smcFunc['db_query'](null, "SELECT sim_name, driving_name, lobby_name
		FROM {lm2_prefix}sim_drivers
		JOIN {lm2_prefix}sims ON sim = id_sim
		WHERE member = {int:driver}
		", array('driver'=>$driver));
	while ($row = $smcFunc['db_fetch_assoc']($query)) {
		echo "<TR><TD>";
		echo $row['sim_name'];
		echo "</TD><TD>";
		if ($name = $row['driving_name']) {
			echo $name;
		}
		echo "</TD><TD>";
		if ($name = $row['lobby_name']) {
			echo $name;
		}
		echo "</TD></TR>\n";
	}
	$smcFunc['db_free_result']($query);
?>
	</TABLE>
<?php
	echo lm2_table_close();

	$header = lm2_table_open("Championship Registrations and Licenses") . "<TABLE>\n";
	$footer = "";
	$query = $smcFunc['db_query'](null, "
		SELECT group_name, champ_group_type, id_event_group, full_desc, series_theme, champ_class_desc
		FROM {lm2_prefix}champ_groups
		JOIN {lm2_prefix}championships ON id_championship = champ_group_champ
		JOIN {lm2_prefix}event_groups ON id_event_group = event_group
		JOIN {db_prefix}members
		JOIN {db_prefix}membergroups AS g ON g.id_group = champ_group_membergroup
		WHERE id_member = {int:driver}
		AND CONCAT(',', additional_groups, ',') REGEXP CONCAT(',', g.id_group, ',')
		GROUP BY g.id_group, id_event_group
		ORDER BY champ_sequence
		" , array('driver'=>$driver));
	while (($row = $smcFunc['db_fetch_assoc']($query))) {
		echo $header;
		$header = "";
		$footer = "</TABLE>\n" . lm2_table_close();
		switch ($row['champ_group_type']) {
		case 'F':
			$type = 'Full Time';
			break;
		case '1':
			$type = 'First Reserve';
			break;
		case '2':
			$type = 'Second Reserve';
			break;
		case '2':
			$type = 'Third Reserve';
			break;
		case 'L':
			$type = 'Licensed';
			break;
		default:
			$type = $row['champ_group_type'];
		}
		echo "<TR TITLE='{$row['group_name']}'>
			<TD>" . lm2MakeEventGroupLink($row['id_event_group'], $row['full_desc'], $row['series_theme']) . "</TD>
			<TD>{$row['champ_class_desc']}</TD>
			<TD>$type</TD>
			</TR>\n";
	}
	echo $footer;
	$smcFunc['db_free_result']($query);
	
	$header = lm2_table_open("Team History") . "<TABLE><TR><TH>Team</TH>$colsep<TH>Series</TH>$colsep<TH>Joined</TH>$colsep<TH>Left</TH></TR>\n";
	$footer = "";
	$query = $smcFunc['db_query'](null, "
		SELECT team_name, date_from, date_to, id_team, short_desc
		FROM ({lm2_prefix}teams, {lm2_prefix}team_drivers)
		LEFT JOIN {lm2_prefix}event_groups ON event_group = id_event_group
		WHERE team = id_team
		  AND date_from IS NOT NULL
		  AND member = {int:driver}
		ORDER BY date_from, date_to
		", array('driver'=>$driver));
	while ($row = $smcFunc['db_fetch_assoc']($query)) {
		echo $header;
		$header = "";
		$footer = "</TABLE>\n" . lm2_table_close();
		if (is_null($series = $row['short_desc'])) {
			$series = "<I>all</I>";
		}
		echo "<TR><TD>" . lm2FormatTeam($row['id_team'], $row['team_name']) . "</TD>"
			. "$colsep<TD>$series</TD>"
			. "$colsep<TD>" . lm2FormatTimestamp(lm2Timestamp2php($row['date_from']), false) . "</TD>"
			. "$colsep<TD>" . lm2FormatTimestamp(lm2Timestamp2php($row['date_to']), false) . "</TD>"
			. "</TR>\n";
	}
	$smcFunc['db_free_result']($query);
	echo $footer;

	echo lm2_table_open("Career Statistics") . "<TABLE>\n";
	$query = $smcFunc['db_query'](null, "SELECT COUNT(*) AS events_entered"
		. " FROM {lm2_prefix}event_entries"
		. " WHERE member = $driver",
		__FILE__, __LINE__);
	while ($row = $smcFunc['db_fetch_assoc']($query)) {
		echo "<TR><TD>Events entered</TD>$colsep<TD COLSPAN=3 ALIGN=RIGHT>{$row['events_entered']}</TD>\n";
	}
	$smcFunc['db_free_result']($query);
	
	echo "<TR><TD></TD>$colsep<TH ALIGN=RIGHT>Outright</TH>$colsep<TH ALIGN=RIGHT>In class</TH>\n";
	$query = $smcFunc['db_query'](null, "
		SELECT MIN(race_pos) AS best_race_pos
		, MIN(race_pos_class) AS best_race_pos_class
		, AVG(race_pos) AS avg_race_pos
		, AVG(race_pos_class) AS avg_race_pos_class
		, MIN(qual_pos) AS best_qual_pos
		, MIN(qual_pos_class) AS best_qual_pos_class
		, MAX(start_pos - race_pos) AS best_climb
		, MAX(start_pos_class - race_pos_class) AS best_climb_class
		, AVG(start_pos - race_pos) AS avg_climb
		, AVG(start_pos_class - race_pos_class) AS avg_climb_class" /*FIXME: add this!*/ . "
		, SUM(IF(qual_pos=1,1,0)) AS poles
		, SUM(IF(qual_pos_class=1,1,0)) AS poles_class
		, SUM(IF(race_pos=1,1,0)) AS wins
		, SUM(IF(race_pos_class=1,1,0)) AS wins_class
		, SUM(IF(race_pos IN (1,2,3),1,0)) AS podiums
		, SUM(IF(race_pos_class IN (1,2,3),1,0)) AS podiums_class
		, SUM(IF(race_best_lap_pos=1,1,0)) AS fastest
		, SUM(IF(race_best_lap_pos_class=1,1,0)) AS fastest_class
		FROM {lm2_prefix}event_entries WHERE member = $driver
		", __FILE__, __LINE__); //FIXME: consider only including non-fun race event_types
	while ($row = $smcFunc['db_fetch_assoc']($query)) {
		echo "<TR><TD>Best qualifying position</TD>$colsep<TD ALIGN=RIGHT>{$row['best_qual_pos']}</TD>"
			. "$colsep<TD ALIGN=RIGHT>{$row['best_qual_pos_class']}</TD></TR>\n";
		echo "<TR><TD>Poles</TD>$colsep<TD ALIGN=RIGHT>{$row['poles']}</TD>"
			. "$colsep<TD ALIGN=RIGHT>{$row['poles_class']}</TD></TR>\n";
		echo "<TR><TD>Best finishing position</TD>$colsep<TD ALIGN=RIGHT>{$row['best_race_pos']}</TD>"
			. "$colsep<TD ALIGN=RIGHT>{$row['best_race_pos_class']}</TD></TR>\n";
		echo "<TR><TD>Wins</TD>$colsep<TD ALIGN=RIGHT>{$row['wins']}</TD>"
			. "$colsep<TD ALIGN=RIGHT>{$row['wins_class']}</TD></TR>\n";
		echo "<TR><TD>Podiums</TD>$colsep<TD ALIGN=RIGHT>{$row['podiums']}</TD>"
			. "$colsep<TD ALIGN=RIGHT>{$row['podiums_class']}</TD></TR>\n";
		echo "<TR><TD>Average finishing position</TD>$colsep<TD ALIGN=RIGHT>{$row['avg_race_pos']}</TD>"
			. "$colsep<TD ALIGN=RIGHT>{$row['avg_race_pos_class']}</TD></TR>\n";
		echo "<TR><TD>Most positions gained</TD>$colsep<TD ALIGN=RIGHT>{$row['best_climb']}</TD>"
			. "$colsep<TD ALIGN=RIGHT>{$row['best_climb_class']}</TD></TR>\n";
		echo "<TR><TD>Average positions gained</TD>$colsep<TD ALIGN=RIGHT>{$row['avg_climb']}</TD>"
			. "$colsep<TD ALIGN=RIGHT>{$row['avg_climb_class']}</TD></TR>\n";
		echo "<TR><TD>Fastest laps</TD>$colsep<TD ALIGN=RIGHT>{$row['fastest']}</TD>"
			. "$colsep<TD ALIGN=RIGHT>{$row['fastest_class']}</TD></TR>\n";
	}
	$smcFunc['db_free_result']($query);

	lm2MakeChampStats('D', $driver);

	echo lm2_table_close() . "</TABLE>\n";

	lm2ShowLapRecords($driver, null, null, null);
	
	// License endorsements.
	$query = $smcFunc['db_query'](null, "
		SELECT SUM($lm2_penalty_points_clause) AS penalty_points
		, $lm2_circuit_html_clause AS circuit_html
		, id_event_group
		, id_event, smf_topic
		, short_desc
		, event_date
		, IFNULL(victim_report, 'Y') AS victim_report
		, penalty_group_desc
		, report_published AS start_date
		, DATE_ADD(report_published, INTERVAL penalty_group_months MONTH) AS end_date
		FROM {lm2_prefix}event_entries
		JOIN {lm2_prefix}events ON id_event = event
		JOIN {lm2_prefix}penalties ON event_entry = id_event_entry
		JOIN {lm2_prefix}sim_circuits ON id_sim_circuit = sim_circuit
		JOIN {lm2_prefix}circuits ON id_circuit = circuit
		JOIN {lm2_prefix}circuit_locations ON id_circuit_location = circuit_location
		JOIN {lm2_prefix}event_groups ON id_event_group = event_group
		JOIN {lm2_prefix}penalty_groups USING (penalty_group)
		WHERE $driver = member
		AND event_status IN ('O', 'H')
		GROUP BY penalty_group, id_event, IFNULL(victim_report, 'Y')
		HAVING penalty_points > 0
		AND end_date >= " . lm2Php2timestamp() . "
		", __FILE__, __LINE__);
	$closer = "";
	$currentGroupDesc = null;
	while ($row = $smcFunc['db_fetch_assoc']($query)) {
		$url = lm2MakeEventLink($row['id_event'], $row['smf_topic']);
		if ($currentGroupDesc != $row['penalty_group_desc']) {
			$currentGroupDesc = $row['penalty_group_desc'];
			echo $closer
				. lm2_table_open("Current License Endorsements - $currentGroupDesc")
				. "<TABLE>\n<TR><TH>Series</TH><TH>Circuit</TH><TH>Date</TH><TH ALIGN='LEFT' COLSPAN='2'>Penalty Points</TH><TH>Expires</TH></TR>\n";
			$closer = "</TABLE>\n" . lm2_table_close();
		}
		echo "<TR>
			<TD>$url{$row['short_desc']}</A></TD>
			<TD>$url{$row['circuit_html']}</A></TD>
			<TD>$url" . lm2FormatTimestamp(lm2Timestamp2php($row['event_date']), false) . "</A></TD>
			<TD ALIGN='RIGHT'>$url{$row['penalty_points']}</A></TD>
			<TD>" . ($row['victim_report'] != 'Y' ? "<I>(reduced to caution)</I>" : "") . "</TD>
			<TD TITLE='Issued " . lm2FormatTimestamp(lm2Timestamp2php($row['start_date']), true) . "'>" . lm2FormatTimestamp(lm2Timestamp2php($row['end_date']), true) . "</TD>
			</TR>\n";
	}
	echo $closer;
	$smcFunc['db_free_result']($query);

	lm2MakeEventList("member", $driver, "Full Event Entry List");
}

?>
