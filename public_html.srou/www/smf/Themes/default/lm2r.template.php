<?php
$modSettings['disableQueryCheck'] = true; // We use UNION and friends...

function template_main()
{
	global $_REQUEST;
	echo "<PRE>" . print_r($_REQUEST, true) . "</PRE>\n";
}

function template_teams() {
	echo lm2_table_open("Registered Teams");
	lm2ShowTeamMembers(null, false);
	echo lm2_table_close();
}

function template_team()
{
	global $context;

	echo lm2_table_open($context['page_title']);
	if ($url = $context['lm2']['team']['url']) {
		echo "<P><A HREF=\"$url\">Team Website</A></P>\n";
	}
	lm2ShowTeamMembers($context['lm2']['team']['id_team']);
	echo lm2_table_close();

	echo lm2_table_open("Statistics <A HREF='/lm2/rss.php?team={$context['lm2']['team']['id_team']}'>"
		. "<IMG ALT='RSS' BORDER=0 SRC='/images/RSS.gif' WIDTH=36 HEIGHT=14>") . "<TABLE>\n";
	lm2MakeChampStats('T', $context['lm2']['team']['id_team']);
	echo "</TABLE>\n" . lm2_table_close();

	// Change the defaults to be the opening/closing things and use the current defaults from the MkP index.
	lm2ShowLapRecords(null, null, null, null, $context['lm2']['team']['id_team']);

	//FIXME: merge the header/footer into the shared function.
	lm2MakeEventList("team", $context['lm2']['team']['id_team'], "Full Event Entry List");
}

function template_group() {

	function format_event_date($row) {
		$event_date = lm2FormatTimestamp(lm2Timestamp2php($row['event_date']), false);
		if ($row['entries'] == 0 && !$row['smf_topic']) {
			$event_date .= " (to be confirmed)";
		}
		return $event_date;
	}

	function show_championship($champ, $champ_type, $group, $events, $event_headers, $fullWidth, $penalty_group, $penalty_group_months) {
		global $lm2_db_prefix, $smcFunc, $boardurl;
		global $lm2_champ_types, $lm2_guest_member_id;

		if ($champ_type == 'D') { //FIXME or not: move this into the switch/case below...
			// Note that painfully we cannot use a temporary table here because we need to use it in both sides of the UNION below.
			$smcFunc['db_query'](null, "
				DELETE FROM {$lm2_db_prefix}registered_drivers WHERE rd_championship = {int:champ}
				", array('champ'=>$champ));
			$smcFunc['db_query'](null, "
				INSERT IGNORE INTO {$lm2_db_prefix}registered_drivers
				(rd_championship, member, name, url, rd_status, has_raced)
				SELECT DISTINCT id_championship AS rd_championship
				, id_member AS member
				, real_name AS name
				, CONCAT('index.php?ind=lm2&driver=', id_member) AS url
				, GROUP_CONCAT(DISTINCT CASE champ_group_type WHEN 'F' THEN '(FT)' WHEN '1' THEN '(R1)' WHEN '2' THEN '(R2)' WHEN '3' THEN '(R3)' ELSE champ_group_type END SEPARATOR '/') AS rd_status
				, MAX(IF(id IS NULL, 0, 1)) AS has_raced
				FROM {$lm2_db_prefix}champ_groups
				JOIN {$lm2_db_prefix}championships ON champ_group_champ = id_championship
				JOIN {db_prefix}members
				LEFT JOIN {$lm2_db_prefix}championship_points ON id_championship = championship AND id = id_member
				WHERE champ_group_champ = {int:champ} AND champ_group_poll_choice IS NOT NULL AND champ_group_type <> 'L'
				AND CONCAT(',', id_group, ',', additional_groups, ',') REGEXP CONCAT(',', champ_group_membergroup, ',')
				GROUP BY id_member
				HAVING rd_status IS NOT NULL
				", array('champ'=>$champ));
		}

		switch ($champ_type) {
		case 'M':
			$status = 'NULL';
			$table = "{$lm2_db_prefix}manufacturers";
			$id_field = 'id_manuf';
			$field = 'manuf_name';
			$url_field = 'manuf_url';
			$left_joins = '';
			$union = '';
			break;
		case 'T':
			$status = 'NULL';
			$table = "{$lm2_db_prefix}teams";
			$id_field = 'id_team';
			$field = 'team_name';
			$url_field = "CONCAT('$boardurl/index.php?action=LM2R&team=', id_team)";
			$left_joins = "
				LEFT JOIN {$lm2_db_prefix}manufacturers ON manuf = id_manuf
			";
			$union = '';
			break;
		case 'D':
			$status = "rd_status";
			$table = "{$lm2_db_prefix}drivers";
			$id_field = 'driver_member';
			$field = 'driver_name';
			$url_field = "CONCAT('$boardurl/index.php?action=profile&u=', IFNULL((
				SELECT id_member FROM {db_prefix}members WHERE id_member = driver_member), {int:guest_id}), '&area=racing_history&driver=', $id_field, '#aliases')";
			$left_joins = "
				LEFT JOIN {$lm2_db_prefix}registered_drivers ON member = id AND rd_championship = {int:champ}
				LEFT JOIN {$lm2_db_prefix}manufacturers ON manuf = id_manuf
			";
			$union = " UNION (
				SELECT member AS id, NULL, name, '&nbsp;'
				, CONCAT('$boardurl/index.php?action=profile&u=', member, '&area=racing_history&driver=', member, '#aliases') , $status
				, NULL AS penalty_points
				, NULL AS points_lost
				, NULL AS tokens
				, NULL AS single_car
				FROM {$lm2_db_prefix}registered_drivers
				WHERE NOT has_raced AND rd_championship = {int:champ})
				";
			break;
		default:
			echo "<TR><TH SPAN='$fullWidth'><I>Unknown championship type '$champ_type'</I></TH></TR>\n";
			return;
		}
		$ycp = true;

		global $_REQUEST;
		if (lm2ArrayValue($_REQUEST, 'scoring') == $champ) {
			echo "<TR><TD COLSPAN='$fullWidth'>" . make_scoring_desc($champ) . "</TD></TR>\n";
		}

		echo "<TR>
			<TH>Rank</TH>
			<TH" . ($ycp ? "" : " COLSPAN='2'") . ">{$lm2_champ_types[$champ_type]}</TH>\n
			" . ($ycp ? "<TH TITLE='Penalty Points (or championship points lost for UKGPL prior to Season 6)'>PP</TH>" : "") . "
			<TH>Total</TH>
			<TH>Vehicle</TH>
			$event_headers
			</TR>\n";

		$roundQueryResults = array();
		$query = $smcFunc['db_query'](null, "
			SELECT event, id
			, IFNULL(SUM(points),'&nbsp;') AS points
			" . ($champ_type == 'D' ? ", MIN(position) AS position, SUM(ep_penalty_points) AS pp" : ", NULL AS position, NULL AS pp") . "
			, is_dropped
			FROM {$lm2_db_prefix}event_points
			JOIN {$lm2_db_prefix}event_entries ON id_event_entry = event_entry
			WHERE championship = {int:champ}
			GROUP BY event, id
			", array('champ'=>$champ));
		while ($row = $smcFunc['db_fetch_assoc']($query)) {
			$roundQueryResults[$row['id']][] = $row;
		}
		$smcFunc['db_free_result']($query);

		$old_pos = -1;
		// The CAST is a nasty hack because PHP butchers large integers...
		$query = $smcFunc['db_query'](null, "SELECT * FROM (
			(SELECT CAST(id AS CHAR) AS id
			, position
			, $field AS name
			, IFNULL({$lm2_db_prefix}championship_points.points, '&nbsp;') AS points
			, $url_field AS url
			, $status AS status
			, champ_penalty_points AS penalty_points
			, champ_points_lost AS points_lost
			, tokens
			, CONCAT(manuf_name, ' ', car_name) AS single_car
			FROM $table
			JOIN {$lm2_db_prefix}championship_points ON $id_field = id AND championship = {int:champ}
			LEFT JOIN {$lm2_db_prefix}cars ON single_car = id_car
			$left_joins
			WHERE position IS NOT NULL)
			$union) AS actual_and_registered
			ORDER BY IFNULL(position, 999), status, name
			", array('champ'=>$champ, 'guest_id'=>$lm2_guest_member_id));
		while ($row = $smcFunc['db_fetch_assoc']($query)) {
			$name = $row['name'];
			$status = " {$row['status']}";
			$id = $row['id'];

			if ($champ_type != 'D') {
				$name = htmlentities($name, ENT_QUOTES);
			}

			if (!is_null($url = $row['url'])) {
				$name = "<A HREF='" . htmlentities($url, ENT_QUOTES) . "'>$name</A>";
			}

			if (($pos = $row['position']) == $old_pos) {
				$pos = "= $pos";
			} else {
				$old_pos = $pos ? $pos : -1;
			}

			echo "  <TR>
				<TD ALIGN=RIGHT TITLE='$id'>$pos</TD>
				<TD" . ($ycp ? "" : " COLSPAN='2'") . ">$name$status</TD>
				" . ($ycp ? format_penalty_points($row['penalty_points'], $row['points_lost']) : "") . "
				<TD ALIGN=RIGHT" . ($row['tokens'] ? " TITLE='Tokens {$row['tokens']}'" : "") . ">{$row['points']}</TD>
				";

			if (is_null($pos)) {
				$row['single_car'] = "";
				$row['car_tooltip'] = 'Has not raced';
				$carAlign = 'RIGHT';
			} else if (is_null($row['single_car'])) {
				$row['single_car'] = '<I>*Mixture*</I>';
				$row['car_tooltip'] = '';
				$carAlign = 'CENTER';
			} else {
				if (strlen($row['single_car']) > 15) {
					$row['car_tooltip'] = $row['single_car'];
					$row['single_car'] = substr($row['single_car'], 0, 14);
					$trailer = "&hellip;";
				} else {
					$row['car_tooltip'] = '';
					$trailer = "";
				}
				$row['single_car'] = htmlentities($row['single_car'], ENT_QUOTES) . $trailer;
				$row['car_tooltip'] = htmlentities($row['car_tooltip'], ENT_QUOTES);
				$carAlign = 'LEFT';
			}
			echo "<TD ALIGN='$carAlign'" . ($row['car_tooltip'] ? " TITLE='{$row['car_tooltip']}'" : "") . "><SMALL>{$row['single_car']}</SMALL></TD>\n";

			if ($id) {
				lm2_show_round_scores($champ, $id, $champ_type == 'D', $events, $roundQueryResults);
			}

			echo "</TR>\n";
		}
		$smcFunc['db_free_result']($query);
	}

	function format_penalty_points($penalty_points, $points_lost) {
		$style = is_null($penalty_points) ? ($points_lost ? " CLASS='lm2EventYCP'" : "") : sprintf(" CLASS='%s'", ($penalty_points > 2.0 ? "lm2manyYCP" : "lm2fewYCP"));
		return "<TD ALIGN='RIGHT'$style>{$penalty_points} $points_lost</TD>";
	}

	function make_scoring_desc($champ) {
		global $lm2_db_prefix;

		$query = db_query("
			SELECT scoring_scheme, champ_type, max_rank, best, rounds, class, ballast_bonus, minimum_distance, event_group
			, scoring_type, free_car_changes, car_change_penalty, single_car_penalty, max_tokens, overspend_penalty
			FROM {$lm2_db_prefix}championships
			JOIN {$lm2_db_prefix}scoring_schemes ON id_championship = $champ AND id_scoring_scheme = scoring_scheme
			" , __FILE__, __LINE__);
		while ($row = mysql_fetch_assoc($query)) {
			$champ_type = $row['champ_type'];
			$scoring_scheme = $row['scoring_scheme'];
			$max_rank = $row['max_rank'];
			$best = $row['best'];
			$rounds = $row['rounds'];
			$class = $row['class'];
			$scoring_type = $row['scoring_type'];
			$free_car_changes = $row['free_car_changes'];
			$car_change_penalty = $row['car_change_penalty'];
			$ballast_bonus = $row['ballast_bonus'];
			$single_car_penalty = $row['single_car_penalty'];
			$max_tokens = $row['max_tokens'];
			$overspend_penalty = $row['overspend_penalty'];
			$minimum_distance = $row['minimum_distance'];
			$event_group = $row['event_group'];
		}
		mysql_free_result($query);

		$html = '<B>Scoring Scheme</B>';

		if ($scoring_type == 'A') {
			$html .= "<BR/>At each event, the scores are averaged";
			if (!is_null($single_car_penalty)) {
				$html .= sprintf("; single car teams lose %d%%", 100 - $single_car_penalty * 100);
			}
		} else if ($champ_type != 'D') {
			$html .= "<BR/>At each event, best $max_rank scores count";
		}

		if (!is_null($minimum_distance)) {
			$html .= sprintf("<BR/>Must complete %d%% of largest number of laps (rounded %s) complete to score",
			                 abs($minimum_distance) * 100, $minimum_distance < 0 ? "up" : "down");
		}

		if (!is_null($best)) {
			$html .= "<BR/>Best $best scores";
			if (!is_null($rounds)) {
				$html .= " from $rounds most recent rounds";
			}
			$html .= " count";
		} else {
			$html .= "<BR/>All rounds count";
		}

		if (!is_null($car_change_penalty)) {
			$html .= sprintf("<BR/>Penalty for changing car: %.0f%%\n", $car_change_penalty * 100);
			if (!is_null($free_car_changes)) {
				$html .= "<BR/>Number of changes allowed without penalty: $free_car_changes\n";
			}
		}

		global $lm2_class_style_clause;

		$html .= "<BR/>Points: ";
		$showRatings = false;
		if ($scoring_type == 'C') {
			$html .= "cumulative based on car ratings:";
			$showRatings = true;
		} else if ($scoring_type == 'T' || $scoring_type == 'A') {
			$query = db_query("
				SELECT position, points
				FROM {$lm2_db_prefix}scoring_schemes, {$lm2_db_prefix}points_schemes
				WHERE id_scoring_scheme = $scoring_scheme
				AND id_points_scheme = points_scheme
				ORDER BY position
				", __FILE__, __LINE__);
			$sep = "";
			while ($row = mysql_fetch_assoc($query)) {
				$html .= "$sep<SPAN TITLE=\"{$row['position']}\">{$row['points']}</SPAN>";
				$sep = ", ";
			}
			mysql_free_result($query);

			if (!is_null($ballast_bonus)) {
				$html .= sprintf("<BR/>Points bonus for voluntary driver ballast: %.3f %%/kg\n", $ballast_bonus);
			}

			if ($max_tokens < 0) {
				$html .= "<BR/>Each driver gets 10 tokens for each race that they start.
					  <BR/>For a driver's first race, they get " . -$max_tokens . " extra tokens, or double that if the event is in the first half of the season.
					  <BR/>Any driver who spends more than their available tokens is disqualified from that race.";
				$showRatings = true;
			} else if (!is_null($max_tokens) || !is_null($overspend_penalty)) {
				$html .= "<BR/>Maximum token spend $max_tokens; charge of $overspend_penalty% of overspend as a percentage of total score";
				$showRatings = true;
			}
		} else {
			$html .= "Unknown scoring type '$scoring_type'";
		}

		if ($showRatings) {
			$html .= "<TABLE ALIGN=\"CENTER\">\n";

//TODO: consider showing eligible cars for non-rated championships...
			// Note that the car classifications can be set up in such a way as to fool this. But please don't.
			$query = db_query("
				SELECT $lm2_class_style_clause, class_description, CONCAT(manuf_name, ' ', car_name) AS car_desc, rating
				FROM {$lm2_db_prefix}cars
				JOIN {$lm2_db_prefix}car_classification ON id_car_classification = (
					SELECT id_car_classification
					FROM {$lm2_db_prefix}car_classification
					JOIN {$lm2_db_prefix}event_group_tree ON event_group = container
					WHERE id_car = car AND $event_group = contained
					ORDER BY depth
					LIMIT 1
				)
				JOIN {$lm2_db_prefix}manufacturers ON id_manuf = manuf
				JOIN {$lm2_db_prefix}classes ON id_class = car_class
				JOIN {$lm2_db_prefix}car_ratings ON id_car = rated_car AND rating_scoring_scheme = $scoring_scheme
				WHERE id_class REGEXP CONCAT('^('," . lm2SqlString($class) . ",')\$')
				ORDER BY rating DESC, display_sequence, car_class, car_name
				", __FILE__, __LINE__);
			while ($row = mysql_fetch_assoc($query)) {
				$html .= "<TR><TD{$row['class_style']}>{$row['class_description']}</TD><TD>{$row['car_desc']}</TD><TD ALIGN=\"RIGHT\">{$row['rating']}</TD></TR>\n";
			}
			mysql_free_result($query);

			$html .= "</TABLE>\n";
		}

		$query = db_query("
			SELECT $lm2_class_style_clause, id_class, class_description
			, class_min_ballast, class_max_ballast
			, IF(ballast_position = 999, '<I>others</I>', CONCAT('P', ballast_position)) AS position
			, ballast_delta
			FROM {$lm2_db_prefix}classes
			JOIN {$lm2_db_prefix}ballast_schemes ON class_ballast_scheme = ballast_scheme
			WHERE id_class REGEXP '^($class)\$'
			ORDER BY display_sequence, ballast_position 
			", __FILE__, __LINE__);
		$id_class = -1;
		while ($row = mysql_fetch_assoc($query)) {
			if ($id_class != $row['id_class']) {
				if ($id_class == -1) {
					$html .= "<BR/><B>Ballasts (where applicable):</B>";
				}
				$id_class = $row['id_class'];
				$html .= "\n<BR/><SMALL><B{$row['class_style']}>{$row['class_description']}</B>: {$row['class_min_ballast']}&nbsp;to&nbsp;{$row['class_max_ballast']}</SMALL>";
			}
			$html .= "<SMALL>, {$row['position']}&nbsp;{$row['ballast_delta']}kg</SMALL>";
		}
		mysql_free_result($query);

		return $html;
	}

	function lm2_show_round_scores($champ, $id, $use_colors, $events, $queryResults) {
		$scores = array();
		$htmlClasses = array();

		$rows = lm2ArrayValue($queryResults, $id);
		if ($rows) {
			foreach ($rows as $row) {
				$scores[$row['event']] = $row['points'];
				$htmlClasses[$row['event']] = $row['position'] . ($row['is_dropped'] == 1 ? " dropped" : "") . (is_null($row['pp']) ? "" : " lm2EventYCP");
			}
		}

		foreach ($events AS $row) {
			$event = $row['id_event'];
			echo "<TD ALIGN=RIGHT CLASS='lm2position" . lm2ArrayValue($htmlClasses, $event) . "'>" . lm2ArrayValue($scores, $event) . "</TD>\n";
		}
	}

	global $context, $boardurl, $smcFunc, $lm2_db_prefix, $lm2_circuit_html_clause, $lm2_class_style_clause, $colsep, $lm2_champ_types, $settings;
	$flag_prefix = "/images/flags-22x14/";

	echo "<table border='0' width='100%'><tr><td valign='top'>";

	$group = $context['lm2']['group']['id_event_group'];

	echo lm2_table_open("Series", "LEFT");
	lm2MakeSeriesTree($group);
	echo lm2_table_close();
	lm2StandingsAndStatistics();
	
	echo "</td>\n<td valigan='top'>";

	if ($block_title = $context['lm2']['group']['block_title']) {
		global $lm2_mods_group, $user_info;
		if (($msg = $context['lm2']['group']['series_details']) && in_array($lm2_mods_group, $user_info['groups'])) {
			global $lm2_series_details_topic, $sc;
			$block_title = "<A HREF='$boardurl/index.php?action=post;msg=$msg;topic=$lm2_series_details_topic.msg$msg;sesc=$sc'>$block_title</A>";
		}
		echo lm2_table_open($block_title) . $context['lm2']['group']['block_text'] . lm2_table_close();
	} else if ($pid = $context['lm2']['group']['pid']) {
		$query = $smcFunc['db_query'](null, "SELECT title, content FROM mkp_pages WHERE id = {string:pid}", array('pid'=>$pid));
		($row = $smcFunc['dbl_fetch_assoc']($query)) || die("can't find page $pid for group $group");
		echo lm2_table_open(stripslashes($row['title'])) . stripslashes($row['content']) . lm2_table_close();
		mysql_free_result($query);
	}

	if ($topic = $context['lm2']['group']['reg_topic']) {
		//FIXME: consider getting rid of this and just putting the link in the series description block instead.
		echo lm2_table_open("Registration")
			. "<P>Please go to <A HREF='$boardurl/index.php?topic=$topic'>this topic</A> for registration details."
			. lm2_table_close();
	}

	$opener = lm2_table_open("Drivers and Standings") . "<TABLE BORDER=1>\n";
	$closer = "";
	$query = $smcFunc['db_query'](null, "
		SELECT id_championship
		, champ_class_desc
		, champ_type
		, scoring_type
		, is_protected
		, penalty_group_months
		, penalty_group
		FROM {$lm2_db_prefix}championships
		JOIN {$lm2_db_prefix}scoring_schemes ON scoring_scheme = id_scoring_scheme
		JOIN {$lm2_db_prefix}event_groups ON event_group = id_event_group
		JOIN {$lm2_db_prefix}penalty_groups USING (penalty_group)
		WHERE id_event_group = {int:group}
		ORDER BY champ_sequence, champ_class_desc, champ_type
		", array('group'=>$group));
	while ($row = $smcFunc['db_fetch_assoc']($query)) {
		echo $opener;
		$opener = "";
		$closer = "</TABLE>\n" . lm2_table_close();

		$champ_type = $row['champ_type'];
		$champ_id = $row['id_championship'];
		$scoring_type = $row['scoring_type'];
		$penalty_group = $row['penalty_group'];
		$penalty_group_months = $row['penalty_group_months'];

		$query2 = $smcFunc['db_query'](null, "SELECT id_event, smf_topic"
			. ", $lm2_circuit_html_clause AS circuit_html"
			. ", smf_topic, event_date"
			. ", LOWER(iso3166_code) AS iso3166_code"
			. ", iso3166_name"
			. ", entries_c AS entries"
			. ", event_status <> 'U' AS is_official"
			. ", COUNT(event_entry) AS points_ch"
			. " FROM ({$lm2_db_prefix}events"
			. ", {$lm2_db_prefix}circuits"
			. ", {$lm2_db_prefix}circuit_locations"
			. ", {$lm2_db_prefix}sim_circuits"
			. ", {$lm2_db_prefix}event_group_tree"
			. ", {$lm2_db_prefix}iso3166)"
			. " LEFT JOIN {$lm2_db_prefix}event_entries ON event = id_event"
			. " LEFT JOIN {$lm2_db_prefix}event_points ON event_entry = id_event_entry AND championship = {int:champ}"
			. " WHERE event_group = {$lm2_db_prefix}event_group_tree.contained"
			. " AND {$lm2_db_prefix}event_group_tree.container = {int:group}"
			. " AND event_type = 'C'"
			. " AND id_iso3166 = iso3166_code"
			. " AND circuit_location = id_circuit_location"
			. " AND id_sim_circuit = {$lm2_db_prefix}events.sim_circuit"
			. " AND id_circuit = {$lm2_db_prefix}sim_circuits.circuit"
			. " GROUP BY event_date, id_event"
			. " HAVING points_ch > 0 OR entries = 0"
			. (($group == 92 || $group == 93) ? " ORDER BY circuit_html, event_date" : "") // Fiddle for UKGPL Season 1 with double-headers
			, array('champ'=>$champ_id, 'group'=>$group));
		$events = array();
		while ($row2 = $smcFunc['db_fetch_assoc']($query2)) {
			$row2['event_date'] = format_event_date($row2);
			$row2['flag'] = "$flag_prefix{$row2['iso3166_code']}.gif";
			array_push($events, $row2);
		}
		$smcFunc['db_free_result']($query2);

		$event_headers = '';
		foreach ($events AS $row2) {
			$title = "{$row2['circuit_html']}&#10;{$row2['event_date']}&#10;{$row2['iso3166_name']}";
			$heading = "<IMG SRC='{$row2['flag']}' ALT='$title' BORDER=0>";
			$heading = lm2MakeEventLink($row2['id_event'], $row2['smf_topic'])	. "$heading</A>";
			$flagClass = "lm2flagCell lm2" . ($row2['is_official'] ? "" : "un") . "official";
			$event_headers .= "\n  <TH TITLE='$title' CLASS='$flagClass'>$heading</TH>\n";
		}

		$fullWidth = 5 + count($events);
		$comp_type = $scoring_type == 'C' ? 'Ranking' : 'Championship';
		echo "<TR><TH COLSPAN='$fullWidth' ID='ch$champ_id' TITLE='$champ_id'" . lm2ArrayValue($row, 'class_style') . ">"
			. "{$row['champ_class_desc']} - {$lm2_champ_types[$champ_type]} $comp_type"
			. " <SMALL>(" . lm2MakeEventGroupLink($group, "scoring", $settings['theme_id'], "ch$champ_id", "&scoring=$champ_id") . ")</SMALL></TH></TR>\n";
		if ($row['is_protected'] != 2) {
			show_championship($champ_id, $champ_type, $group, $events, $event_headers, $fullWidth, $penalty_group, $penalty_group_months);
		}
		echo "\n";
	}
	$smcFunc['db_free_result']($query);
	echo $closer;

//XXX: should be able to remove this once we're setting it correctly in other places (not via URL)
	unset($_SESSION['id_theme']); // Don't allow it to become sticky...

	$query = $smcFunc['db_query'](null, "SELECT id_event, smf_topic"
		. ", $lm2_circuit_html_clause AS circuit_html"
		. ", event_date"
		. ", entries_c AS entries"
	//	. ", LOWER(iso3166_code) AS iso3166_code"
	//	. ", iso3166_name"
		. ", subject AS event_desc"
		. " FROM ({$lm2_db_prefix}events"
		. ", {$lm2_db_prefix}circuits"
		. ", {$lm2_db_prefix}circuit_locations"
	//	. ", {$lm2_db_prefix}iso3166"
		. ", {$lm2_db_prefix}sim_circuits)"
		. " LEFT JOIN {db_prefix}topics ON smf_topic = {db_prefix}topics.id_topic"
		. " LEFT JOIN {db_prefix}messages ON id_first_msg = id_msg"
		. " WHERE event_group = {int:group}"
		. " AND (event_type <> 'C' OR points_c = 0 AND entries_c > 0)"
		. " AND circuit_location = id_circuit_location"
		. " AND id_sim_circuit = {$lm2_db_prefix}events.sim_circuit"
		. " AND id_circuit = {$lm2_db_prefix}sim_circuits.circuit"
	//	. " AND id_iso3166 = iso3166_code"
		. " ORDER BY event_date, id_event"
		, array('group'=>$group));
	$pre = lm2_table_open("Non-Championship Races") . "<TABLE ID='nch'>\n";
	$post = '';
	while ($row = $smcFunc['db_fetch_assoc']($query)) {
		echo $pre;
		$pre = '';
		$post = "</TABLE>\n" . lm2_table_close();
	
		$url = lm2MakeEventLink($row['id_event'], $row['smf_topic']);
		$date = format_event_date($row);
		if ($desc = $row['event_desc']) {
			$tooltip = " TITLE='$date'";
		} else {
			$desc = $date;
			$tooltip = "";
		}
	//XXX: flag - also in driver list.
		echo "<TR><TD$tooltip>$url$desc</A></TD>"
			. "$colsep<TD>{$row['circuit_html']}</TD></TR>\n";
	}
	$smcFunc['db_free_result']($query);
	echo $post;

	/*
	$query = $smcFunc['db_query'](null, "SELECT moderator, realName AS name"
		. " FROM {db_prefix}members"
		. ", {$lm2_db_prefix}event_groups"
		. ", {$lm2_db_prefix}event_group_tree"
		. " WHERE {int:group} = contained AND container = id_event_group"
		. " AND moderator IS NOT NULL"
		. " AND id_member = moderator"
		. " ORDER BY depth ASC"
		, array('group'=>$group));
	while ($row = mysql_fetch_assoc($query)) {
		echo "<P>The series moderator is <A HREF='$boardurl/index.php?action=pm;sa=send;u={$row['moderator']}'>{$row['name']}</A></P>\n";
		break;
	}
	mysql_free_result($query);
	*/

	echo "</td></tr></table>";
}

function lm2StandingsAndStatistics() {
	global $context, $lm2StatsTopN;
	$lm2StatsTopN = 25;

	$theme = lm2ArrayValue($_REQUEST, 'theme');
	$group = $context['lm2']['group']['id_event_group'];
	$params = array(
		"Drivers and Standings"=>null,
		"Top $lm2StatsTopN drivers by participation"=>'stats',
	);

	echo lm2_table_open("Statistics", "RIGHT");
	$sep = "";
	foreach ($params as $text=>$paramName) {
		if (!is_null($context['lm2']['group']['id_event_group']) || !is_null($paramName)) {
			$param = is_null($paramName) ? null : "&stats=". urlencode($text);
			if ((is_null($paramName) ? null : $text) == lm2ArrayValue($context['lm2']['group'], 'stats')) {
				$text = "<B>$text</B>";
			} else {
				$text = lm2MakeEventGroupLink(is_null($group) ? 0 : $group, $text, $theme, null, $param);
			}
			echo "$sep<NOBR>$text</NOBR>";
			$sep = "<BR/>\n";
		}
	}
	echo lm2_table_close();
}

function template_stats() {
	global $context, $boardurl, $lm2_db_prefix, $lm2_guest_member_id, $lm2StatsTopN;

	echo "<table border='0' width='100%'><tr><td valign='top'>";

	$group = $context['lm2']['group']['id_event_group'];

	echo lm2_table_open("Series", "LEFT");
	lm2MakeSeriesTree($group, true, "&stats=". urlencode($context['lm2']['group']['stats']));
	echo lm2_table_close();
	lm2StandingsAndStatistics();

	echo "</td>\n<td valigan='top'>";

	echo lm2_table_open("Statistics - " . htmlentities($context['lm2']['group']['stats'], ENT_QUOTES));

	echo "<TABLE>";
	$query = db_query("
		SELECT driver_name AS name
		, CONCAT('/index.php?ind=lm2&driver=', driver_member) AS url
		, COUNT(DISTINCT event) AS events
		FROM {$lm2_db_prefix}drivers
		JOIN {$lm2_db_prefix}event_entries ON member = driver_member
		WHERE driver_member <> $lm2_guest_member_id
		". (is_null($group) ? "" : "AND event IN (
			SELECT id_event FROM {$lm2_db_prefix}events WHERE event_group IN (
				SELECT contained FROM {$lm2_db_prefix}event_group_tree WHERE container = $group))") . "
		GROUP BY driver_member
		ORDER BY events DESC
		LIMIT $lm2StatsTopN
		", __FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($query)) {
		echo "\n<TR><TD><A HREF='{$row['url']}'>{$row['name']}</A></TD><TD ALIGN='RIGHT'>{$row['events']}</TD></TR>";
	}
	mysql_free_result($query);
	echo "\n</TABLE>\n";
	
	echo lm2_table_close();
	echo "</td></tr></table>";
}

function template_circuits() {
	global $context, $boardurl, $lm2_db_prefix, $smcFunc;

	echo "<table width='100%' border='0'><tr><td valign='top'>\n";

	echo lm2_table_open("Geographical Links");
?>
<table width="100%">
<tr><td align="center"><A HREF="/lm2/kml.php">KML</A> (for <A HREF="http://earth.google.com/">Google Earth</A>)</td></tr>
<tr><td align="center"><?php echo "<A HREF='http://maps.google.com/maps?q=https://{$_SERVER['SROU_HOST_WWW']}/lm2/kml.php'>Google Maps</A>"; ?></td></tr>
</table>
<?php
	echo lm2_table_close();

	echo "</td><td valign='top'>\n";

	echo lm2_table_open("Circuits");

	$query = $smcFunc['db_query'](null, "
		SELECT id_circuit_location, full_name, is_fantasy
		FROM {$lm2_db_prefix}circuit_locations
		WHERE id_circuit_location <> -1
		ORDER BY brief_name
		");
	$sep = "";
	while ($row = $smcFunc['db_fetch_assoc']($query)) {
		echo "$sep<A HREF='$boardurl/index.php?action=LM2R&location={$row['id_circuit_location']}&circuit=0'>{$row['full_name']}</A>";
		if ($row['is_fantasy']) {
			echo " (fantasy)";
		}
		echo "\n";
		$sep = "<BR/>";
	}
	$smcFunc['db_free_result']($query);

	echo lm2_table_close();

	echo "</td></tr></table>\n";
}

function template_circuit() {
	global $context;

	echo lm2_table_open("{$context['lm2']['location']['full_name']} "
		. "<IMG SRC='/images/flags-22x14/{$context['lm2']['location']['iso3166_code']}.gif' VALIGN='MIDDLE' WIDTH='22' HEIGHT='14'>"
		. ($context['lm2']['location']['is_fantasy'] ? ' (fantasy)' : ''));

	if ($loc_url = $context['lm2']['location']['location_url']) {
		echo "<P><A HREF='$loc_url'>Website</A></P>\n";
	}

	$links = lm2MakeWeatherLinks($context['lm2']['location']);

	if (!is_null($n = $context['lm2']['location']['latitude_n']) && !is_null($e = $context['lm2']['location']['longitude_e'])) {
		echo "<P>"
			. "<A HREF='http://maps.google.com/maps?ll=$n,$e&spn=0.025,0.025&t=k'>Google Maps</A>"
			. " | <A HREF='/lm2/kml.php?location={$context['lm2']['location']['id_circuit_location']}'>KML</A> (Google Earth)"
			. " | <A HREF='http://maps.live.com/default.aspx?v=2&cp=$n~$e&style=h&lvl=14&tilt=-90&dir=0&alt=-2000&encType=1'>Live Search</A>"
			. " | <A HREF='http://www.multimap.com/map/browse.cgi?lon=$e&lat=$n&title="
			. urlencode(html_entity_decode($context['lm2']['location']['full_name'], ENT_QUOTES)) . "&scale=25000'>MultiMap</A>"
//			. " | <A HREF='http://www.terraserver.com/view.asp?coord=LL&latDir=N&latDeg=$n&lonDir=E&lonDeg=$e&styp=CO'>TerraServer</A>"
			. "\n<BR/>{$links['weatherLink']}"
			. " | {$links['climateLink']}"
			. ($links['generateLink'] ? " | {$links['generateLink']}" : "")
			. "</P>\n";
	}

	echo lm2_table_close();

	lm2MakeRssWeather($links);

	foreach ($context['lm2']['circuits'] as $row) {
		if (!$row['layout_name']) {
			$row['layout_name'] = "<I>Standard layout</I>";
		}
		echo lm2_table_open($row['layout_name']);
		lm2ShowLapRecords(null, null, $row['id_circuit'], null, null, "<H3>SimRacing.org.uk Lap Records</H3><table>", "</table>");
		echo "<H3>Event History</H3>";
		lm2MakeEventList("id_circuit", $row['id_circuit'], null);
		echo lm2_table_close();
	}
}
?>
