<?php
require_once("smf/SSI.php");

header("Content-Type: text/html");

$ID_MEMBER || die("You must be logged in to call this script");

$query = db_query("INSERT INTO {$lm2_ukgpl_prefix}mailcheck(id_member) VALUES ($ID_MEMBER)" , __FILE__, __LINE__);
$id = db_insert_id();
mysql_free_result($query);

$to = "mailcheck@mail.simracing.org.uk";
$subject = "Mail check from {$user_info['username']} (id $id)";
$body = "My registered email is {$user_info['email']}\r\nMy member ID number is $ID_MEMBER";
$link = "mailto:$to?subject=" . rawurlencode($subject) . "&body=" . rawurlencode($body);

echo "<script>window.location='", json_encode($link), ";</script>",
     "If you email client has not automatically opened with an email, please send an email to <a href='mailto:",
     $link, "'>", $to, "</a> with a subject of '", $subject, "'. Thanks!";
?>
