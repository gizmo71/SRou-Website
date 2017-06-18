<?php

if (!defined('SMF'))
	die('Hacking attempt...');

function ManageTeam() {
	global $context, $boardurl, $smcFunc;

	loadTemplate('ManageTeam');
	$context['ManageTeam'] = array('messages' => array());

	if (empty($_REQUEST['team']) || !is_numeric($_REQUEST['team'])) {
		fatal_error("No valid team specified " . print_r($_REQUEST, true));
	}

	$query = $smcFunc['db_query'](null, "
		SELECT *
		FROM {lm2_prefix}teams
		WHERE id_team = {int:team}
		", array('team'=>$_REQUEST['team']));
	($row = $smcFunc['db_fetch_assoc']($query)) || die("can't find team!");
	$context['ManageTeam']['team'] = $row;
	$smcFunc['db_fetch_assoc']($query) && die("multiple events matching $event!");
	$smcFunc['db_free_result']($query);

	$context['page_title'] = "Manage Team";
       	$context['sub_template'] = 'entry';
}

?>
