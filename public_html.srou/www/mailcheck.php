<?php
require_once("smf/SSI.php");

header("Content-Type: text/html");

if ($user_info['is_guest']) {
	echo "<P><B>You must be logged in to run this script.</B></P>";
	ssi_login("https://{$_SERVER['SROU_HOST_WWW']}/makilcheck.php");
	exit;
}

$query = db_query("INSERT INTO {$lm2_ukgpl_prefix}mailcheck(id_member) VALUES ({$user_info['id']})" , __FILE__, __LINE__);
$id = db_insert_id();
mysql_free_result($query);

$to = "mailcheck@mail.simracing.org.uk";
$subject = "Mail check from {$user_info['username']} (id $id)";
$body = "My registered email is {$user_info['email']}\r\nMy member ID number is {$user_info['id']}";
$link = "mailto:$to?subject=" . rawurlencode($subject) . "&body=" . rawurlencode($body);

echo "<script>window.location='", json_encode($link), ";</script>",
     "If you email client has not automatically opened with an email, please send an email to <a href='mailto:",
     $link, "'>", $to, "</a> with a subject of '", $subject, "'. Thanks!";
?>
