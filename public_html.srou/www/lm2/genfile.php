<?php
preg_match('@^http://members.iracing.com/@', $_SERVER['HTTP_REFERER']) || die("bad referer {$_SERVER['HTTP_REFERER']}");
header('Content-Type: text/csv');
header("Content-Disposition: attachment; filename=\"{$_REQUEST['filename']}\"");
echo stripslashes($_REQUEST['csv']);
?>