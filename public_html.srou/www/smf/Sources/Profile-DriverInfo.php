<?php

function lm2ProfileDriverInfo($memID) {
	global $context, $memberContext, $smcFunc, $user_info;

	($memID == $user_info['id']) || die("$memID != {$user_info['id']}");
	if (!loadMemberContext($memID) || !isset($memberContext[$memID]))
		fatal_lang_error('not_a_user', false);

	$context['page_title'] = "Driver Details - {$memberContext[$memID]['name']}";
	$context['messages'] = array();

	// Process any updates

	if (isset($_REQUEST['saveDriverInfo'])) {
		updateDriverInfo($memID);
	}

	// Load current GPLRank and country if available

	$query = $smcFunc['db_query'](null, "
		SELECT iso3166_code, gplrank
		FROM {lm2_prefix}drivers
		WHERE driver_member = {int:id}
		", array('id'=>$memID));
	if ($row = $smcFunc['db_fetch_assoc']($query)) {
		$context['lm2'] = $row;
		if (is_null($context['lm2']['gplrank'])) {
			$context['lm2']['gplrank'] = '';
		} else if ($context['lm2']['gplrank'] >= 0) {
			$context['lm2']['gplrank'] = "+{$context['lm2']['gplrank']}";
		}
	} else {
		$context['lm2'] = array ('iso3166_code'=>null, 'gplrank'=>'');
	}
	$smcFunc['db_free_result']($query);

	// Current driving names

	$query = $smcFunc['db_query'](null, "
		SELECT id_sim, sim_name, driving_name
		FROM {lm2_prefix}sims
		LEFT JOIN {lm2_prefix}driver_details ON sim = id_sim AND driver = {int:id}
		WHERE use_driver_details = {string:yes}
		", array('id'=>$context['user']['id'], 'yes'=>'Y'));
	$context['lm2']['names'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($query)) {
		$context['lm2']['names'][] = $row;
	}
	$smcFunc['db_free_result']($query);

	// List of countries

	$context['lm2']['iso3166_codes'] = array();
	$query = $smcFunc['db_query'](null, "
		SELECT id_iso3166 AS id, iso3166_name AS description
		FROM {lm2_prefix}iso3166
		ORDER BY description", array());
	while ($row = $smcFunc['db_fetch_assoc']($query)) {
		$context['lm2']['iso3166_codes'][$row['id']] = $row['description'];
	}
	$smcFunc['db_free_result']($query);

	if (!isset($context['lm2']['iso3166_codes'][$context['lm2']['iso3166_code']])) {
		$context['lm2']['iso3166_code'] = 'XX';
	}

	// List of historic drivers

	$context['lm2']['historic_drivers'] = array();
	$query = $smcFunc['db_query'](null, "
		SELECT driver_member AS id, driver_name AS name, approved
		FROM {lm2_prefix}drivers
		LEFT JOIN {ukgpl_prefix}_map_drivers ON driver_member = hist_driver
		WHERE driver_member > {int:real_id_limit}
		  AND (live_driver = {int:driver} OR approved IS NULL)
		ORDER BY name
		", array('driver'=>$memID, 'real_id_limit'=>10000000));
	while ($row = $smcFunc['db_fetch_assoc']($query)) {
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
		$context['lm2']['historic_drivers'][$row['id']] = array(
			'name'=>$row['name'], 'selected'=>!is_null($row['approved']));
	}
	$smcFunc['db_free_result']($query);

	loadTemplate('Profile-DriverInfo');
}

function updateDriverInfo($driver) {
	global $smcFunc, $context, $memberContext;

	if (is_numeric($ukgpl_driver = lm2GetRequestParam('ukgpl_driver'))) {
		$smcFunc['db_insert']('ignore', '{ukgpl_prefix}_map_drivers',
			array('hist_driver'=>'int', 'live_driver'=>'int', 'approved'=>'int'),
			array($ukgpl_driver, $driver, 0),
			array('hist_driver', 'live_driver'));
	}

	$iso3166_code = lm2GetRequestParam('iso3166_code');

	$gplrank = lm2GetRequestParam('gplrank');
	if (!is_numeric($gplrank)) {
		if ($gplrank != '') $context['messages'][] = "Non-numeric GPLRank discarded";
		$gplrank = null;
	}

	$smcFunc['db_query'](null, '
		INSERT INTO {lm2_prefix}drivers (driver_member, driver_name, iso3166_code, gplrank)
		VALUES ({int:driver}, {string:name}, {string:iso3166_code}, {raw:gplrank})
		ON DUPLICATE KEY
		UPDATE iso3166_code = {string:iso3166_code}, gplrank = {raw:gplrank}
		', array(
			'driver' => $driver,
			'name' => $memberContext[$driver]['name'],
			'iso3166_code' => $iso3166_code,
			'gplrank' => is_null($gplrank) ? 'NULL' : $gplrank));

	if (!empty($_REQUEST['sim_name']) && is_array($_REQUEST['sim_name'])) {
		foreach ($_REQUEST['sim_name'] as $sim => $name) {
			is_numeric($sim) || die("Hacking attempt; sim ID $sim");
			$name = trim($name);
			if (strlen($name) < 2) {
				$context['messages'][] = "Ignoring short driving name";
				continue;
			}
			$smcFunc['db_insert']('replace', '{lm2_prefix}driver_details',
				array('driver'=>'int', 'sim'=>'int', 'driving_name'=>'string'),
				array($driver, $sim, $name),
				array('driver', 'sim'));
		}
	}
}

?>
