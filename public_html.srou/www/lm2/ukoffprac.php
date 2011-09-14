<HTML>
<HEAD><TITLE>Practice password hunter killer</TITLE></HEAD>
<BODY>
<TABLE>
<?php
// Seek and destroy any occurences of 'ukoffprac' in publicly posted messages.

require_once("../smf/SSI.php");
require_once("include.php");

$guestMemberGroupId = -1;
$query = lm2_query("
	SELECT subject
	, name AS boardName
	, id_msg
	, id_topic
	FROM {$db_prefix}messages
	JOIN {$db_prefix}topics USING (id_topic, id_board)
	JOIN {$db_prefix}boards USING (id_board)
	WHERE body LIKE '%ukoffprac%'
	AND CONCAT('START,', memberGroups, ',END') LIKE '%,$guestMemberGroupId,%'
	GROUP BY id_msg
	ORDER BY posterTime
	" , __FILE__, __LINE__);
while ($row = mysql_fetch_assoc($query)) {
	printf("<TR>
	<TD>%s</TD>
	<TD><A HREF='http://www.simracing.org.uk/smf/index.php?action=post;msg=%d;topic=%d;sesc=%s#postmodify'>%s</A></TD>
	</TR>\n",
		htmlentities($row['boardName'], ENT_QUOTES),
		$row['id_msg'],
		$row['id_topic'],
		$context['session_id'],
		$row['subject']);
}
mysql_free_result($query);
?>
</TABLE>
</BODY>
</HTML>