<?php
if (!defined("IN_MKP")) {
    die ("Sorry !! You cannot access this file directly.");
}

$idx = new mk_lm2;
class mk_lm2 {

	function mk_lm2() {
		global $mkportals, $mklib, $Skin, $mklib_board, $content, $db_prefix, $boardurl, $lm2_guest_member_id, $ID_MEMBER;
		global $is_firefox, $lm2_db_prefix, $penalty_types, $champ_types, $penalty_points_clause;

		$inc_file = null;
		$block_title_extra = '';
		$end_blocks = '';

		if ($driver = $_REQUEST['driver']) {
			if ($driver == 'self') {
				$driver = $ID_MEMBER;
			}
			$u = $driver;

			$query = lm2_query("
				SELECT driver_name AS realName, memberName
				FROM {$lm2_db_prefix}drivers
				LEFT JOIN {$db_prefix}members ON id_member = driver_member
				WHERE driver_member = $driver
			", __FILE__, __LINE__);
			($row = mysql_fetch_assoc($query)) || die("unknown driver $driver");
			if (is_null($membername = $row['memberName'])) {
				$u = $lm2_guest_member_id;
			}

			header("Location: $boardurl/index.php?action=profile&u=$u&sa=racing_history&driver=$driver");
		} else if ($team = $_REQUEST['team']) {
			header("Location: $boardurl/index.php?action=LM2R&team=$team");
			exit;
		} else if ($teams = $_REQUEST['teams']) {
			header("Location: $boardurl/index.php?action=LM2R&team=*");
			exit;
		} else if ($event = $_REQUEST['event']) {
			$query = lm2_query("SELECT event_group, smf_topic FROM {$lm2_db_prefix}events WHERE id_event = $event", __FILE__, __LINE__);
			$row = mysql_fetch_assoc($query);
			mysql_free_result($query);
			if ($row && ($smf = $row['smf_topic'])) {
				header("Location: $boardurl/index.php?topic=$smf");
				exit;
			}
			$page_title = "Unknown or published event $event";
			unset($_REQUEST['event']); // Stop the results block dying.
		} else if ($group = $_REQUEST['group']) {
			header("Location: $boardurl/index.php?action=LM2R&group=$group");
			exit;
		} else if (($circuit = $_REQUEST['circuit']) || ($location = $_REQUEST['location'])) {
			if ($circuit == "*") {
				header("Location: $boardurl/index.php?action=LM2R&circuit=*");
				exit;
			} else {
				if (!$location) {
					$query = lm2_query("SELECT circuit_location AS location FROM {$lm2_db_prefix}circuits WHERE id_circuit = $circuit", __FILE__, __LINE__);
					($row = mysql_fetch_assoc($query)) || die("unknown circuit $circuit");
					$location = $row['location'];
					mysql_fetch_assoc($query) && die("ambiguous circuit $circuit");
					mysql_free_result($query);
				} else {
					$circuit = 0; // Not trying to bring a particular layout to the top.
				}
				header("Location: $boardurl/index.php?action=LM2R&location=$location&circuit=$circuit");
				exit;
			}
		} else if ($event_group_probably = $_REQUEST['stats']) {
			//XXX: figure out what to do with this!
			$inc_file = "stats.php";
			$page_title = "Statistics";
		} else {
			$mklib->error_page("unknown LM2 reporting function");
			exit;
		}

		$rss_special_source = array();

		if ($inc_file) {
			ob_start();
			include $inc_file;
			if ($content = wrap(ob_get_clean(), true)) {
				$blocks .= $Skin->view_block("$page_title$block_title_extra", $content);
			}
		}

		if (count($rss_special_source) > 0) {
			global $MK_PATH;
			include("{$MK_PATH}mkportal/blocks/rss.php");
			if ($content = wrap($content, true)) {
				$end_blocks .= $Skin->view_block("RSS", $content);
			}
			unset($content);
		}
		unset($rss_special_source);

		$page_title = strip_tags($page_title);
		$mklib->printpage("1", "1", "{$mklib->sitename} - $page_title", "$blocks$end_blocks");
	}

	//XXX: remove and replace with calls to the SMF version.
	function make_rss_weather_link($location_row, &$rss_special_source) {
		$links = lm2MakeWeatherLinks($location_row);
		if ($links['rssId'] && $links['rssUrl']) {
			$rss_special_source[$links['rssId']] = $links['rssUrl'];
		}
		return $links['generateLink'];
	}

	//XXX: remove and replace with calls to the SMF version.
	function make_event_group_link($group, $text = null) {
		return lm2MakeEventGroupLink($group, $text);
	}

	//XXX: remove and replace with calls to the SMF version.
	function make_ballast_fields($empty, $prefix) {
		return lm2MakeBallastFields($empty, $prefix);
	}

	//XXX: remove and replace with calls to the SMF version.
	function show_lap_records($id_driver, $id_sim, $id_circuit, $id_event, $id_team = null) {
		return lm2ShowLapRecords($id_driver, $id_sim, $id_circuit, $id_event, $id_team, "<H3>SimRacing.org.uk Lap Records</H3><TABLE>", "</TABLE>");
	}

	//FIXME: remove and use lm2 one directly.
	function format_time_and_speed($seconds, $divider, $metres) {
		return lm2FormatTimeAndSpeed($seconds, $divider, $metres);
	}

	//FIXME: remove and use lm2 one directly.
	function formatTime($t) {
		return lm2FormatTime($t);
	}

	//FIXME: remove and use lm2 one directly.
	function format_time_gap($t) {
		return lm2FormatTimeGap($t);
	}

	//FIXME: remove and use lm2 one directly.
	function format_team($id, $name, $url) {
		return lm2FormatTeam($id, $name, $url);
	}

	//FIXME: remove and use lm2 one directly.
	function make_imaged_link_cell($base, $row, $rows, $cols, $dir) {
		lm2MakeImagedLinkCell($base, $row, $rows, $cols, $dir);
	}

	//FIXME: remove and use lm2 one directly.
	function make_champ_stats($champ_type, $id) {
		return lm2MakeChampStats($champ_type, $id);
	}

	//FIXME: remove this and use the SMF one.
	function make_event_list($field, $id, $title) {
		return lm2MakeEventList($field, $id, $title);
	}

	//FIXME: remove this and use the SMF one.
	function show_team_members($current_team_id = null, $show_previous = true) {
		return lm2ShowTeamMembers($current_team_id, $show_previous);
	}
}

function wrap($content, $blockOrNull = false) {
	$content = preg_replace('/<!--.*?-->/', '', $content);
	$content = trim($content);
	if (!$content && $blockOrNull)
		return null;
	return "<TR><TD ALIGN='CENTER'>\n$content\n</TD></TR>";
}
?>