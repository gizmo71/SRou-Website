<?php
require_once("smf/SSI.php");

header("Content-Type: text/html");

$query = db_query("SELECT emailAddress FROM {$db_prefix}members WHERE id_member = ($ID_MEMBER)" , __FILE__, __LINE__);
($row = mysql_fetch_assoc($query)) || die("unknown member $ID_MEMBER (are you logged in?)");
$memberEmail = $row['emailAddress'];
mysql_fetch_assoc($query) && die("ambiguous member $ID_MEMBER");
mysql_free_result($query);

$query = db_query("INSERT INTO {$lm2_ukgpl_prefix}mailcheck(id_member) VALUES ($ID_MEMBER)" , __FILE__, __LINE__);
$id = db_insert_id();
mysql_free_result($query);

$subject = "Mail path check for SimRacing.org.uk - {$user_info['name']}";
if ($user_info['name'] != $user_info['username']) $subject = "$subject ({$user_info['username']})";

$body = "Please reply to this email, using the sender's address (including the identifying number).

Please do not change the subject significantly.

It does not matter what the body of the email says - but feel free to tell me a joke!";

mail($memberEmail, $subject, $body, "From: <check$id@mail.simracing.org.uk>");
?>

<p>An email has been sent to <tt><?php echo htmlentities($memberEmail, ENT_QUOTES) ?></tt>; please reply to it when it arrives.</p>
<p>If it does not arrive within a few hours, please send a <a href="<?php echo $boardurl; ?>/index.php?action=pm;sa=send;u=1">private message</a>.</p>

<p>Thanks for taking part! You can now return to <a href="<?php echo $boardurl; ?>/index.php">the forum</a></p>