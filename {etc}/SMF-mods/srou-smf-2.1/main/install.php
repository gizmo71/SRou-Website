<?php

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	die("<b>Error:</b> Cannot install - please verify you put this in the same place as SMF's SSI.php.");

// updateSettings(...)?

// https://wiki.simplemachines.org/smf/Integration_hooks
add_integration_function('integrate_pre_include', '$sourcedir/Subs-LM2.php', TRUE);
add_integration_function('integrate_menu_buttons', 'lm2AddButtons', TRUE);
add_integration_function('integrate_actions', 'lm2AddActions', TRUE);
add_integration_function('integrate_load_permissions', 'lm2AddPermissions', TRUE);
add_integration_function('integrate_pre_profile_areas', 'lm2AddProfileAreas', TRUE);
add_integration_function('integrate_display_topic', 'lm2AddTopicDetails', TRUE);
add_integration_function('integrate_load_theme', 'loadThemeData', TRUE);
//add_integration_function('integrate_theme_include', '$boarddir/../layout-header.php', TRUE);
// integrate_buffer - will this be easier than editing the index.template.php?

?>