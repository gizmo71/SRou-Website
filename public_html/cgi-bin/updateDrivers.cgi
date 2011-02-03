#!/usr/bin/php
<?php
require('../../public_html.srou/www/smf/SSI.php');
require('../../public_html.srou/www/lm2/include.php');

header('Content-Type: text/plain');

rebuild_driver_cache();
echo "Done\n";
?>