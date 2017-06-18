<?php

function template_main() {
	fatal_error("Incident report with no template; " . print_r($_REQUEST, true));
}

function template_entry() {
	global $context, $scripturl, $modSettings;
	$beKindText = htmlentities("I believe I was the victim, but I would prefer that no penalty is given", ENT_QUOTES);

	show_messages($context['ReportIncident']['messages']);

	echo "<H1 id='submit_inc'>Submit Incident Report</H1>
		<P>", $context['ReportIncident']['event_group_full'], ", ", $context['ReportIncident']['when'], ", ", $context['ReportIncident']['circuit'], "</P>";
	if (!empty($context['ReportIncident']['report'])) {
		echo "<P><B>You are responding to an incident which has already been reported.<BR/>
			To report a <I>different</I> incident, <A HREF='$scripturl?action=ReportIncident&event=",
			$context['ReportIncident']['event'], "'>click here</A>.</B></P>\n";
	}
	echo "<FORM METHOD='POST' ACTION='$scripturl?action=ReportIncident#submit_inc' enctype='multipart/form-data'>";
	if (empty($context['ReportIncident']['report'])) {
		echo "<INPUT NAME='event' VALUE='", $context['ReportIncident']['event'], "' TYPE='HIDDEN' />\n";
	} else {
		echo "<INPUT NAME='report' VALUE='", $context['ReportIncident']['report'], "' TYPE='HIDDEN' />\n";
	}
	echo "<P><LABEL>Time of incident (in seconds, from server replay): <INPUT TYPE='EDIT' NAME='time' VALUE='",
			htmlentities($context['ReportIncident']['time'], ENT_QUOTES), "' SIZE='10' /></LABEL>
		<BR />Unless the incident happened during a session prior to the race, or is a general comment, please enter a time.
		<BR />The box is small but you can enter multiple times seperated by commas or slashes if you wish
		where a report covers multiple instances of, for example, corner cutting.
		<BR /><B>Please do not enter unrelated incidents together</B>, even if they involve the same drivers.</P>
		<P><LABEL", isset($context['ReportIncident']['no_summary']) ? ' class="error"' : '', " FOR='summary'>Summary</LABEL>";
	if (empty($context['ReportIncident']['report'])) {
		echo " (sent to involved drivers):
			<INPUT TYPE='EDIT' NAME='summary' VALUE='", htmlentities($context['ReportIncident']['summary'], ENT_QUOTES), "' ID='summary'
			MAXLENGTH='80' SIZE='80' PLACEHOLDER='PLEASE ENTER A BRIEF SUMMARY OF THE INCIDENT' /><BR />
			Please enter a brief description which will help any other involved drivers identify the details and nature of the incident.";
	} else {
		echo ": <B>", $context['ReportIncident']['summary'], "</B>";
	}
	echo "</P>
		<P><LABEL", isset($context['ReportIncident']['no_body']) ? ' class="error"' : '', " for='bodyText'>Description of incident</LABEL>
		(sent only to the moderators):<BR />
		<TEXTAREA ROWS='8' COLS='80' NAME='body' ID='bodyText' PLACEHOLDER='PLEASE ENTER YOUR REPORT HERE'>",
			htmlentities($context['ReportIncident']['body'], ENT_QUOTES), "</TEXTAREA></P>
		<P><INPUT TYPE='CHECKBOX' NAME='unrep' VALUE='$beKindText'", empty($context['ReportIncident']['unrep']) ? '' : ' CHECKED', ">
			<LABEL>", $beKindText, "</LABEL></INPUT></P>
		<P>Drivers involved (please tick any driver who might wish to submit a report):\n";
	foreach ($context['ReportIncident']['drivers'] as $row) {
		$name = $row['driving_name']; // Pre-encoded in HTML
		if (strcasecmp($name, $row['driver_name'])) $name .= " ({$row['driver_name']})";
		$def = $row['unreported'] ? ($row['reporting'] ? ' CHECKED' : '') : ' CHECKED DISABLED';
		echo "<BR/><INPUT TYPE='CHECKBOX' NAME='driver{$row['driver_member']}' VALUE='slartibartfast'$def /> ", $name, "\n";
		if ($context['user']['id'] == $row['driver_member'] && $row['unreported'])
			echo "<BR/><I>If you are reporting an incident but were not involved in it, please clear the box next to your name</I>\n";
	}
	echo "</P>\n";
	if ($context['ReportIncident']['replay_clip_style'] == 'G') {
		echo "<P>Replay clip (please Zip it!): <INPUT size='120' name='replay[]' type='file' /> (maximum size {$modSettings['attachmentSizeLimit']}k)</P>\n";
	}
	if (!empty($context['ReportIncident']['replay'])) {
		echo "Replay clips already uploaded (will be lost if report is not filed):<UL>\n";
		foreach ($context['ReportIncident']['replay'] as $name => $file) {
			echo "<LI>", htmlentities($name, ENT_QUOTES), " (", $file['size'] / 1024.0, "k)</LI>\n";
		}
		echo "</UL>\n";
	}
	echo "<INPUT TYPE='SUBMIT' /></FORM>";
}

function template_filed() {
	global $context, $boardurl;

	show_messages($context['ReportIncident']['messages']);

	echo "<P>Notifications sent; a copy of the report has been saved in your <A HREF='$boardurl/index.php?action=pm;f=sent'>Outbox</A>."
		. "<BR /><A HREF='/index.php?ind=lm2&event={$context['ReportIncident']['event']}'>Return to event details</A>.</P>\n";

	//echo "<PRE id='ReportIncident_stuff'>", htmlentities(print_r($context['ReportIncident'], true), ENT_QUOTES), "</PRE>\n";
}

?>
