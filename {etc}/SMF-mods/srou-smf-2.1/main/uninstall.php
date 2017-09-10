<?php

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	die("<b>Error:</b> Cannot install - please verify you put this in the same place as SMF's SSI.php.");

// updateSettings(...)?

//remove_integration_function('integrate_theme_include', '$boarddir/../layout-header.php');
remove_integration_function('integrate_pre_profile_areas', 'lm2AddProfileAreas');
remove_integration_function('integrate_load_permissions', 'lm2AddPermissions');
remove_integration_function('integrate_actions', 'lm2AddActions');
remove_integration_function('integrate_menu_buttons', 'lm2Buttons');
remove_integration_function('integrate_pre_include', '$sourcedir/Subs-LM2.php');
remove_integration_function('integrate_display_topic', 'lm2AddTopicDetails');
remove_integration_function('integrate_load_theme', 'loadThemeData');

?>