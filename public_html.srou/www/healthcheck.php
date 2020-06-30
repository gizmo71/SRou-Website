<?php
// wget -S -O - --header 'Host: check.haproxy.davegymer.org' http://web/healthcheck
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
http_response_code(500);
//ob_end_flush();
require_once("/srv/public_html.srou/www/smf/SSI.php");
http_response_code(503);
if (($db = mysqli_connect($db_server, $db_user, $db_passwd, $db_name))) {
	mysqli_close($db);
	http_response_code(200);
}
?>
