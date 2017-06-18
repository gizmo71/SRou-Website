<P><B><A HREF="<?php echo $boardurl; ?>/index.php?topic=431.0">Help On Using This Page</A></B></P>
<SPAN ID="pleaseWait" STYLE="display: none">
<H1>Please wait...</H1>
</SPAN><SPAN ID="mainBody">
<SCRIPT>
function submitform(form) {
    pleaseWait.style.display = 'block';
    mainBody.style.display = 'none';
    form.submit();
}
</SCRIPT>

<FORM METHOD="POST">
  <INPUT TYPE="HIDDEN" NAME="new_name" />
  <INPUT TYPE="BUTTON" VALUE="Create new team" onClick="if (name = prompt('Enter team name')) { form.new_name.value = name; submitform(form); }" />
  <B>Warning!</B> Creating and joining a new team may result in you leaving any existing team.
</FORM>

<?php
($own_id = $context['user']['id']) || die("only members can access this page");
$id_team = $_REQUEST['id_team'];
$id_team == "" || is_null($id_team) || is_numeric($id_team) || die("team not null, empty or numeric; hacker?");

require_once("$sourcedir/Subs-Post.php");

$event_group = $_REQUEST['event_group'];
$event_group == "" || $event_group == "NULL" || is_null($event_group) || is_numeric($event_group) || die("event_group '$event_group' not null, empty or numeric; hacker!");

lm2_query("SET @now_then = " . php2timestamp(time()), __FILE__, __LINE__);
if (($new_name = sqlString(get_request_param('new_name'))) != "NULL") {
	echo "<P>New name $new_name</P>\n";

	// Sanity check
	$query = $smcFunc['db_query'](NULL, "SELECT * FROM {lm2_prefix}teams WHERE team_name = {string:new_name} AND NOT team_is_fake", array('new_name'=>$new_name));
	$row = $smcFunc['db_fetch_assoc']($query);
	$smcFunc['db_free_result']($query);

	if ($row) {
		echo "<P STYLE='color: red'><B>Already got a team of that name - please contact one of its members to get invited.</B></P>\n";
	} else {
		$smcFunc['db_query'](NULL, "INSERT INTO {lm2_prefix}teams (team_name, created_by) VALUES ({string:new_name}, {int:own_id})",
			array('new_name'=>$new_name, 'own_id'=>$own_id));
		$id_team = $smcFunc['db_insert_id']('{lm2_prefix}teams', 'id_team');
		$smcFunc['db_query'](NULL, "
			INSERT INTO {lm2_prefix}team_drivers
			(member, team, invitation_date)
			VALUES ({int:own_id}, {int:id_team}, @now_then)
			",
			array('own_id'=>$own_id, 'id_team'=>$id_team));
	}
} else if (($team_name = sqlString(get_request_param('team_name'))) != "NULL") {
	$url = sqlString(get_request_param('url'));
	lm2_query("
		UPDATE ${lm2_db_prefix}teams
		SET team_name = $team_name
		, url = $url
		WHERE id_team = $id_team
		", __FILE__, __LINE__);
} else if (!is_null($invite = $_REQUEST['invite'])) {
	is_numeric($invite) || die("invite not numeric; hacker?");
	lm2_query("
		INSERT INTO ${lm2_db_prefix}team_drivers
		(member, team, event_group, invitation_date, audit_who, audit_when, audit_what)
		VALUES ($invite, $id_team, $event_group, @now_then, $own_id, @now_then, 'invite')
		", __FILE__, __LINE__);

	$logs = sendpm(array('to'=>array($invite), 'bcc'=>array()),
		"Team Invitation",
		"I would like to invite you to join our team."
		. "\n\nTo accept or reject the invitation, [url=http://www.simracing.org.uk/lm2/index.php?action=teams]click here[/url]."
		. "\n\n[i](This message was automatically generated by League Manager 2 interim)[/i]",
		false, // Store in outbox.
		null); // From - null means the person running the script.
} else if (!is_null($what = $_REQUEST['what'])) {
	$id_team_driver = $_REQUEST['id_team_driver'];
	is_numeric($id_team_driver) || die("team_driver not numeric; hacker?");
	if ($what == 'reject') {
		lm2_query("DELETE FROM ${lm2_db_prefix}team_drivers
			WHERE id_team_driver = $id_team_driver
			", __FILE__, __LINE__);
	} else if ($what == 'accept') {
		lm2_query("UPDATE ${lm2_db_prefix}team_drivers
			SET date_to = @now_then
			, audit_who = $own_id
			, audit_when = @now_then
			, audit_what = " . sqlString($what, false) . "
			WHERE id_team_driver <> $id_team_driver
			AND member = $own_id
			AND IFNULL(event_group, 0) = IFNULL($event_group, 0)
			AND date_to IS NULL and date_from IS NOT NULL
			", __FILE__, __LINE__);
		lm2_query("UPDATE ${lm2_db_prefix}team_drivers
			SET event_group = $event_group
			, invitation_date = NULL
			, date_from = @now_then
			, audit_who = $own_id
			, audit_when = @now_then
			, audit_what = " . sqlString($what, false) . "
			WHERE id_team_driver = $id_team_driver
			", __FILE__, __LINE__);
	} else if ($what == 'leave') {
		lm2_query("UPDATE ${lm2_db_prefix}team_drivers
			SET date_to = @now_then
			, audit_who = $own_id
			, audit_when = @now_then
			, audit_what = " . sqlString($what, false) . "
			WHERE id_team_driver = $id_team_driver
			", __FILE__, __LINE__);
	} else if ($what == 'update') {
		($member_id = $_REQUEST['id_member']) || die("expecting id_member");
		lm2_query("UPDATE ${lm2_db_prefix}team_drivers
			SET date_to = @now_then
			, audit_who = $own_id
			, audit_when = @now_then
			, audit_what = " . sqlString($what, false) . "
			WHERE (IFNULL(event_group, 0) = IFNULL($event_group, 0) OR id_team_driver = $id_team_driver)
			AND member = $member_id
			AND date_to IS NULL and date_from IS NOT NULL
			", __FILE__, __LINE__);
		lm2_query("INSERT INTO ${lm2_db_prefix}team_drivers
			(member, team, event_group, date_from, audit_who, audit_when, audit_what)
			VALUES ($member_id, $id_team, $event_group, @now_then, $own_id, @now_then, " . sqlString($what, false) . ")
			", __FILE__, __LINE__);
	} else {
		die("unknown team_driver action: $what");
	}
}

// Tidy up...
for ($limit = 100; $limit-- > 0; ) {
	$query = lm2_query("
		SELECT td1.id_team_driver AS id1
		, td2.id_team_driver AS id2
		, td2.date_to AS date_to
		FROM {$lm2_db_prefix}team_drivers AS td1
		JOIN {$lm2_db_prefix}team_drivers AS td2
		WHERE td1.member = td2.member
		AND td1.team = td2.team
		AND IFNULL(td1.event_group , -1) = IFNULL(td2.event_group , -1)
		AND td1.date_to = td2.date_from
		AND td1.date_from IS NOT NULL
		AND td2.date_from IS NOT NULL
		LIMIT 1
		", __FILE__, __LINE__);
	if (!($row = $smcFunc['db_fetch_assoc']($query))) {
		break;
	}
	lm2_query("
		UPDATE {$lm2_db_prefix}team_drivers
		SET date_to = " . (is_null($row['date_to']) ? "NULL" : php2timestamp(timestamp2php($row['date_to']))) . "
		, audit_what = CONCAT(audit_what, '/tidy')
		WHERE id_team_driver = {$row['id1']}
		", __FILE__, __LINE__);
	lm2_query("
		DELETE FROM {$lm2_db_prefix}team_drivers
		WHERE id_team_driver = {$row['id2']}
		", __FILE__, __LINE__);
}

$query = lm2_query("
	SELECT DISTINCT id_team, team_name, url
	FROM ${lm2_db_prefix}teams, ${lm2_db_prefix}team_drivers
	WHERE member = $own_id
	AND id_team = team
	AND date_to IS NULL
	AND NOT team_is_fake
	", __FILE__, __LINE__);
while ($row = $smcFunc['db_fetch_assoc']($query)) {
	$id_team = $row['id_team'];
	$team_name = htmlentities($row['team_name'], ENT_QUOTES);
	$url = htmlentities($row['url'], ENT_QUOTES);

	echo <<<EOT
<HR />
<TABLE STYLE="background: #DDDDDD">
<FORM METHOD="POST">
<INPUT TYPE="HIDDEN" NAME="id_team" VALUE="$id_team" />
<TR>
  <TD>Name:</TD> <TD><INPUT MAXLENGTH="30" SIZE="30" NAME="team_name" VALUE="$team_name" /></TD>
  <TD ALIGN="RIGHT"><INPUT TYPE="BUTTON" VALUE="Update details" onClick="if (confirm('Are you sure?')) { submitform(form); }" /></TD>
</TR>
<TR><TD>URL:</TD> <TD COLSPAN="2"><INPUT MAXLENGTH="150" SIZE="80" NAME="url" VALUE="$url" />
  <BR/><SMALL>Please use a full-formed URL, eg.</SMALL> <TT>http://www.myteam.org.uk/</TT></TD></TR>
</FORM>
</TABLE>
<P><I>Don't change the default &quot;(all series)&quot; membership unless you have to to avoid a clash with joint membership of an different team.</I></P>
EOT;
	show_entries($id_team);
	show_invitation($id_team);
}
$smcFunc['db_free_result']($query);

function show_entries($team) {
	global $lm2_db_prefix, $db_prefix, $smcFunc, $own_id;

	$query = lm2_query("SELECT id_team_driver, member, real_name AS realName, event_group, date_from, invitation_date"
		. " FROM ${lm2_db_prefix}team_drivers, ${db_prefix}members"
		. " WHERE team = $team"
		. " AND member = id_member"
		. " AND date_to IS NULL"
		. " ORDER BY member != $own_id, real_name",
		__FILE__, __LINE__);
	while ($row = $smcFunc['db_fetch_assoc']($query)) {
		$member = $row['member'];
		$name = $row['realName']; // Stored encoded.
		$id_team_driver = $row['id_team_driver'];
		$event_group = $row['event_group'];
		$date_from = timestamp2php($row['date_from']);
		$invitation_date = timestamp2php($row['invitation_date']);

		echo "<FORM METHOD=\"POST\">
			<INPUT TYPE='HIDDEN' NAME='id_team' VALUE='$team' />
			<INPUT TYPE='HIDDEN' NAME='id_member' VALUE='$member' />
			<INPUT TYPE='HIDDEN' NAME='id_team_driver' VALUE='$id_team_driver' />
			<INPUT TYPE='HIDDEN' NAME='what' VALUE='' />";
		$colsep = "&nbsp;&nbsp;"; // Or use the global one?

		if ($own_id == $member) {
			echo is_null($invitation_date) ? "Your membership: " : "You have been invited to join this team: ";
		} else {
			echo "&nbsp;&nbsp;$name";
		}

		echo $colsep;
		show_groups($event_group);
		echo $colsep;

		if (is_null($date_from) && !is_null($invitation_date)) {
			if ($own_id == $member) {
				show_button("Accept invitation", 'accept');
			}
			echo $colsep;
			show_button($own_id == $member ? "Reject approach" : "Withdraw invitation", 'reject');
		} else if (!is_null($date_from)) {
			show_button("Update", 'update');
			echo $colsep;
			show_button($own_id == $member ? "Leave" : "Fire", 'leave');
		}

		echo "</FORM>\n";
	}
	$smcFunc['db_free_result']($query);
}

function show_invitation($team) {
	global $smcFunc, $lm2_db_prefix, $db_prefix, $own_id;

	echo "<FORM METHOD=\"POST\">\n"
		. "<INPUT TYPE=\"HIDDEN\" NAME=\"id_team\" VALUE=\"$team\">\n";
	show_groups(null);
	echo "<SELECT NAME=\"invite\" VALUE=\"$id_team_driver\">\n";

	$query = lm2_query("SELECT id_member, member_name AS memberName, real_name AS realName, SUM(IF(invitation_date IS NULL, 0, 1)) AS invitations"
		. " FROM ${db_prefix}members"
		. " LEFT JOIN ${lm2_db_prefix}team_drivers ON team = $team AND member = id_member"
		. " GROUP BY id_member"
		. " HAVING invitations = 0 OR id_member = $own_id"
		. " ORDER BY real_name",
		__FILE__, __LINE__);
	while ($row = $smcFunc['db_fetch_assoc']($query)) {
		$id = $row['id_member'];
		$user = $row['memberName'];
		$name = $row['realName'];
		echo "<OPTION VALUE=\"$id\"" . ($id == $own_id ? " SELECTED" : "") . ">$name ($user)</OPTION>\n";
	}
	echo "</SELECT>
		<INPUT TYPE='BUTTON' VALUE='Send invitation' onClick=\"if (confirm('Are you sure?')) { submitform(form); }\" />
		</FORM>\n";
	$smcFunc['db_free_result']($query);
}

function show_button($text, $what) {
	echo " <INPUT TYPE='BUTTON' VALUE=\"$text\" onClick=\"if (confirm('Are you sure?')) { form.what.value = '$what'; submitform(form); }\" />";
}

function show_groups($group, $parent = null, $prefix = '') {
	global $lm2_db_prefix, $smcFunc;

	if (is_null($parent)) {
		echo "<SELECT NAME=\"event_group\">\n"
			. "  <OPTION STYLE=\"background: #ffffa0\" VALUE=\"NULL\">(all series)</OPTION>\n";
	}
	$query = lm2_query("SELECT id_event_group AS id, CONCAT(short_desc,' (',long_desc,')') AS name, parent"
		. " FROM ${lm2_db_prefix}event_groups"
		. " WHERE parent " . (is_null($parent) ? "IS NULL OR id_event_group = parent" : "= " . $parent)
		. " ORDER BY name", __FILE__, __LINE__);
	while ($row = $smcFunc['db_fetch_assoc']($query)) {
		$sel = ($id = $row['id']) == $group ? " SELECTED" : "";
		echo "  <OPTION VALUE=\"$id\"$sel>$prefix" . htmlentities($row['name'], ENT_QUOTES) . "</OPTION>\n";
		if ($row['parent'] != $id) {
			show_groups($group, $id, "$prefix&nbsp;&nbsp;");
		}
	}
	$smcFunc['db_free_result']($query);
	if (is_null($parent)) {
		echo "</SELECT>\n";
	}
}
?>

</SPAN>
