<?php

if (!defined('SMF'))
	die('Hacking attempt...');

if (!empty($_REQUEST['body'])) {
	require_once("$sourcedir/Subs.php");
	require_once("$sourcedir/Subs-Post.php");
	require_once("$sourcedir/Subs-Editor.php");
}

function ReportIncident() {
	global $context, $boardurl, $smcFunc, $lm2_circuit_html_clause;

	loadTemplate('ReportIncident');

	if (!empty($_REQUEST['report'])) {
		empty($_REQUEST['event']) || die("cannot specify both event and report");
		$query = $smcFunc['db_query'](null, "
			SELECT report_event AS event, report_summary AS summary, id_report AS report
			FROM {$GLOBALS['lm2_db_prefix']}reports
			WHERE id_report = {int:report}
			", array('report'=>(int)$_REQUEST['report']));
		($context['ReportIncident'] = $smcFunc['db_fetch_assoc']($query)) || die("can't find report $report!");
		$smcFunc['db_fetch_assoc']($query) && die("multiple reports matching $report!");
		$smcFunc['db_free_result']($query);
	} else if (!empty($_REQUEST['event'])) {
		is_numeric($_REQUEST['event']) || fatal_error("Bad event {$_REQUEST['event']}");
		$context['ReportIncident'] = array('event' => (int) $_REQUEST['event'], 'report'=>0);
	} else {
		fatal_error("Incident report with no report or event; " . print_r($_REQUEST, true));
	}
	$context['ReportIncident']['messages'] = array();

	$query = $smcFunc['db_query'](null, "
		SELECT $lm2_circuit_html_clause AS circuit_html
		, event_date
		, event_status = {string:unofficial_status} AS is_unofficial
		, event_group
		, short_desc
		, full_desc
		, sim_replay_clips
		, IFNULL(id_topic, 0) AS incident_topic
		FROM {$GLOBALS['lm2_db_prefix']}events
		JOIN {$GLOBALS['lm2_db_prefix']}sims ON id_sim = sim
		JOIN {$GLOBALS['lm2_db_prefix']}event_groups ON id_event_group = event_group
		JOIN {$GLOBALS['lm2_db_prefix']}sim_circuits ON id_sim_circuit = sim_circuit
		JOIN {$GLOBALS['lm2_db_prefix']}circuits ON id_circuit = circuit
		JOIN {$GLOBALS['lm2_db_prefix']}circuit_locations ON id_circuit_location = circuit_location
		LEFT JOIN {db_prefix}topics ON incident_topic = id_topic
		WHERE id_event = {int:event}
		", array('unofficial_status'=>'U', 'event'=>$context['ReportIncident']['event']));
	($row = $smcFunc['db_fetch_assoc']($query)) || die("can't find event {$context['ReportIncident']['event']}!");
	$context['ReportIncident']['when'] = lm2FormatTimestamp(lm2Timestamp2php($row['event_date']), false);
	$context['ReportIncident']['event_group_brief'] = htmlentities($row['short_desc'], ENT_QUOTES);
	$context['ReportIncident']['event_group_full'] = htmlentities($row['full_desc'], ENT_QUOTES);
	$context['ReportIncident']['circuit'] = $row['circuit_html'];
	$context['ReportIncident']['replay_clip_style'] = $row['sim_replay_clips'];
	$context['ReportIncident']['is_unofficial'] = $row['is_unofficial'];
	$context['ReportIncident']['incident_topic'] = $row['incident_topic'];
	$smcFunc['db_fetch_assoc']($query) && die("multiple events matching {$context['ReportIncident']['event']}!");
	$smcFunc['db_free_result']($query);

	($context['ReportIncident']['mod_id'] = lm2FindEventModerator($context['ReportIncident']['event'])) || die("can't find a moderator!");

	if (!$row['is_unofficial']) {
		$context['ReportIncident']['messages'][] = "Warning! The moderator's report for this event has already been published.";
	}

	if (isValidAndSubmitted()) {
		$context['page_title'] = "Incident Report Filed";
        	$context['sub_template'] = 'filed';
		return;
	}

	$context['page_title'] = "Submit Incident Report";
       	$context['sub_template'] = 'entry';
}

function isValidAndSubmitted() {
	global $context, $smcFunc, $lm2_guest_member_id;

	$context['ReportIncident']['drivers'] = array();
	$query = $smcFunc['db_query'](null, "
		SELECT DISTINCT driver_member
		, driver_name
		, driving_name
		, reported_driver IS NULL AS unreported
		FROM {$GLOBALS['lm2_db_prefix']}event_entries
		JOIN {$GLOBALS['lm2_db_prefix']}drivers ON member = driver_member
		JOIN {$GLOBALS['lm2_db_prefix']}sim_drivers ON id_sim_drivers = sim_driver
		LEFT JOIN {$GLOBALS['lm2_db_prefix']}reports ON id_report = {int:report}
		LEFT JOIN {$GLOBALS['lm2_db_prefix']}report_drivers ON id_report = report AND reported_driver = driver_member
		WHERE event = {int:event}
		AND driver_member <> $lm2_guest_member_id
		ORDER BY driving_name
		", array('report'=>$context['ReportIncident']['report'], 'event'=>$context['ReportIncident']['event']));
	while ($row = $smcFunc['db_fetch_assoc']($query)) {
		$row['reporting'] = !empty($_REQUEST["driver{$row['driver_member']}"]) || (!isset($_REQUEST['body']) && $row['driver_member'] == $context['user']['id']);
		$context['ReportIncident']['drivers'][] = $row;
	}
	empty($context['ReportIncident']['drivers']) && fatal_error("No entries for event {$context['ReportIncident']['event']}");
	$smcFunc['db_free_result']($query);

	// First time in, nothing to validate.
	if (!isset($_REQUEST['body'])) {
		// Set defaults.
		$context['ReportIncident']['time'] = empty($_REQUEST['time']) ? '' : htmlspecialchars_decode($_REQUEST['time']);
		$context['ReportIncident']['body'] = '';
		$context['ReportIncident']['unrep'] = '';
		if ($context['ReportIncident']['report'] == 0) $context['ReportIncident']['summary'] = '';
		$_SESSION['lm2_replay'] = array();
		return false;
	}

	$valid = true;

	$context['ReportIncident']['replay'] = empty($_SESSION['lm2_replay']) ? array() : $_SESSION['lm2_replay'];
	if (!empty($_FILES['replay'])) {
		is_array($_FILES['replay']['error']) || fatal_error("Replay attachment not array");
		foreach ($_FILES['replay']['tmp_name'] as $n => $dummy) {
			switch ($_FILES['replay']['error'][$n]) {
			case UPLOAD_ERR_NO_FILE: continue 2;
			case UPLOAD_ERR_OK:
				$name = $_FILES['replay']['name'][$n];
				$tmpName = tempnam(null, "lm2");
				move_uploaded_file($_FILES['replay']['tmp_name'][$n], "$tmpName") || fatal_error("Couldn't move uploaded file");
				if (!empty($_SESSION['lm2_replay'][$name])) $context['ReportIncident']['messages'][] = "Warning: replacing replay $name";
				$_SESSION['lm2_replay'][$name] = array('tmp_name' => $tmpName, 'size' => $_FILES['replay']['size'][$n]);
				break;
			default:
				$context['ReportIncident']['messages'][] = "Failed to upload {$_FILES['replay']['name'][$n]} (error {$_FILES['replay']['error'][$n]})";
				$valid = false;
			}
		}
		$context['ReportIncident']['replay'] = $_SESSION['lm2_replay'];
	}

	$context['ReportIncident']['time'] = trim($_REQUEST['time']);
	$context['ReportIncident']['body'] = trim($_REQUEST['body']);
	$context['ReportIncident']['unrep'] = trim(lm2ArrayValue($_REQUEST, 'unrep'));
	if ($context['ReportIncident']['report'] == 0) $context['ReportIncident']['summary'] = trim($_REQUEST['summary']);

	if (empty($context['ReportIncident']['summary'])) {
		$context['ReportIncident']['messages'][] = "Report summary must be completed";
		$context['ReportIncident']['no_summary'] = true;
		$valid = false;
	}
	if (empty($context['ReportIncident']['body'])) {
		$context['ReportIncident']['messages'][] = "Report details must be completed";
		$context['ReportIncident']['no_body'] = true;
		$valid = false;
	}

	if ($valid) submitReport();

	return $valid;
}

// Will process some data destructively!
function submitReport() {
	global $context, $smcFunc, $lm2_guest_member_id, $user_info, $boardurl;

	encodeBodyPart($context['ReportIncident']['body']);

	// Copied from Post.php
	$subject = strtr($smcFunc['htmlspecialchars']("Incident report: {$context['ReportIncident']['event_group_brief']}"
		. ", {$context['ReportIncident']['when']} ({$context['ReportIncident']['circuit']})"), array("\r" => '', "\n" => '', "\t" => ''));

	if ($context['ReportIncident']['report'] == 0) {
		$context['ReportIncident']['report'] = $smcFunc['db_insert']('ignore', // If 'insert', new row ID won't be found.
			"{$GLOBALS['lm2_db_prefix']}reports",
			array('report_summary'=>'string', 'report_event'=>'int'),
			array($context['ReportIncident']['summary'], 'event'=>$context['ReportIncident']['event']),
			array('id_report'=>'int'),
			1);
	}

	$driver_list = "\n\nThe incident involved the following drivers:[list]";
	$drivers = array();
	foreach ($context['ReportIncident']['drivers'] AS $driver) {
		if ($driver['reporting']) {
			$smcFunc['db_insert']('ignore', "{$GLOBALS['lm2_db_prefix']}report_drivers",
				array('report'=>'int', 'reported_driver'=>'int'),
				array($context['ReportIncident']['report'], $driver['driver_member']));
		} else if ($driver['unreported']) {
			continue;
		}
		$drivers[] = $driver['driver_member'];
		$name = $driver['driving_name'];
		if (strcasecmp($name, $driver['driver_name'])) {
			$name = "$name ({$driver['driver_name']})";
		}
		$driver_list .= "\n[li]{$name}[/li]";
	}
	$driver_list .= "\n[/list]";

	if ($drivers) {
		// Remove reporting member from notification list.
		$drivers = array_diff($drivers, array($user_info['id']));
	}

	$smf_url = "[url=$boardurl/index.php?action=ReportIncident&report={$context['ReportIncident']['report']}"
		. "&time=" . urlencode($context['ReportIncident']['time']) . "]click here[/url]";
	encodeBodyPart($context['ReportIncident']['summary']);

	if ($drivers) {
		$recipients = array('to'=>array(), 'bcc'=>$drivers);
		$store_outbox = false;
		$from = array(
			'id' => 0,
			'name' => '{nobodyName}',
			'username' => 'SimRacing.org.uk'
		);

		$message = "An incident at time " . ($context['ReportIncident']['time'] ?: "(unknown)") . " in the server replay has been reported."
			. "\n\n" . $context['ReportIncident']['summary']
			. "$driver_list"
			. "\n\nThe incident may have been submitted by one of the above drivers, or by a witness or moderator."
			. "\n\nTo submit your own report on this incident (if you have not already done so), $smf_url";

		sendpm($recipients, $subject, $message, $store_outbox, $from);
	}

	$attachments = array();
	foreach ($context['ReportIncident']['replay'] as $name=>$replay) {
		$attachmentOptions = array(
			'post' => 0,
			'poster' => $lm2_guest_member_id,
			'name' => $name,
			'tmp_name' => $replay['tmp_name'],
			'size' => $replay['size'],
		);
		if (!createAttachment($attachmentOptions)) {
			$context['ReportIncident']['messages'][] = "Error(s) while uploading attachment: " . print_r($attachmentOptions, true);
		} else {
			$attachments[] = $attachmentOptions['id'];
		}
	}

	$userName = $user_info['name'];
	$userUserName = $user_info['username'];

	$recipients = array('to'=>array($context['ReportIncident']['mod_id']), 'bcc'=>array());
	$messageTop = "[I]Incident report submitted using LM2i[/I]"
		. "\n\nServer replay time: [B]{$context['ReportIncident']['time']}[/B]"
		. "\n\n{$context['ReportIncident']['summary']}"
		. $driver_list
		. "\n\n";
	$messageBottom = "\n\nTo submit an additional report on this incident, $smf_url.";
	if (!empty($context['ReportIncident']['unrep'])) {
		$body .= "\n\n[I]{$context['ReportIncident']['unrep']}[/I]";
	}

	$from = array(
		'id' => 0,
		'name' => "SimRacing.org.uk",
		'email' => ''
	);
	$msgOptions = array(
		'subject'=>$subject,
		'body'=>"$messageTop"."[quote author=$userName link=action=profile;u={$user_info['id']} date="
			. time() . "]{$context['ReportIncident']['body']}[/quote]$messageBottom",
		'attachments'=>$attachments, // A list of attachment IDs
		'smileys_enabled' => 0,
	);
	$topicOptions = array('board'=>$GLOBALS['lm2_incident_report_forum'], 'id'=>$context['ReportIncident']['incident_topic']);
	createPost($msgOptions, $topicOptions, $from);
	if ($context['ReportIncident']['incident_topic'] == 0) {
		$context['ReportIncident']['incident_topic'] = $topicOptions['id'];
		$smcFunc['db_query'](null, "UPDATE {$GLOBALS['lm2_db_prefix']}events SET incident_topic = {int:topic} WHERE id_event = {int:event}",
			array('topic'=>$context['ReportIncident']['incident_topic'], 'event'=>$context['ReportIncident']['event']));
	}

	$store_outbox = true;
	$from = array(
		'id' => $user_info['id'],
		'name' => $userName,
		'username' => $userUserName, // For PM
	);

	sendpm($recipients, $subject, "$messageTop{$context['ReportIncident']['body']}$messageBottom", $store_outbox, $from);
}

function encodeBodyPart(&$text) {
	// Based on code in Post.php
	$text = $GLOBALS['smcFunc']['htmlspecialchars'](un_htmlspecialchars(html_to_bbc($text)));
	preparsecode($text);
}

?>
