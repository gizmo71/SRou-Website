<?php
require_once("smf/SSI.php");

$query = db_query("INSERT INTO {$lm2_ukgpl_prefix}mailcheck(id_member) VALUES ($ID_MEMBER)" , __FILE__, __LINE__);
$id = db_insert_id();
mysql_free_result($query);

$subject = rawurlencode("Mail check from {$user_info['username']} (id $id)");
$body = rawurlencode("My registered email is {$user_info['email']}\r\nMy member ID number is $ID_MEMBER");
header("Location: mailto:mailcheck@mail.simracing.org.uk?subject=$subject&body=$body");

/*
echo "<br/><pre>";
print_r($user_info);
echo "</pre>";
*/
?>
