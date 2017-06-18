<?php
// Forwarder stub
// Test with http://smf2.simracing.org.uk/lm2/index.php?action=increp&report=1809&time=0%3A16%3A41%26foo
$time = urlencode(htmlspecialchars_decode($_REQUEST['time'])); // Sodding SMF.
Header("Location: $boardurl/index.php?action=ReportIncident&report={$_REQUEST['report']}&time=$time", true, 301);
?>
