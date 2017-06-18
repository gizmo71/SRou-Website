<?php
require_once("$sourcedir/Subs-Membergroups.php");
if (is_numeric($member = $_REQUEST['member']) && is_numeric($old_group = $_REQUEST['old_group']) && is_numeric($new_group = $_REQUEST['new_group'])) {
	if ($old_group != -1) {
		removeMembersFromGroups(array($member), array($old_group)) || die("failed to remove $member from $old_group");
	}

	if ($new_group != -1) {
		addMembersToGroup(array($member), $new_group, 'only_additional') || die("failed to add $member to $new_group");
	}
}
?>
<H1 ID="pleaseWait" STYLE="display: none">Please wait...</H1>
<SCRIPT>
function changeGroup(select, member, old_group, event_group) {
	var new_group = select.options[select.selectedIndex].value;
	//alert("change " + member + " from " + old_group + " to " + new_group);
	select.form.member.value = member;
	select.form.old_group.value = old_group;
	select.form.new_group.value = new_group;
	select.form.action += '#' + event_group;
    pleaseWait.style.display = 'block';
    mainBody.style.display = 'none';
	select.form.submit();
}
</SCRIPT>

<FORM METHOD="POST" ID="mainBody">
<INPUT TYPE="HIDDEN" NAME="member" value="" />
<INPUT TYPE="HIDDEN" NAME="old_group" value="" />
<INPUT TYPE="HIDDEN" NAME="new_group" value="" />
<TABLE>
<?php

$query = $smcFunc['db_query'](null, "
	SELECT DISTINCT id_event_group, short_desc, t.id_poll, reg_topic
	FROM {$lm2_db_prefix}event_groups
	JOIN {$lm2_db_prefix}championships ON event_group = id_event_group
	JOIN {$lm2_db_prefix}champ_groups ON id_championship = champ_group_champ
	JOIN {$db_prefix}topics t ON reg_topic = id_topic
	WHERE t.id_poll IS NOT NULL
	AND champ_group_poll_choice IS NOT NULL
	GROUP BY short_desc
	");
while ($row = $smcFunc['db_fetch_assoc']($query)) {
	do_event_group_poll($row['id_event_group'], $row['short_desc'], $row['id_poll'], $row['reg_topic']);
}
$smcFunc['db_free_result']($query);

function do_event_group_poll($event_group, $desc, $poll, $topic) {
	global $lm2_db_prefix, $smcFunc, $boardurl;

	$membergroups = array("dummy"=>-1);
	$poll_choices = array(-1=>"dummy");

	$query = $smcFunc['db_query'](null, "
		SELECT DISTINCT id_championship, champ_group_membergroup, champ_group_poll_choice, champ_group_type
		FROM {$lm2_db_prefix}event_groups
		JOIN {$lm2_db_prefix}championships ON event_group = id_event_group
		JOIN {$lm2_db_prefix}champ_groups ON id_championship = champ_group_champ
		JOIN {db_prefix}poll_choices ON id_poll = $poll AND champ_group_poll_choice = id_choice
		WHERE champ_group_type <> 'L' AND id_event_group = {int:event_group}
		", array('event_group'=>$event_group));
	while ($row = $smcFunc['db_fetch_assoc']($query)) {
		if (!is_null($mg = $row["champ_group_membergroup"]) && !is_null($pc = $row["champ_group_poll_choice"])) {
			$poll_choices[$pc] = "{$row['champ_group_type']}-{$row['id_championship']}";
			$membergroups[$poll_choices[$pc]] = $mg;
		}
	}
	$smcFunc['db_free_result']($query);

	if (count($membergroups) > 1) {
		echo "<TR STYLE='background: #ffff00' id='$event_group'><TD ALIGN='RIGHT' COLSPAN='99'><B>$desc</B> [poll ID $poll]</TD></TR>\n";

		$groups = array(array('id_group'=>-1, 'groupName'=>''));
		$query = $smcFunc['db_query'](null, "
			SELECT id_group, group_name AS groupName
			FROM {db_prefix}membergroups
			WHERE id_group IN (" . implode(',', $membergroups) . ")
			ORDER BY group_name
			", array());
		while ($row = $smcFunc['db_fetch_assoc']($query)) {
			array_push($groups, $row);
		}
		$smcFunc['db_free_result']($query);

		$query = $smcFunc['db_query'](null, "
			SELECT COUNT(*) AS members, g.id_group AS id_group
			FROM {db_prefix}members AS m
			, {db_prefix}membergroups AS g
			WHERE (CONCAT(',', additional_groups, ',') REGEXP CONCAT(',',g.id_group,',')
			AND g.id_group IN (" . implode(",", $membergroups) . "))
			GROUP BY g.id_group
			", array());
		$group_counts = array();
		while ($row = $smcFunc['db_fetch_assoc']($query)) {
			$group_counts[$row['id_group']] = $row['members'];
		}
		$smcFunc['db_free_result']($query);

		$query = $smcFunc['db_query'](null, "SELECT m.id_member AS member, l.id_choice AS choice
			, member_name AS memberName, posts, real_name AS realName, label
			, group_name AS groupName, g.id_group AS id_group
			, (SELECT COUNT(DISTINCT event) FROM {$lm2_db_prefix}event_entries e WHERE m.id_member = member) AS events
			, gplrank
			FROM {db_prefix}members AS m
			LEFT JOIN {$lm2_db_prefix}drivers ON driver_member = id_member
			LEFT JOIN {db_prefix}log_polls AS l ON l.id_poll = {int:poll}
				AND l.id_member = m.id_member
				AND l.id_choice IN (" . implode(",", array_keys($poll_choices)) . ")
			LEFT JOIN {db_prefix}poll_choices AS c ON c.id_poll = $poll AND l.id_choice = c.id_choice
			LEFT JOIN {db_prefix}membergroups g
				ON (CONCAT(',', additional_groups, ',') REGEXP CONCAT(',',g.id_group,',')
				AND g.id_group IN (" . implode(",", $membergroups) . "))
			WHERE c.id_choice IS NOT NULL OR g.id_group IS NOT NULL
			ORDER BY id_group, c.id_choice, events DESC, real_name
			", array('poll'=>$poll));
		$current_group = null;
		while ($row = $smcFunc['db_fetch_assoc']($query)) {
			$group = $row['id_group'];
	
			if ($current_group != $group) {
				$current_group = $group;
				echo "<TR STYLE=\"background: #ffffaa\"><TD ALIGN=\"RIGHT\" COLSPAN=\"99\"><B>Group $group ("
					. hackGroupName($row['groupName']) . "): {$group_counts[$group]} members</B></TD></TR>\n";
				$row_count = 0;
			}

			$settings = "<A HREF='$boardurl/index.php?action=profile;u={$row['member']};sa=account'>Groups</A>";
			if ($row['id_group'] == $membergroups[$poll_choices[$row['choice']]]) {
				$settings = "<SMALL><SMALL>$settings</SMALL></SMALL>";
			}

			if (!stristr($row['realName'], $row['memberName'])) {
				$row['realName'] = "<SPAN TITLE='{$row['memberName']}'>{$row['realName']}</SPAN>";
			}

			if (($posts = $row['posts']) > 0) {
				$posts = "<A HREF='$boardurl/index.php?action=profile;u={$row['member']};sa=showPosts'>$posts</A>";
			}

			$row['label'] = hackLabel($row['label']);

			$all_choices = list_all_choices($row['member'], $poll);
			$all_messages = list_all_messages($row['member'], $topic);
			$all_teams = list_members_teams($row['member']);
			echo "<TR" . ($row_count++ & 1 ? " STYLE=\"background: #eeeeee\"" : "") . ">"
				. "<TD onClick='if (this.title) alert(this.title)' TITLE=\"$all_messages\"><NOBR><A HREF='$boardurl/index.php?action=profile;u={$row['member']};sa=racing_history#aliases'>{$row['realName']}</A></NOBR>"
				."<SMALL> {$row['gplrank']}<SMALL> $all_teams</SMALL></SMALL></TD>"
				. "<TD ALIGN='RIGHT'><SMALL><SMALL>$posts</SMALL></SMALL></TD>"
				. "<TD>$settings</TD>"
				. "<TD>{$row['events']}</TD>"
				. "<TD onClick='alert(this.title)' TITLE=\"$all_choices\"><NOBR>{$row['label']}</NOBR></TD>"
				. "<TD>" . group_selector($row['member'], $groups, $row['id_group'], $event_group) . "</TD>"
				. "<TD><SMALL>" . list_members_groups($row['member'], $membergroups) . "</SMALL></TD>"
				. "</TR>\n";
		}
	$smcFunc['db_free_result']($query);
	}
}

function group_selector($member, $groups, $selected, $event_group) {
	if (!$selected) {
		$selected = -1;
	}

	$select = "<SELECT onChange='changeGroup(this, $member, $selected, $event_group)'>";

	foreach ($groups as $row) {
		$sel = ($row['id_group'] == $selected) ? ' SELECTED' : '';
		$select .= "<OPTION VALUE='{$row['id_group']}'$sel>" . hackGroupName($row['groupName']) . "</OPTION>";
	}

	$select .= "</SELECT>";

	return $select;
}

function list_all_messages($member, $topic) {
	global $smcFunc;
	$query = $smcFunc['db_query'](null, "SELECT body
		FROM {db_prefix}messages
		WHERE id_topic = {int:topic}
 		AND id_member = {int:member}
		ORDER BY poster_time"
		, array('topic'=>$topic, 'member'=>$member));
	$sep = "";
	$list = "";
	while ($row = $smcFunc['db_fetch_assoc']($query)) {
		$body = $row['body'];
		$body = preg_replace ('/\[quote.*?\[\/quote\]/', '', $body);
		$body = str_replace('<br />', '&#10;', $body);
		$body = str_replace('"', '&quot;', $body);
		$list = $list . $sep . $body;
		$sep = "&#10;-----&#10;";
	}
	$smcFunc['db_free_result']($query);

	return $list;
}

function list_all_choices($member, $poll) {
	global $smcFunc;
	$query = $smcFunc['db_query'](null, "
		SELECT c.id_choice AS id, label
		FROM {db_prefix}log_polls AS l
		, {db_prefix}poll_choices AS c
		WHERE l.id_member = {int:member} AND l.id_poll = {int:poll} AND c.id_poll = {int:poll} AND l.id_choice = c.id_choice
		ORDER BY c.id_choice"
		, array('poll'=>$poll, 'member'=>$member));
	$sep = "";
	$list = "";
	while ($row = $smcFunc['db_fetch_assoc']($query)) {
		$row['label'] = hackLabel($row['label']);
		$list = "$list$sep{$row['label']} [{$row['id']}]";
		$sep = "\n";
	}
	$smcFunc['db_free_result']($query);

	return $list;
}

function hackLabel($label) {
	return preg_replace('/ \[.*\]$/', '', $label);
}

// List the other groups the member is in.
function list_members_groups($member, $membergroups) {
	global $lm2_db_prefix, $smcFunc;

	$query = $smcFunc['db_query'](null, "
		SELECT DISTINCT group_name AS groupName
		FROM {db_prefix}membergroups AS g
		JOIN {db_prefix}members AS m ON CONCAT(',', m.id_group, ',', additional_groups, ',') REGEXP CONCAT(',',g.id_group,',')
		LEFT JOIN {$lm2_db_prefix}champ_groups ON champ_group_membergroup = g.id_group
		WHERE id_member = {int:member}
		AND g.id_group NOT IN (" . implode(",", $membergroups) . ") AND g.id_group <> 1
		AND (champ_group_membergroup IS NOT NULL OR g.id_group = 42) 
		ORDER BY group_name
		", array('member'=>$member));
	$sep = "";
	$list = "";
	while ($row = $smcFunc['db_fetch_assoc']($query)) {
		$list = $list . $sep . hackGroupName($row['groupName']);
		$sep = ", ";
	}
	$smcFunc['db_free_result']($query);

	return $list;
}

function hackGroupName($name) {
	return preg_replace('/ #$/', '', $name);
}

function list_members_teams($member) {
	global $lm2_db_prefix, $smcFunc;

	$query = $smcFunc['db_query'](null, "SELECT DISTINCT team_name
		FROM {$lm2_db_prefix}team_drivers
		JOIN {$lm2_db_prefix}teams ON team = id_team
		WHERE date_to IS NULL AND invitation_date IS NULL
		AND member = {int:member}
		ORDER BY team_name
		", array('member'=>$member));
	$sep = "";
	$list = "";
	while ($row = $smcFunc['db_fetch_assoc']($query)) {
		$list = $list . $sep . $row['team_name'];
		$sep = ", ";
	}
	$smcFunc['db_free_result']($query);

	return $list;
}

?>
</TABLE>
<FORM>
