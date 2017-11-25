<!--
Called using a cron job along the lines of:
*/15 * * * * cd /tmp && wget --header="Host: www.simracing.org.uk" -O - http://127.0.0.1/batch/updateDrivers.php >rebuildDriverCache.log 2>&1
-->

<?php
require('../smf/SSI.php');
require("$boarddir/../lm2/include.php");

header('Content-Type: text/plain');

rebuild_driver_cache();
?>

Done
