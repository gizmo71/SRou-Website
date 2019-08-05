<?php
header('Content-Type: text/plain');

require('../smf/SSI.php');
require("$boarddir/../lm2/include.php");

rebuild_driver_cache();

// Called using a cron job along the lines of:
// */15 * * * * cd /tmp && wget --header="Host: www.simracing.org.uk" -O - http://127.0.0.1/batch/updateDrivers.php >rebuildDriverCache.log 2>&1
?>

Done
