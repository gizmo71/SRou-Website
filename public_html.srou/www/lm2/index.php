<?php
require("../smf/SSI.php");
header("Content-Type: text/html; charset={$context['character_set']}");
?>
<HTML>
  <head>
  <style>
.navbar { background: #dddddd; border: solid 1px }
  </style>
  <link rel="stylesheet" type="text/css" href="/smf/Themes/default/css/lm2.css?time=<?php echo rand(); ?>"/>
<?php
require_once('include.php');

$actions = array(
	'refdata'=>array('groups'=>array($lm2_mods_group, $lm2_mods_group_ukgpl, $lm2_mods_group_server),
		'title'=>'Reference Data',      'page'=>'refdata'),
	'import' =>array('groups'=>array($lm2_mods_group_server, $lm2_mods_group_ukgpl),
		'title'=>'Import Results',      'page'=>'import'),
	'stands' =>array('groups'=>array($lm2_mods_group_court, $lm2_mods_group_server, $lm2_mods_group_ukgpl),
		'title'=>'Generate Standings',  'page'=>'stands'),
	'regression'=>array('groups'=>array(1),
		'title'=>'<SMALL TITLE="Don\'t press this!">[N/R]</SMALL>', 'page'=>'regression'),
	'court'  =>array('groups'=>array($lm2_mods_group, $lm2_mods_group_court, $lm2_mods_group_ukgpl),
		'title'=>'Court',               'page'=>'incidents'),
	'teams'  =>array('groups'=>array(),
		'title'=>'Team Management',     'page'=>'teams'),
	'reghelp'=>array('groups'=>array($lm2_mods_group, $lm2_mods_group_ukgpl),
		'title'=>'Registration Helper', 'page'=>'reghelp'),
	'uprune' =>array('groups'=>array(1),
		'title'=>'Prune Users',         'page'=>'prune'),
	'increp' =>array('groups'=>array(),
		'page'=>'increp'),
	'migration'=>array('groups'=>array(1, $lm2_mods_group_ukgpl),
		'title'=>'Migration',   	   'page'=>'migration'),
);

mysqli_query($db_connection, "ROLLBACK"); // Just in case somebody left one open...
?>
  <TITLE>LM2i</TITLE>
</HEAD>
<BODY>

<?php
if (!$user_info['is_guest']) {
	echo "<TABLE CLASS=\"navbar\"><TR>\n";
	$action = $_REQUEST['action'];
	foreach ($actions AS $actionId => $actionInfo) {
		if (count($actionInfo['groups']) == 0 || count(array_intersect($actionInfo['groups'], $user_info['groups'])) > 0) {
			$html = $actionInfo['title'];
			if ($action == $actionId) {
				$html = "<B>$html</B>";
				$content_page = $actionInfo['page'];
			}
			if ($actionInfo['title']) {
				$html = '<A HREF="index.php?action=' . $actionId . '">' . $html . '</A>';
				echo "<TD>$html</TD>\n";
			}
		}
//else echo "<TD><SMALL><I>" . $actionInfo['title'] . "</I></SMALL></TD>\n";
	}
	echo "</TR></TABLE>\n";

	if (!is_null($content_page)) {
		mysqli_query($db_connection, "BEGIN"); // Turns off auto-commit as a side effect.
		include("$content_page.php");
		mysqli_query($db_connection, "COMMIT");
	} else {
		//echo "<H1>Welcome to League Manager 2 interim.</H1>\n";
		echo "<P>Please selection an action from the navigation bar.</P>\n";
	}
}

// Shared stuff.

function own_url() {
	return "index.php?action=" . $_REQUEST['action'];
}

?>

<TABLE CLASS="navbar"><TR><TD><I><?php
if ($user_info['is_guest']) {
    ssi_login($_SERVER['PHP_SELF']);
} else {
    #echo "<P>DB prefix is $db_prefix, connection is $db_connection</P>\n";
    ssi_logout($_SERVER['PHP_SELF']);
}
?></I></TD>
<TD><A HREF="index.php"><I>LM2</I></A></TD>
<TD><A HREF="/"><I>SimRacing.org.uk</I></A></TD>
</TR></TABLE>

</BODY>
</HTML>
