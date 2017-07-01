<?php
global $context;
$context['page_title'] = 'SimRacing.org.uk';
$ssi_layers = array('html', 'body');
global $options;
$options['collapse_header'] = 1;
require_once("smf/SSI.php");
require_once("$sourcedir/Subs-LM2.php");

/* Replacement front page, post-MkPortal. */

if (lm2ArrayValue($_REQUEST, 'ind') == 'lm2') {
	if ($driver = lm2ArrayValue($_REQUEST, 'driver')) {
		if ($driver == 'self' || !is_numeric($driver)) {
			$driver = $user_info['user_id'];
		}
		$u = $driver;

		$query = $smcFunc['db_query'](null, "
			SELECT driver_name AS realName, member_name AS memberName
			FROM {lm2_prefix}drivers
			LEFT JOIN {db_prefix}members ON id_member = driver_member
			WHERE driver_member = {int:driver}
		", array('driver'=>$driver));
		($row = $smcFunc['db_fetch_assoc']($query)) || die("unknown driver $driver");
		if (is_null($row['memberName'])) {
			$u = $lm2_guest_member_id;
		}
		$smcFunc['db_free_result']($query);

		header("Location: $boardurl/index.php?action=profile&u=$u&sa=racing_history&driver=$driver");
		exit;
	} else if (($team = lm2ArrayValue($_REQUEST, 'team')) && is_numeric($team)) {
		header("Location: $boardurl/index.php?action=LM2R&team=$team");
		exit;
	} else if ($teams = lm2ArrayValue($_REQUEST, 'teams')) {
		header("Location: $boardurl/index.php?action=LM2R&team=*");
		exit;
	} else if (($group = lm2ArrayValue($_REQUEST, 'group')) && is_numeric($group)) {
		header("Location: $boardurl/index.php?action=LM2R&group=$group");
		exit;
	} else if (($event = lm2ArrayValue($_REQUEST, 'event'))) {
		is_numeric($event) || die("non-numeric event $event");
		$query = db_query("SELECT event_group, smf_topic FROM {$lm2_db_prefix}events WHERE id_event = $event", __FILE__, __LINE__);
		$row = mysql_fetch_assoc($query);
		mysql_free_result($query);
		if ($row && ($smf = $row['smf_topic'])) {
			header("Location: $boardurl/index.php?topic=$smf");
			exit;
		}
		//$page_title = "Unknown or published event $event";
		unset($_REQUEST['event']); // Stop the results block dying.
	} else if (($circuit = lm2ArrayValue($_REQUEST, 'circuit')) || ($location = lm2ArrayValue($_REQUEST, 'location'))) {
		if (!is_numeric($circuit) && !is_numeric($location)) {
			header("Location: $boardurl/index.php?action=LM2R&circuit=*");
			exit;
		} else {
			if (!$location) {
				$query = $smcFunc['db_query'](null, "
					SELECT circuit_location AS location FROM {$lm2_db_prefix}circuits WHERE id_circuit = {int:circuit}
					", array('circuit'=>$circuit));
				($row = $smcFunc['db_fetch_assoc']($query)) || die("unknown circuit $circuit");
				$location = $row['location'];
				$smcFunc['db_fetch_assoc']($query) && die("ambiguous circuit $circuit");
				$smcFunc['db_free_result']($query);
			} else {
				$circuit = 0; // Not trying to bring a particular layout to the top.
			}
			header("Location: $boardurl/index.php?action=LM2R&location=$location&circuit=$circuit");
			exit;
		}
	}
}

$events = lm2RecentUpcoming(null);

echo "<br/><TABLE WIDTH='100%'><TR>
	<TD VALIGN='TOP'>" . format_event_rows($events["recent"], "Recent Events", 'left') . "</TD>
	<TD VALIGN='TOP'>" . lm2_table_open("Welcome to SRou!") . "
SimRacing.org.uk is an umbrella organisation formed to expand UKGTR's horizons to encompass GT Legends and beyond.
SimRacing.org.uk was founded by Dave Gymer, founder of UKGTR and long time technical administrator of UKGPL.
<BR/><BR/>
We currently have active leagues for GTR2, Power & Glory 2 and GT Legends.
<BR/><BR/>
Please visit our <A HREF='$boardurl/index.php?board=40'>Start Here! board</A> if you would like to participate, or just want to know more about the league.
<BR/><BR/>
Existing members may wish to jump directly to the list of <A HREF='$boardurl/index.php?action=unread;all'>all unread posts</A>.
" . lm2_table_close() . "</TD>
	<TD VALIGN='TOP'>" . format_event_rows($events["coming"], 'Forthcoming Events', 'right') . "</TD>
</TR></TABLE>\n";

echo lm2_table_open("Announcements");
ssi_boardNews(2, 2, null, 150);
echo lm2_table_close();

echo lm2_table_open("UKGTL Announcements");
ssi_boardNews(79, 2, null, 150);
echo lm2_table_close();

echo lm2_table_open("UK Assetto Corsa Announcements");
ssi_boardNews(98, 2, null, 150);
echo lm2_table_close();

echo lm2_table_open("UK Race Room Experience Announcements");
ssi_boardNews(101, 2, null, 150);
echo lm2_table_close();

echo lm2_table_open("Recent Event Moderation Reports");
ssi_boardNews(39, 2, null, 150);
echo lm2_table_close();

echo lm2_table_open("Staff") . "<TABLE>" . lm2Staff(true, $lm2_mods_group) . "</TABLE>" . lm2_table_close();
echo lm2_table_open("Incident Moderators") . "<TABLE>" . lm2Staff(true, $lm2_mods_group_court, false) . "</TABLE>" . lm2_table_close();
echo lm2_table_open("Former Staff") . "<TABLE>" . lm2Staff(true, 50, false) . "</TABLE>" . lm2_table_close();

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

?>

</BODY></HTML>
