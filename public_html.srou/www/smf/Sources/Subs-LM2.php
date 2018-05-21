<?php
/*TODO: move more stuff from include.php - might need to rename carefully...
 * Using a convention that externally access stuff is lm2CamelCase and internal is lm2_under_scores.
 */

if (!defined('SMF'))
	die('Hacking attempt...');

function lm2_table_open($title = null, $align = 'center') {
	return "<table cellpadding='3' cellspacing='0' border='0' width='100%' class='tborder' style='padding-top: 0; margin-bottom: 1ex;'>"
		. (is_null($title) ? "" : "<tr><td class='titlebg' valign='middle' align='left' style='padding-left: 6px;'>$title</td></tr>")
		. "<tr><td width='5%' valign='top' class='windowbg' align='$align'>";
}

function lm2_table_close() {
	return "</td></tr></table>\n";
}

function lm2MakeSeriesTree($group, $showAll = false, $extraParams = '') {
	global $lm2_db_prefix;
	if ($showAll) {
		$text = "All Series";
		if (!$group) {
			$text = "<B>$text</B>";
		}
		echo sprintf("<NOBR>%s</NOBR><BR/>\n", lm2MakeEventGroupLink(0, $text, null, null, $extraParams));
	}
	$query = db_query(((is_null($group) || $group == '') ? "" : "
		SELECT id_event_group, long_desc, depth_c AS depth, sequence_c, series_theme
		FROM {$lm2_db_prefix}event_groups
		, {$lm2_db_prefix}event_group_tree t
" . /* Parents */ "
		WHERE t.contained = $group AND t.container = id_event_group 
" . /* Siblings */ "
		OR t.contained = $group AND t.container IN (SELECT container FROM {$lm2_db_prefix}event_group_tree t2 WHERE t2.contained = id_event_group AND t2.depth = 1)
" . /* Children */ "
		OR t.container = $group AND t.contained = id_event_group AND t.depth = 1
		GROUP BY id_event_group
		UNION ") . "
" . /* Always get the root groups. */ "
		SELECT id_event_group, long_desc, depth_c AS depth, sequence_c, series_theme
		FROM {$lm2_db_prefix}event_groups
		WHERE parent IS NULL
		ORDER BY sequence_c
		" , __FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($query)) {
		$text = $row['long_desc'];
		if ($row['id_event_group'] == $group) {
			$text = "<B>$text</B>";
		}
		echo sprintf("<NOBR>%s%s</NOBR><BR/>\n",
			str_repeat("&nbsp;", ($row['depth'] + ($showAll ? 1 : 0)) * 2),
			lm2MakeEventGroupLink($row['id_event_group'], $text, $row['series_theme'], null, $extraParams));
	}
	mysql_free_result($query);
}

function lm2Staff($returnContent = false, $group = null, $show_title = true) {
	global $db_prefix, $lm2_db_prefix, $lm2_guest_member_id, $boardurl;

	//FIXME: once MkPortal is gone we can remove this and the default.
	if (is_null($group)) {
		global $lm2_mods_group;
		$group = $lm2_mods_group;
	}

	$tdo = "<td" . ($returnContent ? "" : " class='smalltext'");
	if (!isset($content)) $content = ""; // If not being called from MkPortal block.

	$query = db_query("
		SELECT DISTINCT id_team, team_name, id_member, realName, userTitle AS userTitle
		FROM {$db_prefix}members
		LEFT JOIN {$lm2_db_prefix}team_drivers ON member = id_member AND date_to IS NULL
		LEFT JOIN {$lm2_db_prefix}teams ON team = id_team
		WHERE id_member <> $lm2_guest_member_id
		AND CONCAT(',', id_group, ',', additionalGroups, ',') REGEXP CONCAT(',', $group, ',')
		ORDER BY realName, team_name
		" , __FILE__, __LINE__);
	$row = null;
	$closer = "";
	for (;;) {
		$newrow = mysql_fetch_assoc($query);

		if ($newrow == null || $newrow['id_member'] != $row['id_member']) {
			if ($row != null) {
				$content .= $closer;
				$content .= "</NOBR></TD>$tdo WIDTH='5%'></TD>";
				if ($show_title) {
					$content .= "$tdo ALIGN='LEFT'><NOBR>{$row['userTitle']}</NOBR></TD>";
				}
				$content .= "</TR>\n";
			}

			if ($newrow == null) break;

			$row = $newrow;
			$content .= "
			<TR>$tdo ALIGN='RIGHT'><NOBR><A HREF='$boardurl/index.php?action=profile&u={$row['id_member']}&sa=racing_history&driver={$row['id_member']}'>{$row['realName']}</A></NOBR></TD>
				<TD WIDTH='5%'></TD>$tdo ALIGN='LEFT'><NOBR>";
			$sep = "(";
			$closer = "";
		}
		$row = $newrow;

		if ($row['team_name']) {
			$content .= $sep . lm2FormatTeam($row['id_team'], $row['team_name']);
			$closer = ")";
			$sep = ", ";
		}
	}
	mysql_free_result($query);

	if ($returnContent) {
		return $content;
	}

	//FIXME: remove this once we are sure it's no longer used.
	echo "<tr><td class='titlebg' colspan='2'>Staff</td></tr><tr><td class='windowbg'></td><td class='windowbg2'><table>$content</table></td></tr>";
}

// General purpose hook for adding stuff to topics.
function lm2AddTopicDetails() {
	lm2FormatEventDetails();
}

function lm2FormatEventDetails() {
	global $context, $user_info, $boardurl, $settings;
	global $lm2_guest_member_id, $lm2_mods_group, $lm2_db_prefix, $lm2_circuit_link_clause;

	$seenExtra = array();

	global $modSettings;
	$modSettings['disableQueryCheck'] = true;
	$request = db_query("
		SELECT id_event
		, event_date, TIMESTAMPDIFF(HOUR, " . lm2Php2timestamp() . ", event_date) AS tminus
		, id_event_group, full_desc, series_theme
		, length_metres
		, id_circuit
		, id_circuit_location, latitude_n, longitude_e, wu_station
		, event_status <> 'U' AS is_official
		, IFNULL(entries_c, 0) AS entries_c
		, event_moderator
		, $lm2_circuit_link_clause AS circuit_html
		, IFNULL(server_starter_override, server_starter) AS server_starter
		, event_password AS password
		, {$lm2_db_prefix}events.sim
		, IFNULL(eb_name, driving_name) AS eb_name
		, IFNULL(IFNULL(eb_ballast, handicap_ballast), 0) AS eb_ballast
		"/* . ", IF(SELECT COUNT(*) FROM {$lm2_db_prefix}championships
			JOIN {$lm2_db_prefix}classes ON id_class REGEXP CONCAT('^(',{$lm2_db_prefix}championships.class,')\$')
			WHERE event_group = id_event_group
			AND class_ballast_scheme IS NOT NULL) > 0, 8, 0) AS ballast_hours" */. "
		, IF({$lm2_db_prefix}events.sim = 4, 8, 0) AS ballast_hours 
		, (SELECT COUNT(*) FROM {$lm2_db_prefix}event_ballasts WHERE id_event = eb_event AND eb_driver <> $lm2_guest_member_id) AS got_ballasts
		, sim_weather
		, iracing_subsession
		FROM {$lm2_db_prefix}events
		JOIN {$lm2_db_prefix}sims ON id_sim = {$lm2_db_prefix}events.sim
		JOIN {$lm2_db_prefix}event_groups ON id_event_group = event_group
		JOIN {$lm2_db_prefix}sim_circuits ON id_sim_circuit = sim_circuit
		JOIN {$lm2_db_prefix}circuits ON id_circuit = circuit
		JOIN {$lm2_db_prefix}circuit_locations ON id_circuit_location = circuit_location
		LEFT JOIN {$lm2_db_prefix}event_ballasts ON id_event = eb_event AND eb_driver = {$context['user']['id']}
		LEFT JOIN {$lm2_db_prefix}driver_details ON (
			{$lm2_db_prefix}driver_details.driver = {$context['user']['id']} AND
			{$lm2_db_prefix}driver_details.sim = {$lm2_db_prefix}events.sim)
		WHERE smf_topic = {$context['current_topic']}
		ORDER BY event_date
	", __FILE__, __LINE__);
	$context['lm2_event_types'] = array();
	while ($row = mysql_fetch_assoc($request)) {
		$when = lm2Timestamp2php($row['event_date']);
		$row['event_date'] = timeformat($when, true);
		$row['event_date'] = "<A HREF=\"$boardurl/index.php?action=calendar;" . strftime("year=%Y;month=%m", $when) . "\">{$row['event_date']}</A>";

		echo lm2_table_open("<SPAN ID='event{$row['id_event']}'>{$row['event_date']} - {$row['circuit_html']} - "
			. lm2MakeEventGroupLink($row['id_event_group'], $row['full_desc'], $row['series_theme'])
			. ($row['iracing_subsession'] ? " - <A TARGET='_blank' HREF='http://members.iracing.com/membersite/member/EventResult.do?subsessionid={$row['iracing_subsession']}'>@iRacing.com</A>" : "")
			. "</SPAN>");

		if ($row['entries_c'] == 0) {
			if ($settings['theme_id'] != 6) {
				echo '<div style="float: right" id="ts3viewer_14187" style=""> </div>
				     <script src="https://static.tsviewer.com/short_expire/js/ts3viewer_loader.js"></script>
				     <script>var ts3v_url_1 = "https://www.tsviewer.com/ts3viewer.php?ID=14187&text=757575&text_size=10&text_family=1&text_s_color=000000&text_s_weight=normal&text_s_style=normal&text_s_variant=normal&text_s_decoration=none&text_i_color=&text_i_weight=normal&text_i_style=normal&text_i_variant=normal&text_i_decoration=none&text_c_color=&text_c_weight=normal&text_c_style=normal&text_c_variant=normal&text_c_decoration=none&text_u_color=000000&text_u_weight=normal&text_u_style=normal&text_u_variant=normal&text_u_decoration=none&text_s_color_h=&text_s_weight_h=bold&text_s_style_h=normal&text_s_variant_h=normal&text_s_decoration_h=none&text_i_color_h=000000&text_i_weight_h=bold&text_i_style_h=normal&text_i_variant_h=normal&text_i_decoration_h=none&text_c_color_h=&text_c_weight_h=normal&text_c_style_h=normal&text_c_variant_h=normal&text_c_decoration_h=none&text_u_color_h=&text_u_weight_h=bold&text_u_style_h=normal&text_u_variant_h=normal&text_u_decoration_h=none&iconset=default";
ts3v_display.init(ts3v_url_1, 14187, 100);
</script>';
			}
			
			if ($row['server_starter']) {
				$request2 = db_query("
					SELECT driver_member AS id_member
					, driver_name AS realName
					FROM {$lm2_db_prefix}drivers
					WHERE driver_member = {$row['server_starter']}
				", __FILE__, __LINE__);
				($row['server_starter'] = mysql_fetch_assoc($request2)) || die("can't find server starter member");
				mysql_fetch_assoc($request2) && die("ambiguous server starters");
				mysql_free_result($request2);
			}

			$closer = "";
			$sep = "<p>";
			if ($row['sim_weather'] != 'N') { // Some sims don't have weather.
				$links = lm2MakeWeatherLinks($row);
				if ($links['weatherLink']) {
					echo "$sep{$links['weatherLink']}";
					$sep = " - ";
					$closer = "</p>\n";
				}
				if ($links['generateLink'] && $row['sim_weather'] == 'S') {
					echo "$sep{$links['generateLink']}";
					$sep = " - ";
					$closer = "</p>\n";
				}
			} else {
				$links = array();
			}
			if (false && $row['ballast_hours']) {
				echo $sep;
				if ($row['tminus'] > $row['ballast_hours']) {
					echo ($row['tminus'] - $row['ballast_hours']) . " hours until ballasts can be generated";
				} else if (in_array($lm2_mods_group, $user_info['groups'])) {
					echo "<A HREF='/lm2/ballast.php?event={$row['id_event']}&group={$row['id_event_group']}'>Generate Ballast</A>";
				} else {
					echo "ballasts now frozen";
				}
				$sep = " - ";
				$closer = "</p>\n";
			}
			echo $closer;

			if ($row['got_ballasts']) {
				echo "<P>You must drive the race using the name <B><BIG><TT>{$row['eb_name']}</TT></BIG></B>.</P>\n";
				// <BR/>You will carry handicap ballast of <B>{$row['eb_ballast']}kg</B>.
			} else if ($row['eb_name']) {
				echo "<P>You are registered to drive under the name <B><BIG><TT>{$row['eb_name']}</TT></BIG></B>
					" . ($row['eb_ballast'] ? "<!-- with a handicap ballast of <B>{$row['eb_ballast']}kg</B> -->" : "") . ".
					<BR/>To change this click <A HREF='$boardurl/index.php?action=profile;sa=driver_info'>here</A>.</P>\n";
			}
		} else { // Get some entries.
			$links = null; // Don't need weather info for completed events.

			$row['server_starter'] = null;
			$row['password'] = null;

			$query2 = db_query("SELECT MIN(qual_best_lap_time) AS best_qual_time
				FROM {$lm2_db_prefix}event_entries
				WHERE event = {$row['id_event']}
			"/* . " AND car_class_c = id_class"*/, __FILE__, __LINE__);
			$row['best_qual_time'] = null; //FIXME: qualifying best per class?
			while ($row2 = mysql_fetch_assoc($query2)) {
				$row['best_qual_time'] = $row2['best_qual_time'];
			}
			mysql_free_result($query2);
		} // End if some entries.

		if ($row['password'] && !$user_info['is_guest']) {
			echo "<P ID='post_event_password'>" . lm2_make_password_link($row);
			$practiceWording = "practice";
			$preRaceWording = "";
			if ($row['sim'] == 9) { // iRacing doesn't have practice servers per sé.
				$practiceWording = "hosted practice sessions";
				$preRaceWording = "; the actual event hosted session will use the above password";
			}
			echo "<BR/>(pre-event $practiceWording will use the <A HREF='$boardurl/index.php?topic=2929'>usual practice password</A>$preRaceWording)";
			if ($settings['theme_id'] == 6) {
				echo "\n<BR/>UKGPL chatroom on IGOR or WinVROC (see announcement post in this topic), password '<TT><BIG>savage</BIG></TT>'";
			} else {
				echo "</P>\n<P><A HREF='$boardurl/index.php?topic=5646'>TeamSpeak server details</A>.</P>\n";
			}
			echo "</P>\n";
		}
		if ($row['server_starter']) {
			echo "<P><A HREF='$boardurl/index.php?action=profile;u={$row['server_starter']['id_member']}'>{$row['server_starter']['realName']}</A> assigned to start server</P>";
		}
 
		if ($row['entries_c'] > 0) {
			lm2_show_event($row);
		} else if ($row['got_ballasts'] > 0) {
			lm2_show_gen_ballasts($row['id_event']);
		}

		if ($row['is_official']) {
			lm2_show_penalties($row['id_event']);
		} else if (!is_null($row['event_moderator'])) {
			echo "<P ALIGN='CENTER'><I>Results are provisional; moderator's review is underway.</I>";
		} else if ($row['entries_c'] > 0) {
			echo "<P ALIGN='CENTER'><I>Results are provisional pending moderator's review.</I>";
			if (lm2FindEventModerator($row['id_event'])) {
				echo "<BR /><B><A HREF='/lm2/index.php?action=increp&event={$row['id_event']}'>Submit an incident report</A></B>";
			}
			echo "</P>\n";
		}

		echo lm2_table_close();

		$key = "{$row['sim']}/{$row['id_circuit']}";
		if (!array_key_exists($key, $seenExtra) || $row['entries_c'] > 0) {
			lm2ShowLapRecords(null, $row['sim'], $row['id_circuit'], $row['entries_c'] > 0 ? $row['id_event'] : null);

			if ($links) {
				lm2MakeRssWeather($links);
			}

			$seenExtra[$key] = true;
		}
	}
	mysql_free_result($request);
}

function lm2_show_gen_ballasts($event) {
	global $lm2_db_prefix, $lm2_guest_member_id;
	echo "<H2>Voluntary Handicap Ballasts</H2><TABLE BORDER='1' CELLSPACING='0' CELLPADDING='3'>\n";
	$query = db_query("
		SELECT driver_name AS name, eb_ballast
		FROM {$lm2_db_prefix}event_ballasts
		LEFT JOIN {$lm2_db_prefix}drivers ON driver_member = eb_driver
		WHERE eb_event = $event AND eb_driver <> $lm2_guest_member_id
		ORDER BY eb_name
		", __FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($query)) {
		printf("<TR><TD>%s</TD><TD ALIGN='RIGHT'>%skg</TD></TR>\n", $row['name'], $row['eb_ballast']);
	}
	mysql_free_result($query);
	echo "</TABLE>\n";
}

function lm2ArrayValue($array, $key) {
	return array_key_exists($key, $array) ? $array[$key] : null;
}

function lm2RecentUpcoming($event = -1, $topic = -1) {
	global $lm2_db_prefix, $lm2_circuit_html_clause, $lm2_mods_group, $user_info, $ID_MEMBER, $settings;

	$ukgpl = ($settings['theme_id'] == 6); // If any other leagues were to join we could put a theme-lock in the event group table.

	$events = array("champs"=>array(), "recent"=>array(), "coming"=>array());

	global $modSettings;
	$modSettings['disableQueryCheck'] = true;

	$query = db_query("
		SELECT DISTINCT id_event_group, short_desc, series_theme
		FROM {$lm2_db_prefix}event_groups
		JOIN {$lm2_db_prefix}championships ON event_group = id_event_group
		JOIN {$lm2_db_prefix}events ON {$lm2_db_prefix}events.event_group = id_event_group
		AND id_event_group " . ($ukgpl ? "" : "NOT "). "IN (SELECT contained FROM {$lm2_db_prefix}event_group_tree WHERE container = 64)
		WHERE event_date BETWEEN DATE_ADD(" . lm2Php2timestamp() . ", INTERVAL -45 DAY) AND DATE_ADD(" . lm2Php2timestamp() . ", INTERVAL 45 DAY)
		ORDER BY short_desc
		", __FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($query)) {
		$text = lm2MakeEventGroupLink($row['id_event_group'], $row['short_desc'], $row['series_theme']);
		if (lm2ArrayValue($_REQUEST, 'action') == 'LM2R' && lm2ArrayValue($_REQUEST, 'group') == $row['id_event_group']) {
			$text = "<B>$text</B>";
		}
		$events["champs"][] = $text;
	}
	mysql_free_result($query);

	$query = db_query("
		SELECT id_event, smf_topic
		, id_event_group
		, $lm2_circuit_html_clause AS circuit_html
		, short_desc AS event_group
		, event_date
		, SUM(id_event_entry) AS entries
		, IFNULL(server_starter_override, server_starter) AS server_starter
		, full_desc AS event_group_full
		, {$lm2_db_prefix}sims.sim_name AS sim_desc
		FROM {$lm2_db_prefix}events
		JOIN {$lm2_db_prefix}sims ON sim = id_sim
		JOIN {$lm2_db_prefix}sim_circuits ON id_sim_circuit = sim_circuit
		JOIN {$lm2_db_prefix}circuits ON id_circuit = {$lm2_db_prefix}sim_circuits.circuit
		JOIN {$lm2_db_prefix}circuit_locations ON id_circuit_location = circuit_location
		JOIN {$lm2_db_prefix}event_groups ON event_group = id_event_group
		LEFT JOIN {$lm2_db_prefix}event_entries ON id_event = event
		WHERE event_date BETWEEN DATE_ADD(" . lm2Php2timestamp() . ", INTERVAL -15 DAY) AND DATE_ADD(" . lm2Php2timestamp() . ", INTERVAL 15 DAY)
		AND id_circuit <> -1
		AND {$lm2_db_prefix}events.sim " . ($ukgpl ? "=" : "<>") . " 8
		GROUP BY IFNULL(smf_topic, CONCAT(id_sim_circuit, '/', id_event_group))
		ORDER BY event_date ASC
		", __FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($query)) {
		$content = "";

		$max_link_len = ($event == $row['id_event'] || $topic == $row['smf_topic']) ? 20 : 23; // Bold ones are longer...
		if (strlen($link_html = "{$row['event_group']} {$row['circuit_html']}") > $max_link_len) {
			$link_html = substr($link_html, 0, $max_link_len - 1) . '&#133;';
		}
		$link = lm2MakeEventLink($row['id_event'], $row['smf_topic']);
		if (!$row['entries']) {
			$cssClass = null;
			if (in_array($lm2_mods_group, $user_info['groups'])) {
				if ($row['server_starter'] == $ID_MEMBER) {
					$cssClass = 'lm2eventAmStarter';
				} else if (is_null($row['server_starter'])) {
					$cssClass = 'lm2eventNoStarter';
				} else if (is_null($row['smf_topic'])) {
					$cssClass = 'lm2eventUnannounced';
				}
			}
			$link_html = "<I" . ($cssClass ? " CLASS='$cssClass'" : "") . ">$link_html</I>";
		}
		if ($event == $row['id_event'] || $topic == $row['smf_topic']) {
			$link_html = "<B>$link_html</B>";
		}
		$content .= "<SPAN TITLE='{$row['sim_desc']} - {$row['event_date']} - {$row['event_group_full']}'>$link<NOBR>$link_html</NOBR></A></SPAN>";

		$events[$row['entries'] ? "recent" : "coming"][] = $content;
	}
	mysql_free_result($query);

	return $events;
}

function lm2_make_password_link($row) {
	global $_REQUEST;
	if (is_null($reg_status = lm2_registration_status($row['id_event_group']))) {
		return "<B><I>You are not licensed to enter this series</I></B>";
	}
	$status = "Password: <B><BIG><TT>{$row['password']}</TT></BIG></B>";
	$hours = $row['tminus'] - 24;
	if ($hours > 0) {
		$status = "$hours hours until password released";
	}
	return "$status$reg_status";
}

//FIXME: merge this into above?
function lm2_registration_status($event_group) {
	global $user_info, $lm2_db_prefix, $db_prefix;

	$texts = array(
		'F'=>"full time",
		'1'=>"a first reserve",
		'2'=>"a second reserve",
		'3'=>"a third reserve",
	);

	$text = "";
	$reg_needed = false;
	$query = db_query("SELECT DISTINCT IF(champ_group_poll_choice IS NULL, NULL, id_group) AS group_id, champ_class_desc, champ_group_type, champ_group_poll_choice
		FROM {$lm2_db_prefix}championships
		, ${lm2_db_prefix}champ_groups
		, {$db_prefix}membergroups AS g
		, {$lm2_db_prefix}event_group_tree
		WHERE id_championship = champ_group_champ AND event_group = container AND contained = $event_group" /*FIXME: consider using a sub-SELECT */ . "
		AND champ_group_membergroup = g.id_group
		ORDER BY IFNULL(champ_group_poll_choice, -1) DESC
		, CASE champ_group_type WHEN 'L' THEN 0 WHEN 'F' THEN 1 WHEN '1' THEN 2 WHEN '2' THEN 3 ELSE 99 END
		, champ_sequence
		", __FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($query)) {
		$in_group = $row['group_id'] !== null && in_array($row['group_id'], $user_info['groups']);
		if ($row['champ_group_type'] == 'L') {
			if ($in_group) {
				continue; // They are licensed.
			} else {
				mysql_free_result($query);
				return null; // If there's a licensing group and they're not in it, they cannot join, so stop now.
			}
		} else if ($in_group) {
			$text = "{$texts[$row['champ_group_type']]} in {$row['champ_class_desc']}";
			break;
		} else if ($row['champ_group_poll_choice'] === null) {
			// Default status if no matching group found; no class shown.
			$text = $texts[$row['champ_group_type']];
		}
		$reg_needed = true; // If we got here then some sort of registration is needed.
	}
	mysql_free_result($query);

	if ($text) {
		$text = "you are $text";
	} else if (!$reg_needed) {
		$text = "open series";
	} else {
		return null;
	}

	return " ($text)";
}

function lm2MakeSmfCalendarEvents($low_date, $high_date, &$events) {
	global $lm2_db_prefix;

	$result = db_query("
		SELECT DISTINCT DATE(event_date) AS event_date, short_desc, brief_name, smf_topic
		FROM {$lm2_db_prefix}events
		JOIN {$lm2_db_prefix}event_groups ON event_group = id_event_group
		JOIN {$lm2_db_prefix}sim_circuits ON sim_circuit = id_sim_circuit
		JOIN {$lm2_db_prefix}circuits ON circuit = id_circuit
		JOIN {$lm2_db_prefix}circuit_locations ON circuit_location = id_circuit_location
		WHERE DATE(event_date) BETWEEN '$low_date' AND '$high_date'
		AND smf_topic IS NULL
		", __FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($result)) {
		$desc = "{$row['short_desc']} {$row['brief_name']}";
		$events[$row['event_date']][] = array(
			'link' => $desc, // It's not really a link, it's just HTML...
			'is_last' => false,
			'title' => $desc,
			'topic' => $row['smf_topic'],
			// Dummy stuff to prevent 'unknown index' type errors.
			'id' => null,
			'can_edit' => false,
			'modify_href' => '', //FIXME: use this as a link to adding/editing event and topic?
			'href' => '',
			//'start_date' => $row['startDate'],
			//'end_date' => $row['endDate'],
			'msg' => null,
			'poster' => null,
			'allowed_groups' => array(),
		);
	}
	mysql_free_result($result);
}

function lm2_show_event($event) {
	global $db_prefix, $lm2_db_prefix, $lm2_guest_member_id, $lm2_class_style_clause, $boardurl;
	global $lm2_ukgpl_migration_sim_driver;
?>
<TABLE BORDER="1" CELLSPACING="0" ALIGN="CENTER">
  <TR>
    <TH ROWSPAN="3">Driver<BR />&nbsp;Team</TH>
	<TH ROWSPAN="3" CLASS='smalltext' WIDTH='22' HEIGHT='14'>Nat.</TH>
    <TH ROWSPAN="3">Make</TH>
    <TH COLSPAN="1">Model</TH>
    <TH CLASS="smalltext">Class</TH>
    <TH COLSPAN="2" CLASS="windowbg">Qualifying</TH>
    <TH COLSPAN="6">Race</TH>
  </TR>
  <TR>
	<TH ROWSPAN="2" COLSPAN="2" CLASS='smalltext'>Tyres</TH>
    <TH ROWSPAN="2" CLASS="windowbg smalltext">Pos</TH>
    <TH ROWSPAN="2" CLASS="windowbg smalltext">Time/Gap</TH>
    <TH ROWSPAN="2" CLASS='smalltext'>Pos</TH>
    <TH ROWSPAN="2" CLASS='smalltext'>Time/Gap</TH>
    <TH CLASS='smalltext'>Laps</TH>
<?php if ($event['sim'] == 9) { ?>
    <TH CLASS='smalltext'><SMALL>Led</SMALL></TH>
<?php } else { ?>
    <TH TITLE="Pitstop data available only for rFactor" CLASS='smalltext'><SMALL>Stops</SMALL></TH>
<?php } ?>
    <TH ROWSPAN="2" CLASS='smalltext'>Best</TH>
    <TH ROWSPAN="2" CLASS='smalltext'>Retirement<BR>reason</TH>
  </TR>
  <TR>
    <TH COLSPAN="2" CLASS='smalltext'><?php echo $event['sim'] == 9 ? "Incidents" : "Ballast"; ?></TH>
  </TR>
<?php
//XXX: doesn't account for any default ballast
	$query = db_query("
		SELECT {$lm2_db_prefix}event_entries.*
		" . lm2MakeBallastFields("'&nbsp;'", "''") . "
		, IF(IFNULL(eb_ballast, 0) = IFNULL(ballast_driver, 0), NULL, eb_ballast) AS correct_ballast
		, IF(IFNULL(eb_name, driving_name) = driving_name, NULL, eb_name) AS correct_name
		, CONCAT('<B>', {$lm2_db_prefix}cars.car_name, '</B>', IFNULL(CONCAT(' (#',number,')'),'')) AS car_name
		, CONCAT({$lm2_db_prefix}sim_cars.vehicle, IFNULL(CONCAT('/', {$lm2_db_prefix}sim_cars.team), '')) AS sim_car_name
		, class_description, reg_class
		, $lm2_class_style_clause
		, driver_name AS realName
		, LOWER(iso3166_code) AS iso3166_code
		, iso3166_name
		, IFNULL(reason_desc, retirement_reason) AS retirement_reason
		, {$lm2_db_prefix}teams.id_team
		, {$lm2_db_prefix}teams.team_name
		, {$lm2_db_prefix}sim_cars.tyres
		, {$lm2_db_prefix}tyres.tyre_description AS tyre_name
		, CONCAT({$lm2_db_prefix}tyres.id_tyre,'.gif') AS tyre_image
		, {$lm2_db_prefix}tyres.url AS tyre_url
		, {$lm2_db_prefix}tyres.width AS tyre_width
		, {$lm2_db_prefix}tyres.height AS tyre_height
		, {$lm2_db_prefix}tyres.bgcolor AS tyre_bgcolor
		, manuf_url
		, manuf_name
		, manuf_image
		, manuf_width
		, manuf_height
		, manuf_bgcolor
		, IF(id_sim_drivers = $lm2_ukgpl_migration_sim_driver, NULL, IFNULL(IF(TRIM(driving_name) = '', NULL, driving_name), lobby_name)) AS driving_name
		, IFNULL(id_member, $lm2_guest_member_id) AS id_member
		, driver_type
		, incident_points
		, laps_led
		FROM {$lm2_db_prefix}event_entries
		LEFT JOIN {$lm2_db_prefix}teams ON {$lm2_db_prefix}event_entries.team = id_team
		LEFT JOIN {$lm2_db_prefix}sim_drivers ON sim_driver = id_sim_drivers
		LEFT JOIN {$db_prefix}members ON {$lm2_db_prefix}event_entries.member = id_member
		LEFT JOIN {$lm2_db_prefix}event_ballasts ON {$lm2_db_prefix}event_entries.member = eb_driver AND event = eb_event
		LEFT JOIN {$lm2_db_prefix}retirement_reasons USING (retirement_reason)  
		JOIN {$lm2_db_prefix}sim_cars ON sim_car = id_sim_car
		JOIN {$lm2_db_prefix}cars ON id_car = {$lm2_db_prefix}sim_cars.car
		JOIN {$lm2_db_prefix}classes ON car_class_c = id_class
		JOIN {$lm2_db_prefix}tyres ON id_tyre = tyres
		JOIN {$lm2_db_prefix}manufacturers ON id_manuf = manuf
		JOIN {$lm2_db_prefix}drivers ON driver_member = {$lm2_db_prefix}event_entries.member
		JOIN {$lm2_db_prefix}iso3166 ON iso3166_code = id_iso3166
		WHERE event = {$event['id_event']}
		ORDER BY IFNULL(race_pos, 999), IFNULL(race_laps, -999) DESC, IFNULL(qual_pos, 999)
	", __FILE__, __LINE__);
	$prev_race_laps = $winners_race_laps = null;
	$prev_race_time = null;
	$entries = 0;
	while ($row = mysql_fetch_assoc($query)) {
		//printf("<!-- %s -->\n", print_r($row, true));
		$rowStyle = sprintf(' CLASS="windowbg%d"', ($entries++ & 1) + 2);

		echo "<TR$rowStyle TITLE=\"{$row['id_event_entry']}\">\n";
	
		if ($row['member'] == $lm2_guest_member_id) {
			$driver_name = "<I>{$row['driving_name']}</I>";
		} else {
			$realName = $row['realName'];
			if ($row['correct_name']) {
				$realName = sprintf("<SPAN TITLE='Expected: %s' STYLE='color: red'>$realName</SPAN>",
					htmlentities($row['correct_name'], ENT_QUOTES));
			}
			$driver_name = "<B><A HREF='$boardurl/index.php?action=profile&u={$row['id_member']}&sa=racing_history&driver={$row['member']}#aliases'>$realName</A></B>";
		}
		$driver_tooltip = lm2_make_driver_tooltip($row, $event['sim']);
		echo "  <TD CLASS='smalltext' ROWSPAN=\"2\"$driver_tooltip>$driver_name\n
			<BR />&nbsp;" . lm2FormatTeam($row['id_team'], $row['team_name']) . "</TD>\n";

		echo "  <TD WIDTH='22' HEIGHT='14' ROWSPAN='2' TITLE='{$row['iso3166_name']}' ALIGN='CENTER' VALIGN='MIDDLE'>"
			. "<IMG WIDTH='22' HEIGHT='14' SRC='/images/flags-22x14/{$row['iso3166_code']}.gif'></TD>\n";
	
		lm2MakeImagedLinkCell('manuf', $row, 2, 1, 'manuf');
		echo "  <TD CLASS='smalltext' ALIGN='LEFT' TITLE='" . htmlentities($row['sim_car_name'], ENT_QUOTES) . "'>{$row['car_name']}</TD>\n";
		echo "  <TD CLASS='smalltext' ALIGN='RIGHT'{$row['class_style']}"
			. ($row['reg_class'] ? " TITLE='" . htmlentities($row['reg_class'], ENT_QUOTES) . "'" : "")
			. ">{$row['class_description']}</TD>\n";

		if (is_null($qual_main = $row['qual_pos']) && !is_null($row['start_pos'])) {
			$qual_main = "<I>{$row['start_pos']}</I>";
		}
		$qual_extra = "Class:{$row['qual_pos_class']}, Starting:{$row['start_pos']}/{$row['start_pos_class']}";
		echo "  <TD CLASS='windowbg' ROWSPAN='2' ALIGN=RIGHT TITLE='$qual_extra'>$qual_main</TD>\n";
		$qual_time_display = '';
		$qual_time = $row['qual_best_lap_time'];
		if (!is_null($qual_time)) {
			$qual_time_display = ($event['best_qual_time'] == $qual_time) ? lm2FormatTime($qual_time) : lm2FormatTimeGap($qual_time - $event['best_qual_time']);
			$qual_time_display .= "<BR /><SMALL>" . lm2FormatTimeAndSpeed($qual_time, null, $event['length_metres']) . "</SMALL>";
			$qual_class = $row['qual_pos'] == 1 ? "lm2bestO" : ($row['qual_pos_class'] == 1 ? "lm2bestC" : "windowbg");
		} else {
			$qual_class = "windowbg";
		}
		echo "  <TD CLASS='smalltext $qual_class' ROWSPAN='2' ALIGN=RIGHT TITLE='" . lm2FormatTime($qual_time) . "'>$qual_time_display</TD>\n";
	
		$race_pos = ">{$row['race_pos']}";
		$race_pos_tooltip = lm2_add_points($row['id_event_entry']);
		if ($row['excluded_c'] == 'Y') {
			$race_pos = " CLASS=\"lm2penalised\">Excluded";
		} else if ($race_pos_penalty = $row['race_pos_penalty']) {
			$race_pos = " CLASS=\"lm2penalised\"$race_pos (+$race_pos_penalty)";
		}
		if ($penalty_points = $row['penalty_points']) {
			$race_pos_tooltip .= "started with $penalty_points penalty points";
		}
		echo "  <TD ROWSPAN='2' ALIGN=RIGHT TITLE=\"$race_pos_tooltip\"$race_pos</TD>\n";
	
		$race_laps = $row['race_laps'];
		$race_time = $row['race_time_adjusted'];
		$race_time_display = $formatted_race_time = lm2FormatTime($race_time);
		if (is_null($prev_race_time) && is_null($prev_race_laps) && is_null($winners_race_laps)) {
			// First time through.
			$prev_race_time = $race_time;
			$winners_race_laps = $prev_race_laps = $race_laps;
		} else {
			if ($race_laps == $prev_race_laps) {
				if (!is_null($race_time) && !is_null($prev_race_time)) {
					$race_time_display = lm2FormatTimeGap($race_time - $prev_race_time);
				}
			} else {
				if (!is_null($race_laps)) {
					$race_time_display = "+" . ($winners_race_laps - $race_laps . "L");
				} else {
					$race_time_display = 'DNS';
				}
				$prev_race_laps = $race_laps;
				$prev_race_time = $race_time;
			}
		}
		if (!is_null($race_time)) {
			$race_time_display .= "<BR />" . lm2FormatTimeAndSpeed($race_time, null, $event['length_metres'] * $race_laps);
		}
		echo "  <TD ROWSPAN='2' CLASS='smalltext' ALIGN=RIGHT TITLE=\"$formatted_race_time\">$race_time_display</TD>\n";
		$distance = "";
		if (!is_null($event['length_metres']) && !is_null($race_laps)) {
			$distance = sprintf(" TITLE='%.3fkm/%.3fm'", $event['length_metres'] * $race_laps / 1000.0, $event['length_metres'] * $race_laps / 1609.3);
		}
		echo "  <TD CLASS='smalltext' ALIGN='RIGHT'$distance>$race_laps</TD>\n";
	
		echo "  <TD CLASS='smalltext' ALIGN=RIGHT>" . ($event['sim'] == 9 ? nbspIf0($row['laps_led']) : $row['pitstops']) . "</TD>\n";
	
		//FIXME: do we want to show best lap gaps too in a qualifying stylee?
		$best_class = $row['race_best_lap_pos'] == 1 ? "bestO" : ($row['race_best_lap_pos_class'] == 1 ? "bestC" : "");
		$best_lap_ranks = "{$row['race_best_lap_pos']}/{$row['race_best_lap_pos_class']}";
		echo "  <TD ROWSPAN='2' ALIGN=RIGHT CLASS=\"smalltext lm2$best_class\" TITLE=\"$best_lap_ranks\">"
			. lm2FormatTimeAndSpeed($row['race_best_lap_time'], '<BR />', $event['length_metres']) . "</TD>\n";
	
		echo "  <TD ROWSPAN='2' ALIGN=RIGHT>{$row['retirement_reason']}</TD>\n";
	
		echo "</TR>\n";
	
		echo "<TR$rowStyle>\n";
		lm2MakeImagedLinkCell('tyre', $row, 1, 2, 'tyres');
		if ($event['sim'] == 9) { // Hack for iRacing...
			$row['ballast'] = $row['incident_points'] ? $row['incident_points'] : "&nbsp;";
		} else if (!is_null($row['correct_ballast'])) {
			$row['ballast'] = "<SPAN STYLE='color: red'><SPAN TITLE='Expected driver ballast: {$row['correct_ballast']}kg'>*</SPAN>{$row['ballast']}</SPAN>";
		}
		echo "  <TD COLSPAN='2' CLASS='smalltext' ALIGN=RIGHT>{$row['ballast']}</TD>\n";
		echo "</TR>\n";
	}
	mysql_free_result($query);
?>
</TABLE>
<?php
}

function nbspIf0($n) {
	return $n ? $n : "&nbsp;";
}

function lm2_make_driver_tooltip($row, $id_sim) {
	$sep = "";

	if (strcasecmp($row['driving_name'], $row['realName'])) {
		$text = htmlentities("Driving as: {$row['driving_name']}", ENT_QUOTES);
		$sep = " &#151; ";
	} else {
		$text = "";
	}

	switch ($row['driver_type']) {
	case 'A':
		$text .= "{$sep}A.I.";
		break;
	case 'S':
		$text .= "{$sep}Server";
		break;
	case 'G':
		$text .= "{$sep}Non-scoring guest";
		break;
	}

	return $text ? " TITLE=\"$text\"" : "";
}

function lm2_add_points($entry) {
	global $lm2_db_prefix;

	$text = "";
	$query = db_query("SELECT champ_class_desc, position, points, champ_type
		FROM {$lm2_db_prefix}event_points, {$lm2_db_prefix}championships
		WHERE event_entry = $entry AND championship = id_championship
		", __FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($query)) {
		$text .= $row['champ_class_desc'] . "/" . $row['champ_type'] . "=" . $row['position'] . "/" . $row['points'] . "&#10;";
	}
	mysql_free_result($query);
	
	return $text;
}

function lm2_show_penalties($event) {
	global $lm2_db_prefix;

	echo "<DIV ALIGN='LEFT' CLASS='smalltext'>\n";
	$query = db_query("SELECT id_incident, replay_time, description, is_comment, sim
		FROM {$lm2_db_prefix}incidents
		JOIN {$lm2_db_prefix}events ON id_event = event
		WHERE event = $event
		ORDER BY replay_time
	", __FILE__, __LINE__);
	$rows = 0;
	while ($row = mysql_fetch_assoc($query)) {
		$replay_time = lm2_format_incident_time($row['replay_time'], $row['sim']);
		$description = nl2br(htmlentities($row['description'], ENT_QUOTES));
		$is_comment = $row['is_comment'] == 1;
	
		echo $rows++ ? '<HR WIDTH="50%" />' : "<H2 ALIGN='CENTER'>Moderator's Report</H2>";

		if (!$is_comment) echo "<P ALIGN=LEFT>Server replay time: <B>{$replay_time}</B></P>\n";
		echo "<P ALIGN=LEFT>$description<UL>\n";
		lm2AddPenalties($row['id_incident'], $row['is_comment']);
		echo "</UL></P>\n";
	}
	mysql_free_result($query);
	echo "</DIV>\n";
}

function lm2_format_incident_time($seconds, $sim) {
	if ($seconds) {
		if ($sim == 8) {
			$seconds = sprintf("%dh%02dm%02ds", floor($seconds / 3600), floor($seconds / 60) % 60, $seconds % 60);
		} else {
			$seconds .= "s";
		}
	}
	return $seconds;
}

function lm2AddPenalties($incident, $is_comment) {
	global $lm2_db_prefix;
	global $db_prefix;
	global $lm2_penalty_types;
	global $lm2_ukgpl_migration_sim_driver;

	$query = db_query("
		SELECT driver_name AS realName
		, IF(sim_driver <> $lm2_ukgpl_migration_sim_driver, IF(driving_name <> '', driving_name, lobby_name), NULL) AS gameName
		, driver_member AS member
		, description
		, seconds_added
		, positions_lost
		, NULLIF(extra_positions_lost, 0) AS extra_positions_lost
		, points_lost
		, hist_autoban, hist_ban, hist_suspended_ban, hist_ratpoo
		, penalty_type
		, IFNULL(victim_report, 'Y') AS victim_report
		, IFNULL(excluded, 'N') AS excluded
		FROM {$lm2_db_prefix}penalties
		JOIN {$lm2_db_prefix}event_entries ON id_event_entry = event_entry
		JOIN {$lm2_db_prefix}drivers ON driver_member = {$lm2_db_prefix}event_entries.member
		JOIN {$lm2_db_prefix}sim_drivers ON id_sim_drivers = sim_driver
		WHERE incident = $incident
		ORDER BY realName
		", __FILE__, __LINE__);
	$rows = 0;
	while ($row = mysql_fetch_assoc($query)) {
		$who = $row['realName'];
		$whoGame = $row['gameName'];
		if ($whoGame && $whoGame != $who) {
			$who .= " <I>(" . htmlentities($whoGame, ENT_QUOTES) . ")</I>";
		}

		echo "<LI><A HREF=\"/index.php?ind=lm2&driver={$row['member']}\">$who</A>";
		if ($type_text = $lm2_penalty_types[$row['penalty_type']]) {
			echo " &mdash; <B>$type_text</B>";
		} else {
			echo "<!-- unknown penalty type '{$row['penalty_type']}' -->";
		}

		if ($row['description']) {
			printf(" &mdash; %s", htmlentities($row['description'], ENT_QUOTES));
		}

		if ($row['points_lost']) {
			echo " &mdash; {$row['points_lost']} championship points";
		}

		if ($row['hist_autoban']) {
			echo " &mdash; driver on probation, automatic ban";
		}

		if ($row['hist_ban']) {
			echo " &mdash; {$row['hist_ban']} race ban";
		}

		if ($row['hist_suspended_ban']) {
			echo " &mdash; {$row['hist_suspended_ban']} race ban (suspended)";
		}

		if ($row['hist_ratpoo']) {
			echo " &mdash; retired at the point of offence";
		}

		if ($row['excluded'] == 'Y') {
			echo " &mdash; excluded";
		} else {
			if (!is_null($seconds_added = $row['seconds_added'])) {
				echo " &mdash; $seconds_added second" . ($seconds_added == 1.0 ? "" : "s") . " added";
			}

			$extra_positions_lost = $row['extra_positions_lost'];
			if (!is_null($positions_lost = $row['positions_lost'])) {
				echo " &mdash; $positions_lost place" . ($positions_lost == 1 ? "" : "s") . " lost";
//XXX: there may be extra places lost in UKGPL even if no places are lost
				if (!is_null($extra_positions_lost) && $extra_positions_lost != 0) {
					echo " (plus $extra_positions_lost for penalty points)";
				}
			} else if (!is_null($extra_positions_lost)) {
				echo " &mdash; also, $extra_positions_lost place" . ($extra_positions_lost == 1 ? "" : "s") . " lost for penalty points";
			}
		}

		if ($row['victim_report'] != 'Y') {
			echo "<BR /><I>For advice only - unreported by victim, so penalty disregarded</I>";
		}
		echo "</LI>\n";
	
		++$rows;
	}
	mysql_free_result($query);

	if ($rows == 0 && !$is_comment) {
		echo "<LI>Racing incident</LI>\n";
	}

	return $rows;
}

function lm2MakeWeatherLinks($location_row) {
	global $boarddir;//, $lm2_mods_group, $user_info;

	$links = array();

	$wstation = $location_row['wu_station'];
	$wstation = false; // Maybe fix it some day?
	if ($wstation) {
		$links['rssId'] = str_replace("/", "_", "WU_$wstation");
		$links['rssUrl'] = "http://www.weatherunderground.com/auto/rss_full/$wstation.xml";
		$links['rssFile'] = "$boarddir/../mkportal/cache/{$links['rssId']}.rss";
	} else {
		$links['rssId'] = $links['rssUrl'] = $links['rssFile'] = null;
	}

	if (!is_null($n = $location_row['latitude_n']) && !is_null($e = $location_row['longitude_e'])) {
		$links['weatherLink'] = "<A HREF='https://www.wunderground.com/forecast/$n%2C$e'>Weather Underground</A>";
		$links['climateLink'] = sprintf("<A HREF='http://www.worldclimate.com/cgi-bin/grid.pl?gr=%s%02d%s%03d'>World Climate</A>", $n < 0 ? 'S' : 'N', abs($n), $e < 0 ? 'W' : 'E', abs($e));
	} else {
		$links['weatherLink'] = $links['climateLink'] = null;
	}

	if (/*in_array($lm2_mods_group, $user_info['groups']) && */$location_row['id_circuit_location'] && $wstation) {
		$links['generateLink'] = "<A HREF='/lm2/weather.php?location={$location_row['id_circuit_location']}'>Generate weather</A>";
	} else {
		$links['generateLink'] = null;
	}

	return $links;
}

function lm2MakeRssWeather($links) {
return; //TODO: fix weather
	if (file_exists($links['rssFile']) && ($rssXml = file_get_contents($links['rssFile']))) {
		//TODO: share some of this with the generator?
		//XXX: figure out how to not bother if it's very old

		if (!($dom = DOMDocument::loadXML($rssXml))) return;

		$root = $dom->documentElement;
		($root->localName == 'rss') || die("root node is not rss");
		($root->getAttribute('version') == '2.0') || die("only version 2.0 of RSS");
		($channel = lm2GetSingleElement($root, 'channel')) || die("can't find any channels");
		($items = $channel->getElementsByTagName('item')) || die("can't find any items");

		echo lm2_table_open("RSS Weather");

		foreach ($items AS $item) {
			$title = lm2GetSingleElementText($item, 'title');
			$url = lm2GetSingleElementText($item, 'link');
			$desc = lm2GetSingleElementText($item, 'description');

			echo "<P><B><A HREF='$url'>$title</A></B><BR/>$desc</P>";
		}

		echo lm2_table_close();
	}
}

// XML utilities.

function lm2GetSingleElementText($parent, $tagname, $default = 'MAGIC-NO-DEFAULT') {
	$node = lm2GetSingleElement($parent, $tagname, $default != 'MAGIC-NO-DEFAULT');
	if (is_null($node)) {
		if ($default == 'MAGIC-NO-DEFAULT') {
			die("node $tagname was missing");
		}
		return $default;
	}
    return lm2GetElementText($node);
}

function lm2GetSingleElement($parent, $tagname, $null_if_missing = false) {
    $results = $parent->getElementsByTagName($tagname);
    if ($null_if_missing && count($results) == 0)
    	return null;
    count($results) == 1 || die("expecting exactly one $tagname");
    return $results->item(0);
}

function lm2GetElementText($node) {
	$st = "";
	foreach ($node->childNodes as $cnode) {
 		if ($cnode->nodeType == XML_TEXT_NODE) {
			$st .= utf8_decode($cnode->nodeValue);
	 	} else if ($cnode->nodeType == XML_CDATA_SECTION_NODE) {
	 		$st .= utf8_decode($cnode->nodeValue);
		} else die("unknwon node type " . $cnode->nodeType);
	}
	return $st;
}

function lm2GetRequestParam($name) {
	$value = lm2ArrayValue($_REQUEST, $name);
	if (!is_null($value)) {
		$value = stripslashes($value);
	}
	return $value;
}

function lm2SqlString($s, $emptyIfNull = false) {
	if ($emptyIfNull) {
		if ($s == null) {
			$s = '';
		}
	} else {
		if ($s == '') {
			$s = null;
		}
	}
	return is_null($s) ? "NULL" : "'" . mysql_real_escape_string($s) . "'";
}

function lm2ShowDriverInfo($context) {
	global $lm2_db_prefix, $db_prefix, $lm2_ukgpl_prefix;

	echo lm2_table_open("Driver Details");
	$iso3166_code = lm2GetRequestParam('iso3166_code');
	$gplrank = lm2GetRequestParam('gplrank');

	if (!is_numeric($gplrank)) {
		$gplrank = 'NULL';
	}

	if (!is_null($iso3166_code)) {
		global $modSettings;
		$modSettings['disableQueryCheck'] = true;
		db_query("
			INSERT INTO {$lm2_db_prefix}drivers
			(driver_member, driver_name, iso3166_code, gplrank) VALUES (
			{$context['member']['id']}
			, '{$context['member']['name']}'
			, " . lm2SqlString($iso3166_code) . "
			, $gplrank)
			ON DUPLICATE KEY
			UPDATE iso3166_code = " . lm2SqlString($iso3166_code) . "
			, gplrank = $gplrank
			", __FILE__, __LINE__);

		$query = db_query("SELECT id_sim FROM {$lm2_db_prefix}sims WHERE use_driver_details = 'Y'",__FILE__, __LINE__);
		while ($row = mysql_fetch_assoc($query)) {
			if ($row['name'] = lm2GetRequestParam("sim{$row['id_sim']}_name")) {
				$row['name'] = lm2SqlString($row['name']);
				db_query("
					INSERT INTO {$lm2_db_prefix}driver_details
					(driver, sim, driving_name) VALUES (
					{$context['member']['id']}, {$row['id_sim']}, {$row['name']}
					) ON DUPLICATE KEY UPDATE driving_name = {$row['name']}
					", __FILE__, __LINE__);
			}
		}
		mysql_free_result($query);
	}

	if (is_numeric($ukgpl_driver = lm2GetRequestParam('ukgpl_driver'))) {
		db_query("
			INSERT IGNORE INTO {$lm2_ukgpl_prefix}_map_drivers
			(hist_driver, live_driver, approved)
			VALUES ($ukgpl_driver, {$context['member']['id']}, 0)
			", __FILE__, __LINE__);
	}

	$query = db_query("
		SELECT iso3166_code, gplrank
		FROM {$lm2_db_prefix}drivers
		WHERE driver_member = {$context['member']['id']}
		",__FILE__, __LINE__);
	if ($row = mysql_fetch_assoc($query)) {
		$iso3166_code = $row['iso3166_code'];
		if (is_numeric($gplrank = $row['gplrank']) && $gplrank >= 0) {
			$gplrank = "+$gplrank";
		}
	} else {
		$iso3166_code = 'XX';
		$gplrank = '';
	}
	mysql_free_result($query);

	echo "<FORM METHOD='POST'><TABLE>
		<TR><TD>Country:</TD><TD><SELECT NAME='iso3166_code'>";
	$query = db_query("SELECT id_iso3166 AS id, iso3166_name AS description FROM {$lm2_db_prefix}iso3166 ORDER BY description",__FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($query)) {
		$sel = "";
		if ($iso3166_code == $row['id']) {
			$sel = " SELECTED";
			$iso3166_code = null;
		}
		echo "<OPTION VALUE='{$row['id']}'$sel>{$row['description']}</OPTION>\n";
	}
	mysql_free_result($query);
	if ($iso3166_code) {
		echo "<OPTION VALUE='$iso3166_code'>$iso3166_code</OPTION>\n";
	}
	echo "</SELECT></TD></TR>\n";

	$query = db_query("
		SELECT id_sim, sim_name, driving_name
		FROM {$lm2_db_prefix}sims
		LEFT JOIN {$lm2_db_prefix}driver_details ON sim = id_sim AND driver = {$context['member']['id']}
		WHERE use_driver_details = 'Y'
		",__FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($query)) {
		$row['driving_name'] = $row['driving_name'] ? htmlentities($row['driving_name'], ENT_QUOTES) : $context['member']['name'];
		echo "<TR><TD COLSPAN='3'><B><U>{$row['sim_name']}</U></B></TD></TR>
			<TR><TD>Driving Name:</TD>
				<TD><INPUT TYPE='EDIT' NAME='sim{$row['id_sim']}_name' VALUE='{$row['driving_name']}' SIZE='30' MAXLENGTH='32'></TD>
				<TD ALIGN='LEFT'><I>This is <B>CASE SENSITIVE!</B> and should be at least 2 characters</I></TD></TR>\n";
	}
	mysql_free_result($query);

	echo "<TR><TD COLSPAN='3'><B><U>Grand Prix Legends</U></B></TD></TR>
		<TR><TD><A HREF='http://gplrank.schuerkamp.de/'>GPLRank</A>:</TD>
			<TD><INPUT TYPE='EDIT' NAME='gplrank' VALUE='$gplrank' SIZE='8' MAXLENGTH='8'></TD>
			<TD ALIGN='LEFT'><I>eg. +123.456, or -13.666</I></TD></TR>
		<TR><TD>Historic identity:</TD><TD COLSPAN='2'><SELECT NAME='ukgpl_driver'>
				<OPTION VALUE='none'></OPTION>\n";
	$query = db_query("
		SELECT driver_member AS id, driver_name AS name, approved
		FROM {$lm2_db_prefix}drivers
		LEFT JOIN {$lm2_ukgpl_prefix}_map_drivers ON driver_member = hist_driver
		WHERE driver_member > 10000000
		AND (live_driver = {$context['member']['id']} OR approved IS NULL)
		ORDER BY name
		", __FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($query)) {
		switch ($row['approved']) {
		case null: // Otherwise the 0 picks it up!
			break;
		case 0:
			$row['name'] .= " (pending)";
			break;
		case 1:
			$row['name'] .= " (actioned)";
			break;
		}
		echo "<OPTION VALUE='{$row['id']}'" . (is_null($row['approved']) ? "" : " SELECTED") . ">{$row['name']}</OPTION>\n";
	}
	mysql_free_result($query);
	echo "</SELECT></TD></TR>
			<TR><TD COLSPAN='3' ALIGN='RIGHT'><INPUT TYPE='SUBMIT' VALUE='Update Details'></TD></TR>
		</TABLE></FORM>\n";
	echo lm2_table_close();
}

function lm2ShowDriverProfile($driver) {
	global $lm2_circuit_link_clause, $lm2_circuit_html_clause, $lm2_penalty_points_clause;
	global $colsep;
	global $ID_MEMBER, $boardurl;
	global $context, $lm2_db_prefix, $db_prefix;
	
	echo lm2_table_open("Aliases");
?><A NAME="aliases"></A>
	<TABLE BORDER='1' CELLPADDING='2' CELLSPACING='0'>
	<TR><TH>Sims</TH><TH>Driving Names</TH><TH>Lobby Names</TH></TR>
<?php
	$query = db_query("SELECT sim_name, driving_name, lobby_name
		FROM {$lm2_db_prefix}sim_drivers
		JOIN {$lm2_db_prefix}sims ON sim = id_sim
		WHERE member = $driver
		",__FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($query)) {
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
	mysql_free_result($query);
?>
	</TABLE>
<?php
	echo lm2_table_close();

	$header = lm2_table_open("Championship Registrations and Licenses") . "<TABLE>\n";
	$footer = "";
	$query = db_query("
		SELECT groupName, champ_group_type, id_event_group, full_desc, series_theme, champ_class_desc
		FROM {$lm2_db_prefix}champ_groups
		JOIN ${lm2_db_prefix}championships ON id_championship = champ_group_champ
		JOIN {$lm2_db_prefix}event_groups ON id_event_group = event_group
		JOIN {$db_prefix}members
		JOIN {$db_prefix}membergroups AS g ON g.id_group = champ_group_membergroup
		WHERE id_member = $driver
		AND CONCAT(',', additionalGroups, ',') REGEXP CONCAT(',', g.id_group, ',')
		GROUP BY g.id_group, id_event_group
		ORDER BY champ_sequence
		" , __FILE__, __LINE__);
	while (($row = mysql_fetch_assoc($query))) {
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
		echo "<TR TITLE='{$row['groupName']}'>
			<TD>" . lm2MakeEventGroupLink($row['id_event_group'], $row['full_desc'], $row['series_theme']) . "</TD>
			<TD>{$row['champ_class_desc']}</TD>
			<TD>$type</TD>
			</TR>\n";
	}
	echo $footer;
	mysql_free_result($query);
	
	$header = lm2_table_open("Team History") . "<TABLE><TR><TH>Team</TH>$colsep<TH>Series</TH>$colsep<TH>Joined</TH>$colsep<TH>Left</TH></TR>\n";
	$footer = "";
	$query = db_query("SELECT team_name, date_from, date_to, id_team, short_desc"
		. " FROM (${lm2_db_prefix}teams, ${lm2_db_prefix}team_drivers)"
		. " LEFT JOIN ${lm2_db_prefix}event_groups ON event_group = id_event_group"
		. " WHERE team = id_team"
		. " AND date_from IS NOT NULL"
		. " AND member = $driver"
		. " ORDER BY date_from, date_to"
		, __FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($query)) {
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
	mysql_free_result($query);
	echo $footer;

	echo lm2_table_open("Career Statistics") . "<TABLE>\n";
	$query = db_query("SELECT COUNT(*) AS events_entered"
		. " FROM {$lm2_db_prefix}event_entries"
		. " WHERE member = $driver",
		__FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($query)) {
		echo "<TR><TD>Events entered</TD>$colsep<TD COLSPAN=3 ALIGN=RIGHT>{$row['events_entered']}</TD>\n";
	}
	mysql_free_result($query);
	
	echo "<TR><TD></TD>$colsep<TH ALIGN=RIGHT>Outright</TH>$colsep<TH ALIGN=RIGHT>In class</TH>\n";
	$query = db_query("
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
		FROM {$lm2_db_prefix}event_entries WHERE member = $driver
		", __FILE__, __LINE__); //FIXME: consider only including non-fun race event_types
	while ($row = mysql_fetch_assoc($query)) {
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
	mysql_free_result($query);

	lm2MakeChampStats('D', $driver);

	echo lm2_table_close() . "</TABLE>\n";

	lm2ShowLapRecords($driver, null, null, null);
	
	// License endorsements.
	$query = db_query("
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
		FROM {$lm2_db_prefix}event_entries
		JOIN {$lm2_db_prefix}events ON id_event = event
		JOIN {$lm2_db_prefix}penalties ON event_entry = id_event_entry
		JOIN {$lm2_db_prefix}sim_circuits ON id_sim_circuit = sim_circuit
		JOIN {$lm2_db_prefix}circuits ON id_circuit = circuit
		JOIN {$lm2_db_prefix}circuit_locations ON id_circuit_location = circuit_location
		JOIN {$lm2_db_prefix}event_groups ON id_event_group = event_group
		JOIN {$lm2_db_prefix}penalty_groups USING (penalty_group)
		WHERE $driver = member
		AND event_status IN ('O', 'H')
		GROUP BY penalty_group, id_event, IFNULL(victim_report, 'Y')
		HAVING penalty_points > 0
		AND end_date >= " . lm2Php2timestamp() . "
		", __FILE__, __LINE__);
	$closer = "";
	$currentGroupDesc = null;
	while ($row = mysql_fetch_assoc($query)) {
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
	mysql_free_result($query);

	lm2MakeEventList("member", $driver, "Full Event Entry List");
}

// Returns member ID, or null if none found.
function lm2FindEventModerator($event) {
	global $lm2_guest_member_id;
	return $lm2_guest_member_id;
//	global $lm2_db_prefix;
//
//	$mod_id = null;
//
//	$query = db_query("SELECT moderator"
//		. " FROM {$lm2_db_prefix}events"
//		. ", {$lm2_db_prefix}event_groups"
//		. ", {$lm2_db_prefix}event_group_tree"
//		. " WHERE event_group = contained AND container = id_event_group"
//		. " AND id_event = $event"
//		. " AND moderator IS NOT NULL"
//		. " ORDER BY depth ASC"
//		, __FILE__, __LINE__);
//	while ($row = mysql_fetch_assoc($query)) {
//		$mod_id = $row['moderator'];
//		break;
//	}
//	mysql_free_result($query);
//	return $mod_id;
}

//TODO: remove all calls to this and just get the full_desc directly...
function lm2FullEventGroupName($group) {
	global $lm2_db_prefix;

	$query = db_query("SELECT full_desc FROM {$lm2_db_prefix}event_groups WHERE id_event_group = $group", __FILE__, __LINE__);
	($row = mysql_fetch_assoc($query)) || die("can't find group $group");
	mysql_fetch_assoc($query) && die("multiple groups matching $group!");
	mysql_free_result($query);

	return $row['full_desc'];
}


function format_time_and_speed($seconds, $divider, $metres) {
	return lm2FormatTimeAndSpeed($seconds, $divider, $metres);
}

function lm2FormatTimeAndSpeed($seconds, $divider, $metres) {
	if ($seconds == 0) {
		$time = $mph = "---";
	} else {
		$time = lm2FormatTime($seconds);
		$mph = $metres < 0 ? -$metres : ($metres / 1609.3) / ($seconds / 3600.0);
		$mph = $mph ? sprintf('%7.3fmph', $mph) : '---';
	}
	return (is_null($divider) ? "" : "$time$divider") . $mph;
}

function lm2FormatTime($t) {
	return lm2_format_time_internal($t, false);
}

function lm2FormatTimeGap($t) {
	return lm2_format_time_internal($t, true);
}

function lm2_format_time_internal($t, $alwaysSign) {
	if (!$t || $t == 0)
		return '';

	$s = "";
	$prefix = "";

	if ($t < 0) {
		$t = -$t;
		$prefix = "-";
	} else if ($alwaysSign) {
		$prefix = "+";
	}
	
	$sep = "";
	$protection = 0;
	$fmt1 = "%05.3F";
	$fmt2 = "%06.3F";
	do {
		if (++$protection == 10)
			break;
		$p = fmod((double) $t, 60);
		$t = floor($t / 60);
		$s = sprintf(($t > 0 ? $fmt2 : $fmt1), $p) . $sep . $s;
		$fmt1 = "%01.0F";
		$fmt2 = "%02.0F";
		$fmt = ".0F";
		$sep = ":";
	} while ($t > 0);

	$s = "$prefix$s";

	return $s;
}

function lm2FormatTeam($id, $name, $urlNotUsed = null) {
	global $boardurl;
	return "<A HREF='$boardurl/index.php?action=LM2R&team=$id'>" . htmlentities($name, ENT_QUOTES) . "</A>";
}

function lm2MakeImagedLinkCell($base, $row, $rows, $cols, $dir) {
	$name = $row["{$base}_name"];
	echo "  <TD ROWSPAN='$rows' COLSPAN='$cols' CELLPADDING='0' ALIGN='CENTER' TITLE='$name'";
	if (($w = $row["{$base}_width"]) && ($h = $row["{$base}_height"]) && ($bg = $row["{$base}_bgcolor"])) {
		$tyre_TD = " STYLE='background-color: #$bg'";
		$tyre_IMG = "<IMG WIDTH='$w' HEIGHT='$h' ALT='$name' SRC='/images/$dir/" . $row["{$base}_image"] . "'  BORDER='0'>";
	} else {
		$tyre_TD = "";
		$tyre_IMG = $row["{$base}_name"];
	}
	if ($tyre_url = $row["{$base}_url"]) {
		$tyre_IMG = "<A HREF='$tyre_url'>$tyre_IMG</A>";
	}
	echo "$tyre_TD>$tyre_IMG";
	echo "</TD>\n";
}

function lm2MakeEventLink($event, $smf_topic = null) {
	global $boardurl;
	if (!$smf_topic) return "<A>";
	return "<A HREF='$boardurl/index.php?topic=$smf_topic#event$event'>";
}

//XXX: change all occurrences to pass theme and text...
function lm2MakeEventGroupLink($group, $text = null, $theme = null, $anchor = null, $extraParams = '') {
	global $boardurl;
	if (is_null($text)) {
		$text = lm2FullEventGroupName($group);
		$title = "";
	} else if ($group) {
		$title = ' TITLE="' . lm2FullEventGroupName($group) . '"';
	} else {
		$title = '';
	}
	$theme = "&theme=" . (is_null($theme) ? 4 : $theme);
	$anchor = is_null($anchor) ? '' : "#$anchor";
	return "<A HREF='$boardurl/index.php?action=LM2R&group=$group$theme$extraParams$anchor'$title>$text</A>";
}

function lm2_make_ballast_number($ballastExp) {
	$ballastExp = "($ballastExp)";
	return "CONCAT(IF($ballastExp < 0, '', '+'), $ballastExp, 'kg')";
}

function lm2MakeBallastFields($empty, $prefix) {
	$totalExp = "(IFNULL(ballast_car, 0) + IFNULL(ballast_driver, 0))";
	return ", IF(IFNULL(ballast_car, 0) <> 0"
		. " OR IFNULL(ballast_driver, 0) <> 0, CONCAT($prefix, '<SPAN TITLE=\"Car: ', IFNULL("
		. lm2_make_ballast_number('ballast_car') . ", 'none'), '; driver: ', IFNULL("
		. lm2_make_ballast_number('ballast_driver') . ", 'none'), '\">', "
		. lm2_make_ballast_number($totalExp) . ", '</SPAN>'), $empty) AS ballast";
}

function lm2ShowLapRecords($id_driver, $id_sim, $id_circuit, $id_event, $id_team = null, $opening = "
	<table cellpadding='3' cellspacing='0' border='0' width='100%' class='tborder' style='padding-top: 0; margin-bottom: 3ex;'>
	<tr><td class='titlebg' valign='middle' align='left' style='padding-left: 6px;'>SimRacing.org.uk Lap Records</td>
	</tr><tr><td width='5%' valign='top' class='windowbg' align='center'><table>
	", $closing = "</table></td></tr></table>")
{
	global $lm2_lap_record_clause, $lm2_lap_record_types, $lm2_db_prefix, $db_prefix, $lm2_circuit_link_clause, $lm2_class_style_clause;
	$query = db_query("
		SELECT class_description
		, $lm2_class_style_clause
		, lap_record_type
		, car_name
		, manuf_url
		, manuf_name
		, manuf_image
		, manuf_width
		, manuf_height
		, manuf_bgcolor
		, record_lap_time
		, record_mph
		, driver_name AS realName
		, id_event, smf_topic
		, id_event_group, short_desc, series_theme
		, event_date
		, driver_member AS id_member
		" . lm2MakeBallastFields("''", "'<BR/>'") . "
		" . ($id_circuit ? "" : ", $lm2_circuit_link_clause AS circuit_link, id_event_group") . "
		, {$lm2_db_prefix}sims.sim_name
		FROM {$lm2_db_prefix}event_entries
		JOIN {$lm2_db_prefix}events ON id_event = event" . ($id_event ? " AND id_event = $id_event" : ""). "
		JOIN {$lm2_db_prefix}sim_cars ON id_sim_car = sim_car
		JOIN {$lm2_db_prefix}cars ON id_car = car
		JOIN {$lm2_db_prefix}classes ON id_class = car_class_c
		JOIN {$lm2_db_prefix}sim_circuits ON id_sim_circuit = {$lm2_db_prefix}events.sim_circuit
		JOIN {$lm2_db_prefix}circuits ON id_circuit = {$lm2_db_prefix}sim_circuits.circuit" . ($id_circuit ? " AND id_circuit = $id_circuit" : "") . "
		JOIN {$lm2_db_prefix}lap_records ON id_class = record_class AND id_circuit = record_circuit
		JOIN {$lm2_db_prefix}circuit_locations ON id_circuit_location = circuit_location
		JOIN {$lm2_db_prefix}event_groups ON id_event_group = event_group
		JOIN {$lm2_db_prefix}sims ON id_sim = {$lm2_db_prefix}events.sim AND id_sim = {$lm2_db_prefix}lap_records.sim
		" . ($id_sim ? " AND id_sim = $id_sim" : "") . "
		JOIN {$lm2_db_prefix}drivers ON member = driver_member" . ($id_driver ? " AND $id_driver = driver_member" : "") . "
		JOIN {$lm2_db_prefix}manufacturers ON id_manuf = manuf
		WHERE $lm2_lap_record_clause" . ($id_team ? " AND $id_team = {$lm2_db_prefix}event_entries.team" : "") . "
		GROUP BY brief_name, lap_record_type, class_description, sim_name
		ORDER BY brief_name, display_sequence, lap_record_type, sim_name, record_lap_time, event_date
		", __FILE__, __LINE__);
	$sep = $opening;
	$closer = '';
	while ($row = mysql_fetch_assoc($query)) {
		$url = $row['id_event'] == $id_event ? "<A>" : lm2MakeEventLink($row['id_event'], $row['smf_topic']);
		echo "{$sep}<TR>"
			. ($id_circuit ? "" : "<TD CLASS='smalltext'>{$row['circuit_link']}</TD>")
			. "<TD CLASS='smalltext' ALIGN=\"CENTER\">{$row['sim_name']}"
			. "<BR/><SPAN{$row['class_style']}>{$row['class_description']}</SPAN></TD>"
			. "<TD CLASS='smalltext' ALIGN=\"RIGHT\"><B>" . lm2FormatTimeAndSpeed($row['record_lap_time'], "</B><BR/>", -$row['record_mph']) . "</TD>"
			. "<TD CLASS='smalltext'>";
		if (!$id_driver)
			echo "<A HREF='/index.php?ind=lm2&driver={$row['id_member']}'>{$row['realName']}</A><BR/>";
		echo "{$lm2_lap_record_types[$row['lap_record_type']]}</TD>";
		lm2MakeImagedLinkCell('manuf', $row, 1, 1, 'manuf');
		echo "<TD CLASS='smalltext'>{$row['car_name']}{$row['ballast']}</TD>"
			. "<TD CLASS='smalltext'>$url" . lm2FormatTimestamp(lm2Timestamp2php($row['event_date']), false) . "</A>"
			. "<BR/>" . lm2MakeEventGroupLink($row['id_event_group'], $row['short_desc'], $row['series_theme']) . "</TD>"
			. "</TR>\n";
		$sep = "";
		$closer = $closing;
	}
	echo $closer;
	mysql_free_result($query);
}

function lm2FormatTimestamp($time, $date_only) {
	return is_null($time) ? '' : ($date_only ? strftime('%d %B %Y', $time) : timeformat($time, false));
}

// TIMESTAMPs are actually DATETIMEs because MyPHPAdmin is very bad with timestamps. :-(

function lm2Php2timestamp($php_time = null, $utc = false) {
	if ($php_time == null) {
		$php_time = time();
	}
	$date_func = $utc ? 'gmdate' : 'date';
	return is_null($php_time) ? "NULL" : "'" . $date_func('YmdHis', $php_time) . "'";
}

function lm2Timestamp2php($mysql_timestamp, $utc = false) {
	if (is_null($mysql_timestamp)) return null;
	(sscanf($mysql_timestamp, "%d-%d-%d %d:%d:%d", $year, $month, $day, $hour, $minute, $second) == 6) || die("bad MySQL timestamp $mysql_timestamp");
	$mkdate_func = $utc ? 'gmmktime' : 'mktime';
	return $mkdate_func($hour, $minute, $second, $month, $day, $year);
}

function LM2R() {
	global $context, $db_prefix, $lm2_db_prefix, $ID_MEMBER, $boardurl;

	loadTemplate('lm2r');

	if (($team = lm2ArrayValue($_REQUEST, 'team')) == '*' || $team === '') {
		$context['page_title'] = "Registered Teams";
		$context['sub_template'] = 'teams';
	} else if (!is_null($team)) {
		is_numeric($team) || die("$team is not numeric");
		$query = db_query("SELECT * FROM {$lm2_db_prefix}teams WHERE id_team = $team", __FILE__, __LINE__);
		($context['lm2']['team'] = mysql_fetch_assoc($query)) || die("unknown team $team");
		mysql_fetch_assoc($query) && die("ambiguous team $team");
		mysql_free_result($query);
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
	
		$query = db_query("
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
			", __FILE__, __LINE__);
		($context['lm2']['location'] = mysql_fetch_assoc($query)) || die("unknown location $location");
		mysql_fetch_assoc($query) && die("ambiguous location $location");
		mysql_free_result($query);
	
		$context['page_title'] = "Circuit Information - {$context['lm2']['location']['brief_name']}";

		$query = db_query("
			SELECT id_circuit, layout_name
			FROM {$lm2_db_prefix}circuits
			WHERE circuit_location = $location
			ORDER BY id_circuit <> $circuit, layout_name
		", __FILE__, __LINE__);
		while ($row = mysql_fetch_assoc($query)) {
			$context['lm2']['circuits'][] = $row;
		}
		mysql_free_result($query);
	} else if (!is_null($group = lm2ArrayValue($_REQUEST, 'group'))) {
		is_numeric($group) || fatal_error("'$group' is not a number (referer " . lm2ArrayValue($_SERVER, 'HTTP_REFERER') . ")", true);
		$stats = lm2ArrayValue($_REQUEST, 'stats');
		if ((int)$group == 0) {
			$context['lm2']['group'] = array('full_desc'=>'All Series', 'block_text'=>null, 'id_event_group'=>null);
		} else {
			$query = db_query("
				SELECT full_desc, id_event_group, SUM(id_event IS NOT NULL) AS events, reg_topic, series_details
				, subject AS block_title, body AS block_text, smileysEnabled, mkp_pid AS pid
				FROM {$lm2_db_prefix}event_groups
				LEFT JOIN {$lm2_db_prefix}events ON event_group = id_event_group
				LEFT JOIN {$db_prefix}messages ON series_details = id_msg
				WHERE id_event_group = $group
				GROUP BY id_event_group
				", __FILE__, __LINE__);
			$context['lm2']['group'] = mysql_fetch_assoc($query);
			mysql_free_result($query);
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

//XXX: need to move the functions below to the template when MkPortal goes

function lm2ShowTeamMembers($current_team_id = null, $show_previous = true) {
	global $lm2_db_prefix, $db_prefix;

?>
<TABLE><TR>
<?php
	echo '<TH ALIGN="LEFT" ROWSPAN="2" VALIGN="TOP">' . ($current_team_id == null ? 'Team' : 'Members') . '</TH>'
?>
  <TH COLSPAN="4">&nbsp;</TH>
</TR><TR>
  <TH ALIGN="LEFT">Driver</TH>
  <TH>Series</TH>
  <TH>Joined</TH>
<TH><?php
	if ($show_previous) {
		echo "Resigned";
	}
	echo "</TH>\n</TR>\n";
	$query = db_query("SELECT DISTINCT driver_member AS id_member
		, short_desc AS group_desc
		, driver_name AS realName
		, id_team
		, team_name
		, date_from
		, date_to
		FROM ${lm2_db_prefix}teams 
		JOIN ${lm2_db_prefix}team_drivers ON id_team = team
		JOIN ${lm2_db_prefix}drivers ON driver_member = member
		LEFT JOIN ${lm2_db_prefix}event_groups ON event_group = id_event_group
		WHERE NOT team_is_fake
		" . ($current_team_id == null ? "" : " AND id_team = $current_team_id") . "
		AND date_from IS NOT NULL
		" . ($show_previous ? "" : " AND date_to IS NULL") . "
		AND (parent IS NULL OR parent <> event_group)
		ORDER BY " . lm2_team_name_order_by('team_name') . ", realName
		", __FILE__, __LINE__);
	$who = null;
	while ($row = mysql_fetch_assoc($query)) {
		if ($current_team_id != $row['id_team']) {
			$text = lm2FormatTeam(($current_team_id = $row['id_team']), $row['team_name']);
			echo "<TR><TD COLSPAN='5' ALIGN='LEFT'><B><BIG>$text</BIG></B></TD></TR>\n";
		}
		$from = lm2FormatTimestamp(lm2Timestamp2php($row['date_from']), false);
		$to = lm2FormatTimestamp(lm2Timestamp2php($row['date_to']), false);
		$group = $row['group_desc']; //FIXME: TITLE the link with its full description.
		$group = is_null($group) ? "<I>all</I>" : htmlentities($group, ENT_QUOTES);
		if ($who != $row['id_member']) {
			$driver_name = "<A HREF='/index.php?ind=lm2&driver={$row['id_member']}'>{$row['realName']}</A>";
			$who = $row['id_member'];
		} else {
			$driver_name = "&nbsp;&nbsp;&quot;";
		}
		echo "<TR><TD></TD><TD ALIGN=\"LEFT\">$driver_name</TD><TD>$group</TD><TD>$from</TD><TD>$to</TD></TR>\n";
	}
	mysql_free_result($query);

	echo "</TABLE>\n";
}

//FIXME: replace this with a MySQL stored function.
function lm2_team_name_order_by($nameColumn) {
    $ignorePrefixes = array(
        "Team ",
        "Scuderia ",
        "The ",
    );

    $pred = '';
    foreach ($ignorePrefixes AS $ignorePrefix) {
        $len = strlen($ignorePrefix);
        $pred .= "IF(LEFT($nameColumn,$len) = '$ignorePrefix', SUBSTRING($nameColumn," . ($len + 1) . "), ";
    }
    $pred .= "IFNULL($nameColumn, 'ZZZZZ')";
    foreach ($ignorePrefixes AS $ignorePrefix) {
        $pred .= ')';
    }

	return $pred;
}

function lm2MakeEventList($field, $id, $title = null) {
	global $colsep, $lm2_db_prefix, $lm2_circuit_link_clause, $lm2_guest_member_id;

	$driver_name = "IF(driver_member = $lm2_guest_member_id, IFNULL(IF(TRIM(driving_name) = '', NULL, driving_name), lobby_name), driver_name)";
	$query = db_query("SELECT id_event_group, id_event, smf_topic
		, short_desc, event_date
		" . ($field == 'id_circuit' ? ", {$lm2_db_prefix}event_entries.member, $driver_name AS driving_name, length_metres, race_time_adjusted AS race_time, race_laps, length_metres" : "") . "
		, MIN(race_pos_class) AS best_race_pos_class
		" . ($field == 'id_circuit' ? "" : ", $lm2_circuit_link_clause AS circuit_link") . "
		, IF(MIN(race_best_lap_pos) = 1, 'FL', IF(MIN(race_best_lap_pos_class) = 1, 'FL (C)', NULL)) AS fastest_race_lap
		, IF(MIN(qual_pos) = 1, 'Pole', IF(MIN(qual_pos_class) = 1, 'Pole (C)', NULL)) AS pole
		, driver_type
		FROM {$lm2_db_prefix}event_groups
		JOIN {$lm2_db_prefix}events ON id_event_group = event_group
		JOIN {$lm2_db_prefix}sim_circuits ON id_sim_circuit = sim_circuit
		JOIN {$lm2_db_prefix}circuits ON id_circuit = {$lm2_db_prefix}sim_circuits.circuit
		JOIN {$lm2_db_prefix}event_entries ON id_event = event
		LEFT JOIN {$lm2_db_prefix}sim_drivers ON sim_driver = id_sim_drivers
		" . ($field == 'id_circuit' ? ", {$lm2_db_prefix}drivers" : ", {$lm2_db_prefix}circuit_locations") . "
		WHERE " . ($field == 'member' ? "{$lm2_db_prefix}event_entries." : "") . "$field = $id
		AND " . ($field == 'id_circuit'
		   ? "race_pos = 1 AND driver_member = {$lm2_db_prefix}event_entries.member"
		   : "id_circuit_location = circuit_location") . "
		GROUP BY id_event
		ORDER BY event_date
		", __FILE__, __LINE__);
	$sep = ($title ? lm2_table_open("$title") : "") . "<TABLE>\n";
	$closer = "";
	while ($row = mysql_fetch_assoc($query)) {
		echo $sep;
		$sep = "";
		$closer = "</TABLE>\n" . ($title ? lm2_table_close() : "");
		$url = lm2MakeEventLink($row['id_event'], $row['smf_topic']);
		echo "<TR><TD>{$row['short_desc']}</TD>"
			. "$colsep<TD>$url" . lm2FormatTimestamp(lm2Timestamp2php($row['event_date']), false) . "</A></TD>";
		if ($field == 'id_circuit') {
			$driver_name = $row['driving_name'];
			if ($row['member'] != $lm2_guest_member_id) {
				$driver_name = "<A HREF=\"/index.php?ind=lm2&driver={$row['member']}\">{$driver_name}</A>";
			}
			echo "$colsep<TD>{$driver_name}</TD>"
				. "$colsep<TD ALIGN=RIGHT>" . lm2FormatTimeAndSpeed($row['race_time'],
					"</TD>$colsep<TD ALIGN=RIGHT>", $row['length_metres'] * $row['race_laps']) . "</TD>";
		} else {
			echo "$colsep
				<TD>{$row['circuit_link']}</TD>
				$colsep
				<TD ALIGN='RIGHT' CLASS='lm2position{$row['best_race_pos_class']}'>" . (is_null($row['best_race_pos_class']) ? "DNF" : $row['best_race_pos_class']) . "</TD>
				$colsep
				<TD ALIGN='LEFT'>{$row['pole']}</TD>
				$colsep
				<TD ALIGN='LEFT'>{$row['fastest_race_lap']}</TD>
				";
			if ($field == 'member' && $row['driver_type']) {
				echo "$colsep<TD><I>Non-scoring</I></TD>";
			}
		}
		echo "</TR>\n";
	}
	echo $closer;
	mysql_free_result($query);
}

function lm2MakeChampStats($champ_type, $id) {
	global $lm2_db_prefix, $colsep;

	$suffix = "$colsep<TH ALIGN='CENTER'>Class</TH>$colsep<TH ALIGN='RIGHT'>Position</TH>$colsep<TH ALIGN='RIGHT'>Points</TH>\n";
	$official_title = "<TR><TH ALIGN='LEFT'>Championship Results</TH>$suffix";
	$current_title = "<TR><TH ALIGN='LEFT'>Current Standings</TH>$suffix";
	$ranking_title = "<TR><TH ALIGN='LEFT'>Current Rankings</TH>$suffix";
	$query = db_query("
		SELECT position
		, points
		, id_event_group AS event_group, series_theme, full_desc AS event_group_desc
		, champ_class_desc
		, MAX(event_date) AS last_event
		, SUM(event_status = 'U') > 0 AS some_unofficial
		, scoring_type
		FROM {$lm2_db_prefix}championship_points
		JOIN {$lm2_db_prefix}championships ON id_championship = championship
		JOIN {$lm2_db_prefix}scoring_schemes ON id_scoring_scheme = scoring_scheme
		JOIN {$lm2_db_prefix}event_groups ON id_event_group = {$lm2_db_prefix}championships.event_group
		JOIN {$lm2_db_prefix}events ON id_event_group = {$lm2_db_prefix}events.event_group
		WHERE id = $id AND champ_type = '$champ_type'
		GROUP BY id_championship
		ORDER BY some_unofficial, scoring_type = 'C' AND 0, last_event
		", __FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($query)) {
		if (!$row['some_unofficial']) {
			echo $official_title;
			$official_title = '';
		} else if ($row['scoring_type'] == 'C') { // Will always show up in above - this is deliberate! Hence the "AND 0" above.
			echo $ranking_title;
			$ranking_title = '';
		} else {
			echo $current_title;
			$current_title = '';
		}
		//FIXME: drop class description when only one class had a championship.
		echo "<TR><TD>" . lm2MakeEventGroupLink($row['event_group'], $row['event_group_desc'], $row['series_theme']) . "</TD>"
			. "$colsep<TD ALIGN='CENTER'>{$row['champ_class_desc']}</TD>"
			. "$colsep<TD ALIGN='RIGHT' CLASS='lm2position{$row['position']}'>{$row['position']}</TD>$colsep<TD ALIGN=RIGHT>{$row['points']}</TD></TR>\n";
	}
	mysql_free_result($query);
}

function lm2MaybeAddEventText() {
	if (isset($_REQUEST['lm2group']) && isset($_REQUEST['lm2simCircuit']) && isset($_REQUEST['lm2sim'])) {
		is_numeric($_REQUEST['lm2group']) && is_numeric($_REQUEST['lm2simCircuit']) && is_numeric($_REQUEST['lm2sim']) || die('hacker be gone!');
		global $lm2_db_prefix, $db_prefix, $context, $func, $boardurl, $txt;

		$query = db_query("
			SELECT full_desc, short_desc, series_theme
			FROM {$lm2_db_prefix}event_groups
			WHERE id_event_group = {$_REQUEST['lm2group']}
			", __FILE__, __LINE__);
		($row = mysql_fetch_assoc($query)) || die("group {$_REQUEST['lm2group']} not found");
		$group = $row['short_desc'];
		$groupFull = $row['full_desc'];
		$groupTheme = $row['series_theme'];
		mysql_fetch_assoc($query) && die("topic {$_REQUEST['lm2group']} found more than once!");
		mysql_free_result($query);

		$query = db_query("
			SELECT id_circuit, brief_name AS name
			FROM {$lm2_db_prefix}sim_circuits
			, {$lm2_db_prefix}circuits
			, {$lm2_db_prefix}circuit_locations
			WHERE id_sim_circuit = {$_REQUEST['lm2simCircuit']}
			AND id_circuit = circuit AND id_circuit_location = circuit_location
			" , __FILE__, __LINE__);
		($row = mysql_fetch_assoc($query)) || die("circuit {$_REQUEST['lm2simCircuit']} not found");
		$circuit = $row['name'];
		$id_circuit = $row['id_circuit'];
		mysql_fetch_assoc($query) && die("topic {$_REQUEST['lm2simCircuit']} found more than once!");
		mysql_free_result($query);

		$_REQUEST['evtitle'] = "$group $circuit";
		$_REQUEST['subject'] = "$groupFull - $circuit - {$txt['months_short'][$_REQUEST['month']]} {$_REQUEST['day']}";
		$_REQUEST['message'] = "COPY THE TEXT IN!
Password: [iurl=#event_password]see above[/iurl]
(2) Driver lists can be found on the [url=$boardurl/index.php?action=LM2R;group={$_REQUEST['lm2group']}$groupTheme]championship standings page[/url]";

		$query = db_query("
			SELECT DISTINCT smf_topic, body
			, (event_group = {$_REQUEST['lm2group']}) AS same_group
			, (circuit = $id_circuit) AS same_circuit
			FROM {$lm2_db_prefix}events
			JOIN {$lm2_db_prefix}sim_circuits ON id_sim_circuit = sim_circuit
			JOIN {$db_prefix}topics ON id_topic = smf_topic
			JOIN {$db_prefix}messages ON id_first_msg = id_msg
			WHERE (event_group = {$_REQUEST['lm2group']} OR circuit = $id_circuit)
			AND {$lm2_db_prefix}events.sim = {$_REQUEST['lm2sim']}
			AND smf_topic IS NOT NULL
			ORDER BY same_group DESC, event_date DESC
			", __FILE__, __LINE__);
		$seen_group = false;
		$seen_circuit = false;
		while ($row = mysql_fetch_assoc($query)) {
			$row['body'] = un_htmlspecialchars(un_preparsecode($row['body']) );
			if (!$seen_group && $row['same_group']) {
				$seen_group = true;
				$seen_circuit = $row['same_circuit'] ? true : false;
				// Just replace all the text - don't need the template stuff.
				$_REQUEST['message'] = $row['body'];
			} else if (!$seen_circuit && $row['same_circuit']) {
				$seen_circuit = true;
				$_REQUEST['message'] .= "\n\n\n\n[quote author=MostRecentAtThisCircuit]{$row['body']}[/quote]";
			}
		}
		mysql_free_result($query);
	}
}

?>
