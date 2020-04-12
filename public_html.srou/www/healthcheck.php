<?php
http_response_code(503);
require_once("{$_ENV['SROU_ROOT']}/public_html.srou/www/smf/SSI.php");
if (($db = mysqli_connect($db_server, $db_user, $db_passwd, $db_name))) {
	mysqli_close($db);
	http_response_code(200);
}
?>
