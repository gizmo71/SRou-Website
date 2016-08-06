<?php
require_once('../smf/Sources/Subs-Post.php');

$event = $_REQUEST['event'];
$incident = $_REQUEST['incident'];
$description = get_request_param('description');
$event_status = $_REQUEST['event_status'];
$show_all = $_REQUEST['show_all']; // Do we want to show all events, or only those not yet published/official?

function nullIfEmpty($v) {
	return $v == '' ? null : $v;
}

if (!is_null($event_status)) {
	db_query("
		UPDATE {$lm2_db_prefix}events
		SET event_status = " . sqlString($event_status) . "
		WHERE id_event = $event
		", __FILE__, __LINE__);
	if ($event_status != 'U' && $event_status != 'H') {
		db_query("
			UPDATE {$lm2_db_prefix}events
			SET report_published = " . php2timestamp(time()) . "
			WHERE id_event = $event AND report_published IS NULL
			", __FILE__, __LINE__);
		inform_bad_boys($event);
		echo "<P>Don't forget to generate the standings!</P>\n";
	}
	generate_list($event);
} else if (!is_null($description)) {
	$delete = $_REQUEST['delete'] == '1';
	if ($delete) {
		db_query("
			DELETE FROM {$lm2_db_prefix}incidents
			WHERE event = $event AND id_incident = $incident
			", __FILE__, __LINE__);
	} else {
		$replay_time = parse_incident_time(nullIfNull($_REQUEST['replayTime']));
		$is_comment = $_REQUEST['is_comment'] == '1' ? '1' : '0';
		$description = sqlString($description);
		if ($incident == "ADD") {
			db_query("
				INSERT INTO {$lm2_db_prefix}incidents
				(event, replay_time, description, is_comment)
				VALUES ($event, $replay_time, $description, $is_comment)
				", __FILE__, __LINE__);
			$added_incident = $incident = db_insert_id();
		} else {
			db_query("
				UPDATE {$lm2_db_prefix}incidents
				SET replay_time = $replay_time, description = $description, is_comment = $is_comment
				WHERE event = $event AND id_incident = $incident
				", __FILE__, __LINE__);
		}

		$rownum = 0;
		while ($penalty = $_REQUEST["penalty{$rownum}"]) {
			$event_entry = $_REQUEST["penalty{$rownum}event_entry"];
			$description = $_REQUEST["penalty{$rownum}description"];
			if (!is_null($description)) $description = stripslashes($description);
			$description = sqlString($description);
			$type = sqlString(stripslashes($_REQUEST["penalty{$rownum}type"]));
			$victim_report = sqlString(stripslashes($_REQUEST["penalty{$rownum}victim_report"]));
			$excluded = sqlString(stripslashes($_REQUEST["penalty{$rownum}excluded"]));
			if (($positions_lost = $_REQUEST["penalty{$rownum}positions_lost"]) == '') $positions_lost = null;
			$positions_lost = nullIfNull($positions_lost);
			$points_lost = nullIfNull(nullIfEmpty($_REQUEST["penalty{$rownum}points_lost"]));
			$champ_type = sqlString(nullIfEmpty($_REQUEST["penalty{$rownum}champ_type"]));
			if (($seconds_added = $_REQUEST["penalty{$rownum}seconds_added"]) == '') $seconds_added = null;
			$seconds_added = nullIfNull($seconds_added);
			$sql = null;
			if ($penalty == 'ADD' && $event_entry != '') {
				$sql = "INSERT INTO {$lm2_db_prefix}penalties
					(event_entry, description, seconds_added, positions_lost, points_lost, penalty_champ_type, incident, penalty_type, victim_report, excluded)
					VALUES ($event_entry, $description, $seconds_added, $positions_lost, $points_lost, $champ_type, $incident, $type, $victim_report, $excluded)";
			} else if ($penalty != 'ADD' && $event_entry != '') {
				$sql = "UPDATE {$lm2_db_prefix}penalties
					SET event_entry = $event_entry
					, description = $description
					, seconds_added = $seconds_added
					, positions_lost = $positions_lost
					, points_lost = $points_lost
					, penalty_champ_type = $champ_type
					, penalty_type = $type
					, victim_report = $victim_report
					, excluded = $excluded
					WHERE id_penalty = $penalty";
			} else if ($penalty != 'ADD' && $event_entry == '') {
				$sql = "DELETE FROM {$lm2_db_prefix}penalties WHERE id_penalty = $penalty";
			} // ... else ignore it.
			if (!is_null($sql)) {
				//echo "<P>$sql</P>";
				db_query($sql, __FILE__, __LINE__);
			}
			++$rownum;
		}
	}
	generate_list($event);
} else if (!is_null($incident)) {
	if ($incident == 'ADD') {
		$replay_time = 0;
		$is_comment = false;
		$description = '';
	} else {
		$query = db_query("
			SELECT replay_time, is_comment, description, sim
			FROM {$lm2_db_prefix}incidents
			JOIN {$lm2_db_prefix}events ON event = id_event
			WHERE id_incident = $incident
			" , __FILE__, __LINE__);
		($row = mysql_fetch_assoc($query)) || die("can't load incident $incident");
		$replay_time = $row['replay_time'];
		$is_comment = $row['is_comment'] == 1;
		$description = $row['description'];
		$sim = $row['sim'];
		mysql_fetch_assoc($query) && die("incident $incident found more than once!");
		mysql_free_result($query);
	}
?>
<FORM METHOD="POST" ACTION="#<?php echo $incident; ?>">
<INPUT TYPE="HIDDEN" NAME="event" VALUE="<?php echo $event; ?>" />
<INPUT TYPE="HIDDEN" NAME="incident" VALUE="<?php echo $incident; ?>" />
<TABLE>
<?php if ($incident != 'ADD') { ?>
	<TR><TD>Delete?</TD><TD><INPUT TYPE=CHECKBOX NAME=delete VALUE=1 /></TD></TR>
<?php } ?>
	<TR><TD>Replay time:</TD><TD><INPUT TYPE="TEXT" NAME="replayTime" VALUE="<?php echo lm2_format_incident_time($replay_time, $sim); ?>" />
		(enter as seconds, or as a time in a format like 1h02m03s or 1:02:03; do <B>not</B> use fractions of seconds!)</TD></TR>
	<TR><TD>Is a moderator's comment?</TD><TD><INPUT TYPE=CHECKBOX NAME="is_comment" <?php echo $is_comment ? "CHECKED" : ""; ?> VALUE="1" />
		(hides the replay time, and inhibits the 'Racing Incident' text normally shown if no penalties are given)</TD></TR>
    <TR><TD COLSPAN=2>Description of incident:<BR />
        <TEXTAREA NAME="description" ROWS=10 COLS=70 /><?php echo htmlentities($description, ENT_QUOTES); ?></TEXTAREA>
</TABLE>
<TABLE>
<TR>
  <TH>Driver</TH>
  <TH>Description</TH>
  <TH>Seconds</TH>
  <TH>Places</TH>
  <TH>Points</TH>
  <TH>Championship</TH>
  <TH>Type</TH>
  <TH>Exclude?</TH>
  <TH>Victim report?</TH>
</TR>
<?php
	$rows = 0;
	if ($incident != 'ADD') {
		$query = db_query("
			SELECT *
			FROM {$lm2_db_prefix}penalties
			WHERE incident = $incident
			", __FILE__, __LINE__);
		while ($row = mysql_fetch_assoc($query)) {
			show_penalty($rows++, $row['id_penalty'], $row['event_entry'], $row['description'], $row['seconds_added'], $row['positions_lost'], $row['points_lost'], $row['penalty_champ_type'], $row['penalty_type'], $row['victim_report'], $row['excluded']);
		}
		mysql_free_result($query);
	}
	do {
		show_penalty($rows++, null, null, '', null, null, null, null, null, null, null);
	} while ($rows < 5);
?>
</TABLE>
<INPUT TYPE="SUBMIT" VALUE="Submit" />
</FORM>
<?php
} else if (!is_null($event)) {
	generate_list($event);
} else {
	if (!is_null($_REQUEST['mod_event'])) {
		db_query("
			UPDATE {$lm2_db_prefix}events
			SET event_moderator = $ID_MEMBER, moderation_start = " . php2timestamp(time()) . "
			WHERE id_event = {$_REQUEST['mod_event']} AND event_moderator IS NULL
			" , __FILE__, __LINE__);
	} else if (!is_null($_REQUEST['unmod_event'])) {
		db_query("
			UPDATE {$lm2_db_prefix}events SET event_moderator = NULL, moderation_start = NULL
			WHERE id_event = {$_REQUEST['unmod_event']} AND event_moderator = {$_REQUEST['who']}
			" , __FILE__, __LINE__);
	}
?>
<P>Choose event:
<?php
	printf('(<A HREF="index.php?action=court&show_all=%d">Show %s events</A>)', ($show_all ? 0 : 1), ($show_all ? "only unmoderatred" : "all"));
?>
<TABLE BORDER=1 CELLSPACING=0 CELLPADDING=3>
<TR>
	<TH>ID</TH>
	<TH>Date</TH>
	<TH>Group</TH>
	<TH>Type</TH>
	<TH>Circuit</TH>
	<TH>&nbsp;</TH>
	<TH>&nbsp;</TH>
	<TH>Reports</TH>
	<TH>Moderator</TH>
</TR>
<?php
	$query = db_query("
		SELECT DISTINCT id_event
		, $circuit_html_clause AS circuit_html
		, short_desc AS event_group
		, event_date
		, COUNT(DISTINCT id_incident) AS incidents
		, COUNT(DISTINCT id_report) AS reports
		, event_status
		, event_type
		, event_moderator, IFNULL(driver_name, CONCAT('#', event_moderator)) AS moderator_name
		, moderation_start
		, incident_topic
		, " . php2timestamp(time()) . " < event_date + INTERVAL mod(12 - weekday(event_date), 7) + 3 DAY AS too_soon
		, IF({$lm2_db_prefix}events.sim = 8, '#eeffee', '#ffffff') AS bgcol
		FROM ({$lm2_db_prefix}events
		JOIN {$lm2_db_prefix}event_groups ON event_group = id_event_group
		JOIN {$lm2_db_prefix}event_entries ON id_event = {$lm2_db_prefix}event_entries.event
		JOIN {$lm2_db_prefix}sim_circuits ON id_sim_circuit = sim_circuit
		JOIN {$lm2_db_prefix}circuits ON id_circuit = circuit
		JOIN {$lm2_db_prefix}circuit_locations ON id_circuit_location = circuit_location)
		LEFT JOIN {$lm2_db_prefix}incidents ON id_event = {$lm2_db_prefix}incidents.event
		LEFT JOIN {$lm2_db_prefix}reports ON id_event = report_event
		LEFT JOIN {$lm2_db_prefix}drivers ON driver_member = event_moderator
		WHERE NOT is_protected
		AND (event_date > " . php2timestamp(time()) . " - INTERVAL 2 YEAR OR event_status NOT IN ('O', 'H'))
		" . ($show_all ? "" : "AND event_status = 'U'") . "
		GROUP BY id_event
		ORDER BY IF(event_moderator = $ID_MEMBER, 0, 1), event_date DESC
		", __FILE__, __LINE__);
	$rows = 0;
	while ($row = mysql_fetch_assoc($query)) {
		$url = "index.php?action=court&event={$row['id_event']}";
		$prefix = "<A HREF=\"$url\">";
		$suffix = "</A>";
		if ($row['event_status'] != 'U') {
			$row['event_moderator'] = "&nbsp;";
		} else {
			if (is_null($row['event_moderator'])) {
				$row['event_moderator'] = "<A HREF='?action=court&mod_event={$row['id_event']}'>become moderator</A>";
				if ($row['too_soon']) {
					$row['event_moderator'] .= "<SMALL> (probably too soon!)</SMALL>";
				} elseif ($row['incidents'] == 0 && $row['reports'] == 0) {
					$row['event_moderator'] = "<B>{$row['event_moderator']}</B>";
				}
			} else {
				$row['event_moderator'] = "{$row['moderator_name']}"
					. " - <A HREF='?action=court&unmod_event={$row['id_event']}&who={$row['event_moderator']}'>remove moderator</A> {$row['moderation_start']}";
			}
		}
		if ($row['event_status'] == 'U') {
			$row['event_status'] = "<B>{$row['event_status']}</B>";
		} elseif ($row['event_status'] == 'P' && $row['event_type'] != 'F'
		           || $row['event_status'] == 'O' && $row['event_type'] == 'F') {
			$row['event_status'] = "<SPAN STYLE='background: #ff0000'>{$row['event_status']}</SPAN>";
		}
		if ($row['incident_topic']) {
			$row['reports'] = "{$row['reports']} report(s)";
			$row['reports'] = "<A HREF='$boardurl/index.php?topic={$row['incident_topic']}.0'>{$row['reports']}</A>";
		}
		$onClick = " onClick=\"location.href='$url'\"";
	    echo "<TR STYLE='background: " . ($row['too_soon'] ? '#dddddd' : $row['bgcol']) . "'>"
	    	. "<TD ALIGN=RIGHT>$prefix{$row['id_event']}$suffix</TD>"
	        . "<TD$onClick>". format_timestamp(timestamp2php($row['event_date']), true) . "</TD>"
	        . "<TD$onClick>" . htmlentities($row['event_group'], ENT_QUOTES) . "</TD>"
	        . "<TD$onClick>{$row['event_type']}</TD>"
	        . "<TD$onClick>" . htmlentities($row['circuit_html'], ENT_QUOTES) . "</TD>"
	        . "<TD$onClick ALIGN=RIGHT>" . htmlentities($row['incidents']) . "</TD>"
	        . "<TD>{$row['event_status']}</TD>"
	        . "<TD>{$row['reports']}</TD>"
	        . "<TD>{$row['event_moderator']}</TD>"
	        . "</TR>\n";
	    ++$rows;
	}
	mysql_free_result($query);
	if ($rows > 0) {
?>
</TABLE>
<?php
	}
}

function generate_list($event) {
	global $lm2_db_prefix, $circuit_html_clause;
?>
<FORM METHOD="POST">
<?php
	$query = db_query("
		SELECT $circuit_html_clause AS circuit_html
		, short_desc AS event_group
		, event_date
		, event_status
		, event_type
		FROM {$lm2_db_prefix}events
		JOIN {$lm2_db_prefix}event_groups ON event_group = id_event_group
		JOIN {$lm2_db_prefix}sim_circuits ON id_sim_circuit = sim_circuit
		JOIN {$lm2_db_prefix}circuits ON id_circuit = circuit
		JOIN {$lm2_db_prefix}circuit_locations ON id_circuit_location = circuit_location
		WHERE id_event = $event
		ORDER BY event_date DESC
		", __FILE__, __LINE__);
	$row = mysql_fetch_assoc($query);
	$event_status = $row['event_status'];
    echo '<P><B>Incidents for ' . htmlentities($row['circuit_html']) . ' ' . $row['event_date'] . ' '
        . htmlentities($row['event_group']) . "</B>\n";
    mysql_fetch_assoc($query) && die("multiple events matching $event!");
	mysql_free_result($query);
?>
<INPUT TYPE=HIDDEN NAME="event" VALUE="<?php echo $event; ?>" />
<BR/><INPUT TYPE=RADIO NAME="event_status" VALUE="U"<?php echo $event_status == 'U' ? ' CHECKED' : '' ?> /> Unofficial
<?php if ($row['event_type'] == 'F') { ?>
<BR/><INPUT TYPE=RADIO NAME="event_status" VALUE="P"<?php echo $event_status == 'P' ? ' CHECKED' : '' ?> /> Published but unofficial (for fun races)
<?php } else { ?>
<BR/><INPUT TYPE=RADIO NAME="event_status" VALUE="O"<?php echo $event_status == 'O' ? ' CHECKED' : '' ?> /> Official (for champ/non-champ events)
<?php } ?>
<BR/><INPUT TYPE=RADIO NAME="event_status" VALUE="H"<?php echo $event_status == 'H' ? ' CHECKED' : '' ?> /> Historic (for migrated UKGPL data)
<BR/><INPUT TYPE=SUBMIT VALUE="Update Status" />
</FORM>
<FORM METHOD="POST">
<INPUT TYPE=HIDDEN NAME="event" VALUE="<?php echo $event; ?>" />
<INPUT TYPE=HIDDEN NAME="incident" VALUE="ADD" />
<INPUT TYPE=SUBMIT VALUE="Add Incident" />
</FORM>
<?php

	$query = db_query("
		SELECT id_incident, replay_time, description, is_comment, sim
		FROM {$lm2_db_prefix}incidents
		JOIN {$lm2_db_prefix}events ON id_event = event
		WHERE event = $event
		ORDER BY replay_time
		", __FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($query)) {
		$incident = $row['id_incident'];
		global $added_incident;
?>
<HR />
<FORM ID="<?php echo $added_incident == $incident ? 'ADD' : $incident; ?>" METHOD="POST">
<P>Time: <?php echo lm2_format_incident_time($row['replay_time'], $row['sim']); ?>;
   Is comment?: <?php echo $row['is_comment'] == 1 ? "Yes" : "No"; ?>;
<P><?php echo nl2br(htmlentities($row['description'], ENT_QUOTES)); ?></P>
<UL><?php lm2AddPenalties($incident, $row['is_comment']); ?></UL>
<INPUT TYPE=HIDDEN NAME="event" VALUE="<?php echo $event; ?>" />
<INPUT TYPE=HIDDEN NAME="incident" VALUE="<?php echo $incident; ?>" />
<INPUT TYPE="SUBMIT" VALUE="Edit or delete incident" />
</FORM>
<?php
	}
	mysql_free_result($query);
}

function show_penalty($rownum, $id_penalty, $event_entry, $description, $seconds_added, $positions_lost, $points_lost, $champ_type, $type, $victim_report, $excluded) {
	global $event;
	global $lm2_db_prefix;
	global $db_prefix;
	global $penalty_types;
?>
<TR>
<TD><INPUT TYPE=HIDDEN NAME="<?php echo "penalty$rownum"; ?>" VALUE="<?php echo $id_penalty ? $id_penalty : "ADD"; ?>" />
<SELECT NAME="<?php echo "penalty{$rownum}event_entry"; ?>">
<OPTION VALUE=""><?php if (!is_null($event_entry)) echo 'REMOVE'; ?></OPTION>
<?php
	$query = db_query("SELECT driver_name AS realName, id_event_entry"
		. " FROM {$lm2_db_prefix}event_entries, {$lm2_db_prefix}drivers"
		. " WHERE event = $event AND member = driver_member"
		. " ORDER BY realName",
		__FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($query)) {
		$id = $row['id_event_entry'];
		$sel = $event_entry == $id ? ' SELECTED' : '';
		echo "<OPTION VALUE=\"$id\"$sel>{$row['realName']}</OPTION>\n";
	}
	mysql_free_result($query);
?>
</SELECT></TD>
<TD><INPUT NAME="<?php echo "penalty{$rownum}description"; ?>" VALUE="<?php echo htmlentities($description, ENT_QUOTES); ?>" MAXLENGTH="75" SIZE="50" /></TD>
<TD><INPUT NAME="<?php echo "penalty{$rownum}seconds_added"; ?>" VALUE="<?php echo $seconds_added; ?>" MAXLENGTH="7" SIZE="8" /></TD>
<TD><INPUT NAME="<?php echo "penalty{$rownum}positions_lost"; ?>" VALUE="<?php echo $positions_lost; ?>" MAXLENGTH="2" SIZE="3" /></TD>
<TD><INPUT NAME="<?php echo "penalty{$rownum}points_lost"; ?>" VALUE="<?php echo $points_lost; ?>" MAXLENGTH="6" SIZE="6" /></TD>
<TD><SELECT NAME="<?php echo "penalty{$rownum}champ_type"; ?>"><?php
	global $lm2_champ_types;
	$champ_types = $lm2_champ_types;
	if (is_null($champ_type)) $champ_type = '';
	$champ_types[''] = '*All*';
	if (!array_key_exists($champ_type, $champ_types)) {
		$champ_types[$champ_type] = "[$champ_type]";
	}
	foreach ($champ_types AS $code=>$desc) {
		echo "<OPTION VALUE=\"$code\"" . ($code == $champ_type ? " SELECTED" : "") . ">$desc</OPTION>\n";
	}
?></SELECT></TD>
<TD><SELECT NAME="<?php echo "penalty{$rownum}type"; ?>">
<?php
	if (is_null($type)) $type = '';
	if (!array_key_exists($type, $penalty_types)) {
		$penalty_types[$type] = "[$type]";
	}
	foreach ($penalty_types AS $code=>$desc) {
		echo "<OPTION VALUE=\"$code\"" . ($code == $type ? " SELECTED" : "") . ">$desc</OPTION>\n";
	}
?></SELECT></TD>
<TD><SELECT NAME="<?php echo "penalty{$rownum}excluded"; ?>">
<?php
	$excluded_types = array('Y'=>'excluded', 'N'=>'');
	if (is_null($excluded)) $excluded = 'N';
	if (!array_key_exists($excluded, $excluded_types)) {
		$excluded_types[$excluded] = "[$excluded]";
	}
	foreach ($excluded_types AS $code=>$desc) {
		echo "<OPTION VALUE=\"$code\"" . ($code == $excluded ? " SELECTED" : "") . ">$desc</OPTION>\n";
	}
?></SELECT></TD>
<TD><SELECT NAME="<?php echo "penalty{$rownum}victim_report"; ?>">
<?php
	$victim_report_types = array(''=>'', 'Y'=>'victim report', 'N'=>'no victim report');
	if (is_null($victim_report)) $victim_report = '';
	if (!array_key_exists($victim_report, $victim_report_types)) {
		$victim_report_types[$victim_report] = "[$victim_report]";
	}
	foreach ($victim_report_types AS $code=>$desc) {
		echo "<OPTION VALUE=\"$code\"" . ($code == $victim_report ? " SELECTED" : "") . ">$desc</OPTION>\n";
	}
?></SELECT></TD>
</TR>
<?php
}

// Keep track of who's been a naughty boy and tell them.
function inform_bad_boys($event) {
	global $db_prefix, $lm2_db_prefix, $lm2_guest_member_id;

	$drivers = array();
	$query = db_query("
		SELECT id_event_entry, id_member
		FROM {$lm2_db_prefix}event_entries
		JOIN {$lm2_db_prefix}penalties ON event_entry = id_event_entry
		LEFT JOIN {$db_prefix}members ON member = id_member
		WHERE event = $event
		AND IFNULL(informed_of_report, 0) <> 1
	", __FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($query)) {
		if ($informed = ((is_null($row['id_member']) || $row['id_member'] == $lm2_guest_member_id) ? 0 : 1)) {
			array_push($drivers, $row['id_member']);
		}
		db_query("
			UPDATE {$lm2_db_prefix}event_entries
			SET informed_of_report = $informed
			WHERE id_event_entry = {$row['id_event_entry']}
			", __FILE__, __LINE__);
	}
	mysql_free_result($query);

	if ($drivers) {
		$recipients = array('to'=>array(), 'bcc'=>$drivers);
			$subject = "Incident report published - please read";
			$url = "http://www.simracing.org.uk/index.php?ind=lm2&event={$event}";
			$message = "The moderator's report for an event has been published which you should read because it involves you.
\nIt may be that you have been found partly or wholly to blame for an incident, or it may simply be that the moderator wishes to give you some advice about your driving or racecraft.
\nIn any case, please read the report by going [url={$url}]here[/url].
\nThanks,
The SRou Moderators";
			$store_outbox = false;
			$from = array(
				'id' => 0,
				'name' => '{nobodyName}',
				'username' => 'SimRacing.org.uk'
			);
			sendpm($recipients, $subject, $message, $store_outbox, $from);
	}
}
?>