<?php
# First, some LM2/SRou settings... 
putenv("TZ=Europe/London");
# Move at least some of these into the Subs-LM2 module.
$lm2_db_prefix = "{$_SERVER['SROU_DB_PREFIX']}lm2.lm2_";
$lm2_hst_prefix = "{$_SERVER['SROU_DB_PREFIX']}lm2.hst_";
$lm2_view_prefix = "{$_SERVER['SROU_DB_PREFIX']}views.";
$lm2_ukgpl_prefix = "{$_SERVER['SROU_DB_PREFIX']}ukgpl.";
$lm2_guest_member_id = 2;
$lm2_mods_group = 10;
$lm2_mods_group_court = 45;
$lm2_mods_group_server = 48;
$lm2_mods_group_ukgpl = 49;
$lm2_incident_report_forum = 13;
$lm2_series_details_topic = 3905;
$lm2_ukgpl_migration_sim_driver = 7283;
$lm2_circuit_html_clause = "CONCAT(brief_name, IFNULL(CONCAT(' (', layout_name, ')'), ''))";
$lm2_circuit_link_clause = "CONCAT('<A TITLE=\"', IFNULL(layout_notes, ''),'\" HREF=\"/index.php?ind=lm2&circuit=', id_circuit, '\">', $lm2_circuit_html_clause, '</A>')";
$lm2_class_style_clause = "CONCAT(' STYLE=\"background-color: #', class_bgcolor, '\"') AS class_style";
$lm2_lap_record_types = array('R'=>'Race', 'Q'=>'Qualifying');
$lm2_lap_record_clause = "CASE lap_record_type WHEN 'Q' THEN qual_best_lap_time ELSE race_best_lap_time END = record_lap_time";
$lm2_penalty_types = array(
	''=>'', // Can be used when the penalty does not relate to a driving standards infingement.
	'A'=>'advice',
	'C'=>'caution',
	'W'=>'warning',
	'P'=>'penalty',
);
$lm2_champ_types = array('D'=>'Drivers', 'T'=>'Teams', 'M'=>'Manufacturers');
$lm2_penalty_points_clause = "CASE penalty_type WHEN 'P' THEN 2 WHEN 'W' THEN 1 WHEN 'C' THEN 0 ELSE NULL END";
$colsep = "<TD>&nbsp;&nbsp;</TD>";

$db_show_debug = false;

/**********************************************************************************
* Settings.php                                                                    *
***********************************************************************************
* SMF: Simple Machines Forum                                                      *
* Open-Source Project Inspired by Zef Hemel (zef@zefhemel.com)                    *
* =============================================================================== *
* Software Version:           SMF 1.1                                             *
* Software by:                Simple Machines (http://www.simplemachines.org)     *
* Copyright 2006 by:          Simple Machines LLC (http://www.simplemachines.org) *
*           2001-2006 by:     Lewis Media (http://www.lewismedia.com)             *
* Support, News, Updates at:  http://www.simplemachines.org                       *
***********************************************************************************
* This program is free software; you may redistribute it and/or modify it under   *
* the terms of the provided license as published by Simple Machines LLC.          *
*                                                                                 *
* This program is distributed in the hope that it is and will be useful, but      *
* WITHOUT ANY WARRANTIES; without even any implied warranty of MERCHANTABILITY    *
* or FITNESS FOR A PARTICULAR PURPOSE.                                            *
*                                                                                 *
* See the "license.txt" file for details of the Simple Machines license.          *
* The latest version can always be found at http://www.simplemachines.org.        *
**********************************************************************************/


########## Maintenance ##########
# Note: If $maintenance is set to 2, the forum will be unusable!  Change it to 0 to fix it.
$maintenance = 0;		# Set to 1 to enable Maintenance Mode, 2 to make the forum untouchable. (you'll have to make it 0 again manually!)
$mtitle = 'Maintenance Mode';		# Title for the Maintenance Mode message.
$mmessage = 'Migration in progress.';		# Description of why the forum is in maintenance mode.  

########## Forum Info ##########
$mbname = 'SimRacing.org.uk';		# The name of your forum.
$language = 'english';		# The default language file set for the forum.
$boardurl = "https://{$_SERVER['SROU_HOST_WWW']}/smf";		# URL to your forum's folder. (without the trailing /!)
$webmaster_email = 'smf@SimRacing.org.uk';		# Email address to send emails from. (like noreply@yourdomain.com.)
$cookiename = "{$_SERVER['SROU_DB_PREFIX']}20231019_smf";		# Name of the cookie to set for authentication.

########## Database Info ##########
$db_server = $_SERVER['SROU_DB_HOST'];
$db_name = "{$_SERVER['SROU_DB_PREFIX']}smf";
$db_user = $db_name;
$db_passwd = trim(file_get_contents("{$_SERVER['SROU_ROOT']}/cfg/smf-db.password"));
$db_prefix = 'smf_';
$db_persist = 0;
$db_error_send = 0;
$db_character_set = 'utf8';

########## Directories/Files ##########
# Note: These directories do not have to be changed unless you move things.
$boarddir = "{$_SERVER['SROU_ROOT']}/public_html.srou/www/smf";
$sourcedir = "{$boarddir}/Sources";

########## Error-Catching ##########
# Note: You shouldn't touch these settings.
$db_last_error = 1296596322;


# Make sure the paths are correct... at least try to fix them.
if (!file_exists($boarddir) && file_exists(dirname(__FILE__) . '/agreement.txt'))
	$boarddir = dirname(__FILE__);
if (!file_exists($sourcedir) && file_exists($boarddir . '/Sources'))
	$sourcedir = $boarddir . '/Sources';

?>
