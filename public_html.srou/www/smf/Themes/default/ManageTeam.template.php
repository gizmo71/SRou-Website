<?php

function template_main() {
	fatal_error("Manage team subaction with no template; " . print_r($_REQUEST, true));
}

function template_entry() {
	global $context, $scripturl;

	show_messages($context['ManageTeam']['messages']);

	echo "<PRE>", htmlentities(print_r($context['ManageTeam'], true), ENT_QUOTES), "</PRE>";
}

?>
