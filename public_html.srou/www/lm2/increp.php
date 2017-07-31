<?php
require_once('../smf/Sources/Subs-Post.php');

$event = get_request_param('event');
$report = get_request_param('report');
$time = get_request_param('time');

function encodeSubject($subject) {
	global $func, $incidentReportForum;
	return strtr($func['htmlspecialchars']($subject), array("\r" => '', "\n" => '', "\t" => '')); //XXX: do we need this?
}

if ($body = get_request_param('body')) {
	global $func, $lm2_guest_member_id;

	$mod_id = get_request_param('to');
	$when = get_request_param('when');
	$event_group_full = get_request_param('event_group_full');
	$event_group_brief = get_request_param('event_group_brief');
	$where = get_request_param('where');
	$summary = get_request_param('summary');
	$unrep = get_request_param('unrep');
	$drivers = $_REQUEST['driver'];

	$attachments = array();

	$replayClip = $_FILES['replay'];
	if ($replayClip['size'] != 0) {
		echo '<P>Replay clip: ' . $replayClip['tmp_name'] . ' from ' . $replayClip['name'] . ' size ' . $replayClip['size'] . '</P>';
		$contents = file_get_contents($replayClip['tmp_name']);
		$attachmentOptions = array(
			'post' => 0,
			'poster' => $lm2_guest_member_id,
			'name' => $replayClip['name'],
			'tmp_name' => $replayClip['tmp_name'],
			'size' => $replayClip['size'],
		);
		createAttachment($attachmentOptions);
		if ($attachmentOptions['errors']) {
			die("Error(s) while uploading attachments: " . print_r($attachmentOptions['errors'], true));
		}
		$attachments[] = $attachmentOptions['id'];
	}

	if ($report == 0) {
		lm2_query("INSERT INTO ${lm2_db_prefix}reports"
			. " (report_summary, report_event)"
			. " VALUES (" . sqlString($summary) . ", $event)"
			, __FILE__, __LINE__);
		$report = db_insert_id();
	}

	$topic = 0;
	$query = db_query("SELECT IFNULL(id_topic, 0) AS incident_topic, event_date AS event_date"
		. " FROM {$lm2_db_prefix}events"
		. " LEFT JOIN {$db_prefix}topics ON incident_topic = id_topic" // In case it has been deleted - make a new one
		. " WHERE id_event = $event"
		, __FILE__, __LINE__);
	($row = mysql_fetch_assoc($query)) || die("can't find event $event");
	$topic = $row['incident_topic'];
	$whenCanonical = $row['event_date'];
	mysql_fetch_assoc($query) && die("more than one event $event");
	mysql_free_result($query);

	$userName = $user_info['name'];
	$userUserName = $user_info['username'];

	$subject = encodeSubject("Incident report: $event_group_brief, $when ($where)");
	$smf_url = "[url={$_SERVER['SROU_HOST_WWW']}/lm2/index.php?action=increp&report=$report&time=" . urlencode($time) . "]click here[/url]";

	if ($drivers) {
		$sql = "INSERT IGNORE INTO {$lm2_db_prefix}report_drivers (report, reported_driver) VALUES";
		$sep = " ";
		foreach ($drivers AS $driver) {
			$sql .= "$sep($report, $driver)";
			$sep = " ,";
		}
		lm2_query($sql, __FILE__, __LINE__);
	}

	$driver_list .= "\n\nThe incident involved the following drivers:[list]";
	$query = db_query("SELECT DISTINCT driver_name, driving_name"
		. " FROM {$lm2_db_prefix}event_entries"
		. ", {$lm2_db_prefix}drivers"
		. ", {$lm2_db_prefix}sim_drivers"
		. ", {$lm2_db_prefix}reports"
		. ", {$lm2_db_prefix}report_drivers"
		. " WHERE event = $event"
		. " AND id_report = $report"
		. " AND id_report = report"
		. " AND reported_driver = driver_member"
		. " AND id_sim_drivers = sim_driver"
		. " AND {$lm2_db_prefix}event_entries.member = driver_member"
		. " ORDER BY driving_name"
		, __FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($query)) {
		$name = $row['driving_name'];
		if (strcasecmp($name, $row['driver_name'])) {
			$name = "$name ({$row['driver_name']})";
		}
		$driver_list .= "[li]" . html_entity_decode($name, ENT_QUOTES) . "[/li]\n";
	}
	mysql_free_result($query);
	$driver_list .= "[/list]";

	if ($drivers) {
		// Remove reporting member from notification list.
		$drivers = array_diff($drivers, array($ID_MEMBER));
	}

	if ($drivers) {
		$recipients = array('to'=>array(), 'bcc'=>$drivers);
		$store_outbox = false;
		$from = array(
			'id' => 0,
			'name' => '{nobodyName}',
			'username' => 'SimRacing.org.uk'
		);

		$message = "An incident at time " . ($time ? $time : "(unknown)") . " in the server replay has been reported."
			. "\n\n$summary"
			. "$driver_list"
			. "\n\nThe incident may have been submitted by one of the above drivers, or by a witness or moderator."
			. "\n\nTo submit your own report on this incident (if you have not already done so), $smf_url";

		sendAndLogPM($recipients, $subject, $message, $store_outbox, $from);
	}

	$recipients = array('to'=>array($mod_id), 'bcc'=>array());
	$messageTop = "[I]Incident report submitted using LM2i[/I]"
		. "$messageTop\n\nServer replay time: [B]{$time}[/B]"
		. "\n\n$summary"
		. $driver_list
		. "\n\n";
	$messageBottom = "\n\nTo submit an additional report on this incident, $smf_url.";
	if (!is_null($unrep)) {
		$body .= "\n\n[I]{$unrep}[/I]";
	}

	$from = array(
		'id' => 0,
		'name' => "SimRacing.org.uk",
		'email' => ''
	);
	$msgOptions = array(
		'subject'=>encodeSubject("Incident report: $whenCanonical $event_group_brief ($where)"),
		'body'=>$func['htmlspecialchars']("$messageTop"."[quote author=$userName link=action=profile;u=$ID_MEMBER date=" . time() . "]$body"."[/quote]$messageBottom", ENT_QUOTES),
		'attachments'=>$attachments, // A list of attachment IDs
		'smileys_enabled' => 0,
	);
	$topicOptions = array('board'=>$incidentReportForum, 'id'=>$topic);
	createPost($msgOptions, $topicOptions, $from);
	if ($topic == 0) {
		$topic = $topicOptions['id'];
		lm2_query("UPDATE ${lm2_db_prefix}events SET incident_topic = $topic WHERE id_event = $event", __FILE__, __LINE__);
	}

	$store_outbox = true;
	$from = array(
		'id' => $ID_MEMBER,
		'name' => $userName,
		'username' => $userUserName, // For PM
	);

	sendAndLogPM($recipients, $subject, "$messageTop$body$messageBottom", $store_outbox, $from);

	echo "<P>Notifications sent; a copy of the report has been saved in your <A HREF='$boardurl/index.php?action=pm;f=outbox'>Outbox</A>."
		. "<BR /><A HREF='/index.php?ind=lm2&event=$event'>Return to event details</A>.</P>\n";
} else {
	echo "<H1>Submit Incident Report</H1>\n";

	$DEFAULT_SUMMARY = 'PLEASE ENTER A BRIEF SUMMARY OF THE INCIDENT';

	if ($report) {
		$event && die("cannot specify both event and report");
		$query = db_query("SELECT report_event AS event, report_summary AS summary
			FROM {$lm2_db_prefix}reports
			WHERE id_report = $report
			", __FILE__, __LINE__);
		($row = mysql_fetch_assoc($query)) || die("can't find report $report!");
		$event = $row['event']; 
		$summary = htmlentities($row['summary'], ENT_QUOTES);
		mysql_fetch_assoc($query) && die("multiple reports matching $report!");
		mysql_free_result($query);

		echo "<P><B>You are responding to an incident which has already been reported."
			. "<BR/>To report a <I>different</I> incident, <A HREF='index.php?action=increp&event=$event'>click here</A>.</B></P>\n";
	} else {
		$report = 0;
		$summary = $DEFAULT_SUMMARY;
	}

	$query = db_query("
		SELECT $circuit_html_clause AS circuit_html
		, event_date
		, event_status = 'U' AS is_unofficial
		, event_group
		, short_desc
		, full_desc
		, sim_replay_clips
		FROM {$lm2_db_prefix}events
		JOIN {$lm2_db_prefix}sims ON id_sim = sim
		JOIN {$lm2_db_prefix}event_groups ON id_event_group = event_group
		JOIN {$lm2_db_prefix}sim_circuits ON id_sim_circuit = sim_circuit
		JOIN {$lm2_db_prefix}circuits ON id_circuit = circuit
		JOIN {$lm2_db_prefix}circuit_locations ON id_circuit_location = circuit_location
		WHERE id_event = $event
		" , __FILE__, __LINE__);
	($row = mysql_fetch_assoc($query)) || die("can't find event $event!");
	$when = format_timestamp(timestamp2php($row['event_date']), false);
	$event_group_brief = htmlentities($row['short_desc'], ENT_QUOTES);
	$event_group_full = htmlentities($row['full_desc'], ENT_QUOTES);
	$circuit = $row['circuit_html'];
	$replay_clip_style = $row['sim_replay_clips'];
	mysql_fetch_assoc($query) && die("multiple events matching $event!");
	mysql_free_result($query);

	if (!$row['is_unofficial']) {
		echo "<P><B><SPAN STYLE='color: red'>Warning!</SPAN> <I>The moderator's report for this event has already been published.</I></B></P>\n";
	}

	($mod_id = find_event_moderator($event)) || die("can't find a moderator!");

	$bekind = htmlentities("I believe I was the victim, but I would prefer that no penalty is given", ENT_QUOTES);
	echo "<FORM enctype='multipart/form-data' METHOD=\"POST\">
		<P>$event_group_full, $when, $circuit</P>
		<INPUT TYPE=\"HIDDEN\" NAME=\"to\" VALUE=\"$mod_id\" />
		<INPUT TYPE=\"HIDDEN\" NAME=\"when\" VALUE=\"$when\" />
		<INPUT TYPE=\"HIDDEN\" NAME=\"event\" VALUE=\"$event\" />
		<INPUT TYPE=\"HIDDEN\" NAME=\"event_group_full\" VALUE=\"$event_group_full\" />
		<INPUT TYPE=\"HIDDEN\" NAME=\"event_group_brief\" VALUE=\"$event_group_brief\" />
		<INPUT TYPE=\"HIDDEN\" NAME=\"where\" VALUE=\"$circuit\" />
		<INPUT TYPE=\"HIDDEN\" NAME=\"report\" VALUE=\"$report\" />
		<P>Time of incident (in seconds, from server replay): <INPUT TYPE='EDIT' NAME='time' VALUE='" . htmlentities($time, ENT_QUOTES) ."' SIZE='10' />
		<BR />Unless the incident happened during a session prior to the race, or is a general comment, please enter a time.
		<BR />The box is small but you can enter multiple times seperated by commas or slashes if you wish
		where a report covers multiple instances of, for example, corner cutting.
		<BR /><B>Please do not enter unrelated incidents together</B>, even if they involve the same drivers.</P>
		<P>Summary";
	if ($report == 0) {
		echo " (sent to involved drivers): <INPUT TYPE='EDIT' NAME='summary' VALUE='$summary' MAXLENGTH='80' SIZE='80' />"
			. "<BR />Please enter a brief description which will help any other involved drivers identify the details and nature of the incident.";
	} else {
		echo ": <B>$summary</B> <INPUT TYPE='HIDDEN' NAME='summary' VALUE='$summary' />";
	}

	//FIXME: is there any way to get the usual SMF message editing box here?
	$DEFAULT_REPORT = 'PLEASE ENTER YOUR REPORT HERE';
	echo  "</P>
		<P>Description of incident (sent only to the moderators):<BR /><TEXTAREA ROWS=\"8\" COLS=\"80\" NAME=\"body\">$DEFAULT_REPORT</TEXTAREA></P>
		<P><INPUT TYPE='CHECKBOX' NAME='unrep' VALUE='$bekind'$def />$bekind</P>
		<P>Drivers involved (please tick any driver who might wish to submit a report):";

	$query = db_query("
		SELECT DISTINCT driver_member
		, driver_name
		, driving_name
		, reported_driver IS NULL AS unreported
		FROM {$lm2_db_prefix}event_entries
		JOIN {$lm2_db_prefix}drivers ON member = driver_member
		JOIN {$lm2_db_prefix}sim_drivers ON id_sim_drivers = sim_driver
		LEFT JOIN {$lm2_db_prefix}reports ON id_report = $report
		LEFT JOIN {$lm2_db_prefix}report_drivers ON id_report = report AND reported_driver = driver_member
		WHERE event = $event
		AND driver_member <> $guest_member_id
		ORDER BY driving_name
		" , __FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($query)) {
		$name = $row['driving_name'];
		if (strcasecmp($name, $row['driver_name'])) {
			$name = "$name ({$row['driver_name']})";
		}
		$def = $row['unreported'] == 1 ? ($ID_MEMBER == $row['driver_member'] ? ' CHECKED' : '') : ' CHECKED DISABLED';
		echo "<BR/><INPUT TYPE='CHECKBOX' NAME='driver[]' VALUE='{$row['driver_member']}'$def /> $name\n";
		if ($row['unreported'] == 1 && $ID_MEMBER == $row['driver_member']) {
			echo "<BR/><I>If you are reporting an incident but were not involved in it, please clear the box next to your name</I>\n";
		}
	}
	mysql_free_result($query);
	echo "</P>\n";

	if ($replay_clip_style == 'G') {
		echo "<P>Replay clip (please Zip it!): <INPUT size='120' name='replay' type='file' /> (maximum size {$modSettings['attachmentSizeLimit']}k)</P>\n";
	}

?>
<SCRIPT>
function checkForm(form) {
	if (form.summary.value == '' || form.summary.value == '<?php echo $DEFAULT_SUMMARY; ?>') {
		alert("You must enter your own text into the Summary field");
		return false;
	}
	if (form.body.value == '' || form.body.value == '<?php echo $DEFAULT_REPORT; ?>') {
		alert("You must enter your own text into the Description field");
		return false;
	}
	return true;
}
</SCRIPT>
	<INPUT TYPE='SUBMIT' VALUE='Submit Report' onClick='return checkForm(form)' />
</FORM>
<?php
}

function sendAndLogPM($recipients, $subject, $message, $store_outbox, $from) {
//array_push($recipients['bcc'], 1); // In case of suspicious behaviour.
//global $ID_MEMBER; if ($ID_MEMBER == 1) { $recipients = array('to'=>array(1), 'bcc'=>array()); } // For testing.
	echo "<!-- About to send...\n$subject\n$message\nTo " . print_r($recipients, true) . " -->\n";
//if ($ID_MEMBER == 1) $logs = array(); else
	$logs = sendpm($recipients, $subject, $message, $store_outbox, $from);
	echo "<!-- Sent driver notifications; " . print_r($logs, true) . " -->\n";
}

?>