<?php
require_once("$sourcedir/Subs-Members.php");

if ($membersToDelete = $_REQUEST['memberToDelete']) {
	deleteMembers($membersToDelete);
	echo "<B>Selected members deleted</B>\n";
}
?>
<FORM METHOD="POST">
<INPUT TYPE="BUTTON" VALUE="Delete selected users" onClick="if (confirm('Delete users?')) form.submit();" />
<TABLE>
<TR>
	<TH></TH>
	<TH TITLE="Signature">Member</TH>
	<TH>Website</TH>
	<TH>Activated?</TH>
	<TH>Registered</TH>
	<TH>Last Login</TH>
	<TH>Posts</TH>
	<TH>Events</TH>
</TR>
<?php
$query = lm2_query("
	SELECT id_member AS id
	, websiteTitle, websiteUrl, signature
	, CONCAT(realName, IF(memberName <> realName, CONCAT(' (', memberName, ')'), '')) AS name
	, is_activated
	, IF(posts = 0, NULL, posts) AS posts
	, (SELECT IF(COUNT(*) = 0, NULL, COUNT(*)) FROM {$lm2_db_prefix}event_entries WHERE member = id_member) AS events
	, FROM_UNIXTIME(dateRegistered) AS dateRegistered
	, IF(lastLogin = 0, NULL, FROM_UNIXTIME(lastLogin)) AS lastLogin
	FROM {$db_prefix}members
	WHERE ((is_activated = 0 OR lastLogin = 0) AND FROM_UNIXTIME(dateRegistered) < DATE_SUB(" . php2timestamp(time()) . ", INTERVAL 2 MONTH))
	   OR (lastLogin > 0 AND FROM_UNIXTIME(lastLogin) < DATE_SUB(" . php2timestamp(time()) . ", INTERVAL 12 MONTH) AND posts = 0)
	   OR (posts = 0 AND (websiteUrl <> '' OR signature <> ''))
	HAVING events IS NULL
	ORDER BY IFNULL(posts, 0) > 0 OR IFNULL(events, 0) > 0
	, IF(lastLogin > dateRegistered, lastLogin, dateRegistered)
	", __FILE__, __LINE__);
while ($row = mysql_fetch_assoc($query)) {
	echo "<TR>
		<TD><INPUT TYPE='CHECKBOX' NAME='memberToDelete[]' VALUE='{$row['id']}'" . (is_null($row['posts']) && is_null($row['events']) ? " CHECKED" : "") . " /></TD>
		<TD TITLE=\"{$row['signature']}\"><A HREF=\"$boardurl/index.php?action=profile;u={$row['id']}\">{$row['name']}</A></TD>
		<TD><SMALL>" . ($row['websiteUrl'] ? "<A HREF=\"{$row['websiteUrl']}\">{$row['websiteTitle']}</A>" : $row['websiteTitle']) . "</SMALL></TD>
		<TD ALIGN=CENTER>{$row['is_activated']}</TD>
		<TD>{$row['dateRegistered']}</TD>
		<TD>{$row['lastLogin']}</TD>
		<TD ALIGN=RIGHT>{$row['posts']}</TD>
		<TD ALIGN=RIGHT>{$row['events']}</TD>
		</TR>\n";
}
mysql_free_result($query);
?>
</TABLE>
</FORM>