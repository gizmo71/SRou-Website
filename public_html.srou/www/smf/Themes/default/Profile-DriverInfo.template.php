<?php

function template_lm2ProfileDriverInfo() {
	global $context;
	global $smcFunc;

	show_messages($context['messages']);

	echo lm2_table_open("Driver Details");

	echo "<FORM METHOD='POST'><TABLE>
		<TR><TD>Country:</TD><TD><SELECT NAME='iso3166_code'>";
	foreach ($context['lm2']['iso3166_codes'] as $id => $desc) {
		$sel = "";
		if ($context['lm2']['iso3166_code'] == $id) {
			$sel = " SELECTED";
		}
		echo "<OPTION VALUE='$id'$sel>$desc</OPTION>\n";
	}
	echo "</SELECT></TD></TR>\n";

	foreach ($context['lm2']['names'] as $row) {
		echo "<TR><TD COLSPAN='3'><B><U>", $row['sim_name'], "</U></B></TD></TR>
			<TR><TD>Driving Name:</TD>
				<TD><INPUT TYPE='EDIT' NAME='sim_name[", $row['id_sim'], "]' VALUE='",
				htmlentities($row['driving_name'], ENT_QUOTES), "' SIZE='30' MAXLENGTH='32'></TD>
				<TD ALIGN='LEFT'><I>This is <B>CASE SENSITIVE</B> and should be at least 2 characters</I></TD></TR>\n";
	}

	echo "<TR><TD COLSPAN='3'><B><U>Grand Prix Legends</U></B></TD></TR>
		<TR><TD><A HREF='http://gplrank.schuerkamp.de/'>GPLRank</A>:</TD>
			<TD><INPUT TYPE='EDIT' NAME='gplrank'
				VALUE='", $context['lm2']['gplrank'], "' SIZE='8' MAXLENGTH='8'></TD>
			<TD ALIGN='LEFT'><I>eg. +123.456, or -13.666</I></TD></TR>
		<TR><TD>Historic identity:</TD><TD COLSPAN='2'><SELECT NAME='ukgpl_driver'>
				<OPTION VALUE='none'></OPTION>\n";
	foreach ($context['lm2']['historic_drivers'] as $id => $row) {
		echo "<OPTION VALUE='$id'", $row['selected'] ? " SELECTED" : "", ">", $row['name'], "</OPTION>\n";
	}
	echo "</SELECT></TD></TR>\n";

	echo "<TR><TD COLSPAN='3' ALIGN='RIGHT'><INPUT TYPE='SUBMIT' NAME='saveDriverInfo' VALUE='Update Details'></TD></TR>
		</TABLE></FORM>\n";

	echo lm2_table_close();
}

?>
