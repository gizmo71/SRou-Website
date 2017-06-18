<?php

if (!defined('SMF'))
	die('Hacking attempt...');

function lm2r() {
	global $context, $lm2_db_prefix, $boardurl, $smcFunc;

	loadTemplate('lm2r');

	if (($team = lm2ArrayValue($_REQUEST, 'team')) == '*' || $team === '') {
		$context['page_title'] = "Registered Teams";
		$context['sub_template'] = 'teams';
	} else if (!is_null($team)) {
		is_numeric($team) || die("$team is not numeric");
		$query = $smcFunc['db_query'](null, "SELECT * FROM {$lm2_db_prefix}teams WHERE id_team = {int:team}", array('team'=>$team));
		($context['lm2']['team'] = $smcFunc['db_fetch_assoc']($query)) || die("unknown team $team");
		$smcFunc['db_fetch_assoc']($query) && die("ambiguous team $team");
		$smcFunc['db_free_result']($query);
		$context['page_title'] = "Team Profile - {$context['lm2']['team']['team_name']}";
		$context['sub_template'] = 'team';
	} else if (($circuit = lm2ArrayValue($_REQUEST, 'circuit')) == '*') {
		$context['sub_template'] = 'circuits';
		$context['page_title'] = "Circuits";
	} else if (!is_null($circuit) && !is_null($location = lm2ArrayValue($_REQUEST, 'location'))) {
		is_numeric($circuit) || die("$circuit is not numeric");
		is_numeric($location) || die("$location is not numeric");
		$context['sub_template'] = 'circuit';

		global $colsep;
	
		$query = $smcFunc['db_query'](null, "
			SELECT id_circuit_location
			, full_name
			, brief_name
			, is_fantasy
			, iso3166_name
			, LOWER(iso3166_code) AS iso3166_code
			, wu_station
			, location_url
			, latitude_n
			, longitude_e
			FROM {$lm2_db_prefix}circuit_locations
			, {$lm2_db_prefix}iso3166
			 WHERE id_circuit_location = $location
			   AND id_iso3166 = iso3166_code
			");
		($context['lm2']['location'] = $smcFunc['db_fetch_assoc']($query)) || die("unknown location $location");
		$smcFunc['db_fetch_assoc']($query) && die("ambiguous location $location");
		$smcFunc['db_free_result']($query);
	
		$context['page_title'] = "Circuit Information - {$context['lm2']['location']['brief_name']}";

		$query = $smcFunc['db_query'](null, "
			SELECT id_circuit, layout_name
			FROM {$lm2_db_prefix}circuits
			WHERE circuit_location = $location
			ORDER BY id_circuit <> {int:circuit}, layout_name
			", array('circuit'=>$circuit));
		while ($row = $smcFunc['db_fetch_assoc']($query)) {
			$context['lm2']['circuits'][] = $row;
		}
		$smcFunc['db_free_result']($query);
	} else if (!is_null($group = lm2ArrayValue($_REQUEST, 'group'))) {
		is_numeric($group) || fatal_error("'$group' is not a number (referer " . lm2ArrayValue($_SERVER, 'HTTP_REFERER') . ")", true);
		$stats = lm2ArrayValue($_REQUEST, 'stats');
		if ($group == 0) {
			$context['lm2']['group'] = array('full_desc'=>'All Series', 'block_text'=>null, 'id_event_group'=>null);
		} else {
			global $board_info;
			$query = $smcFunc['db_query'](null, "
				SELECT full_desc, id_event_group, SUM(id_event IS NOT NULL) AS events
				, reg_topic, series_details, subject AS block_title, body AS block_text
				, smileys_enabled AS smileysEnabled, mkp_pid AS pid
				FROM {lm2_prefix}event_groups
				LEFT JOIN {lm2_prefix}events ON event_group = id_event_group
				LEFT JOIN {db_prefix}messages ON series_details = id_msg
				WHERE id_event_group = {int:group}
				GROUP BY id_event_group
				", array('group'=>$group));
			$context['lm2']['group'] = $smcFunc['db_fetch_assoc']($query);
			$smcFunc['db_free_result']($query);
		}
		if ($context['lm2']['group']) {
			$context['page_title'] = htmlentities($context['lm2']['group']['full_desc'], ENT_QUOTES);
			$context['sub_template'] = 'group';
			if (!is_null($stats)) {
				$context['page_title'] .= " - Statistics";
				$context['lm2']['group']['stats'] = $stats;
				$context['sub_template'] = 'stats';
			} else if ($context['lm2']['group']['events'] > 0) {
				$context['page_title'] .= " - Schedule and Standings";
			}
			if (is_null($stats) && !is_null($context['lm2']['group']['block_text'])) {
				$context['lm2']['group']['block_text'] = parse_bbc(
					$context['lm2']['group']['block_text'],
					!!$context['lm2']['group']['smileysEnabled'],
					"LM2i_GRP=$group" /*cache ID*/);
			}
		} else {
			$context['page_title'] = "Unknown event group $group";
		}
	} else {
		$context['page_title'] = "LM2 unknown";
	}
}

?>
