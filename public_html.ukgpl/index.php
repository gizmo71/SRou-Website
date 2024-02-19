<?php
$ssi_theme = 6;
require_once(dirname(dirname(__FILE__)) . "/public_html.srou/www/smf/SSI.php");
require_once("$sourcedir/Subs-LM2.php");

$default_inc_file = "pages/about.php";
$inc_file = "pages{$_SERVER['PATH_INFO']}.php";
#FIXME: don't allow .. or absolute paths - HTTPD protects though so it's not urgent.
if (!file_exists($inc_file)) {
	$inc_file = $default_inc_file;
}
$block_title = $page_title = 'UKGPL';

ob_start();
include($inc_file);
$prodhosts = array('www.simracing.org.uk', 'www.ukgpl.com');
$envhosts = array($_SERVER['SROU_HOST_WWW'], $_SERVER['SROU_HOST_UKGPL']);
$contents = str_ireplace($prodhosts, $envhosts, ob_get_contents());
ob_end_clean();
?>

<HTML>
<HEAD>
	<TITLE><?php echo $page_title; ?></TITLE>
	<?php echo "<LINK rel='stylesheet' type='text/css' href='{$settings['theme_url']}/style.css' />"; ?>
</HEAD>
<BODY>

<?php
include_once("$boarddir/../layout-header.php");
makeSrouLayoutHeader();

$events = lm2RecentUpcoming($event);

echo "<BR/><TABLE WIDTH='100%'><TR>
	<TD VALIGN='TOP'>
	" . lm2_table_open("Navigation", "left") . "
";
include("pages/nav.php");
echo "
	" . lm2_table_close(). "
	" . lm2_table_open("Seasons", "LEFT") . list_seasons() . lm2_table_close() . "
	</TD>
	<TD VALIGN='TOP' WIDTH='100%'>";

echo lm2_table_open($block_title, 'LEFT') . $contents . lm2_table_close();
if ($inc_file == $default_inc_file) {
	echo lm2_table_open("Announcements") ;
	ssi_boardNews(48, 2, null, 150); // UKGPL Announcements board
	echo lm2_table_close();
	echo lm2_table_open("Race Announcements");
	ssi_boardNews(53, 2, null, 150); // UKGPL Race Announcements board
	echo lm2_table_close();
}
echo lm2_table_open("Staff") . "<TABLE>" . lm2Staff(true, $lm2_mods_group_ukgpl) . "</TABLE>" . lm2_table_close();
echo lm2_table_open("Former Staff") . "<TABLE>" . lm2Staff(true, 89, false) . "</TABLE>" . lm2_table_close();	

echo "</TD>
	<TD VALIGN='TOP'>" . format_event_rows($events["recent"], "Recent Events", 'left') . "
	" . format_event_rows($events["coming"], 'Forthcoming Events', 'right') . "</TD>
    </TR>";
echo "</TABLE>\n";

function list_seasons() {
	global $lm2_db_prefix;

	$s = null;
//XXX: sort by most recent event first
	$query = db_query("
		SELECT id_event_group, long_desc, series_theme
		FROM {$lm2_db_prefix}event_groups
		WHERE parent = 64
		ORDER BY sequence_c
		", __FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($query)) {
		$s = is_null($s) ? '' : "$s<BR/>";
		$s .= "<NOBR>" . lm2MakeEventGroupLink($row['id_event_group'], $row['long_desc'], $row['series_theme']) . "</NOBR>";
	}
	mysql_free_result($query);

	return $s;
}

function format_event_rows($events, $title, $align) {
	$content = lm2_table_open($title, $align);
	$sep = '';
	foreach ($events as $row) {
		$content .= "$sep$row\n";
		$sep = '<BR/>';
	}
	$content .= lm2_table_close();
	return $content;
}

unset($_SESSION['ID_THEME']); // Don't allow it to become sticky...

?>

</BODY>
</HTML>
