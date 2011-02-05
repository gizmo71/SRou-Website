<P>W.I.P.</P>

<TABLE>
<?php
// "a" - ahead, "b" - behind
$query = db_query("SELECT eea.event AS event"
	. ", eeb.race_time_adjusted - eea.race_time_adjusted AS gap"
	. ", eea.race_pos AS a_pos, eea.member AS a_member"
	. ", eeb.race_pos AS b_pos, eeb.member AS b_member"
	. " FROM {$lm2_db_prefix}event_entries eea"
	. ", {$lm2_db_prefix}event_entries eeb"
	. " WHERE eea.event = eeb.event AND eea.race_laps = eeb.race_laps"
	. " AND eea.race_pos + 1 = eeb.race_pos"
	. " AND eea.race_time_adjusted > 0 AND eeb.race_time_adjusted > 0"
	. " ORDER BY gap"
	. " LIMIT 10"
	, __FILE__, __LINE__);
while ($row = mysql_fetch_assoc($query)) {
	echo "<TR>"
		. "<TD>{$row['event']}</TD>"
		. "<TD>{$row['a_pos']}</TD><TD>{$row['a_member']}</TD>"
		. "<TD>+{$row['gap']}</TD>"
		. "<TD>{$row['b_pos']}</TD><TD>{$row['b_member']}</TD>"
		. "</TR>\n";
}
mysql_free_result($query);

/*

*/
?>
</TABLE>
