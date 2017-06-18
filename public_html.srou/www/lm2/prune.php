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
	<TH>Sent</TH>
	<TH>Recv</TH>
</TR>
<?php
$query = $smcFunc['db_query'](null, "
	SELECT id_member AS id
	, website_title AS websiteTitle, website_url AS websiteUrl, signature
	, CONCAT(real_name, IF(member_name <> real_name, CONCAT(' (', member_name, ')'), '')) AS name
	, is_activated
	, IF(posts = 0, NULL, posts) AS posts
	, (SELECT IF(COUNT(*) = 0, NULL, COUNT(*)) FROM {$lm2_db_prefix}event_entries WHERE member = id_member) AS events
	, FROM_UNIXTIME(date_registered) AS dateRegistered
	, IF(last_login = 0, NULL, FROM_UNIXTIME(last_login)) AS lastLogin
	, (SELECT IF(COUNT(*) = 0, NULL, COUNT(*)) FROM {db_prefix}personal_messages WHERE id_member_from = id_member) AS pm_sent
	, (SELECT IF(COUNT(*) = 0, NULL, COUNT(*)) FROM {db_prefix}pm_recipients WHERE {db_prefix}members.id_member = {db_prefix}pm_recipients.id_member) AS pm_recv
	FROM {db_prefix}members
	WHERE ((is_activated = 0 OR last_login = 0) AND FROM_UNIXTIME(date_registered) < DATE_SUB(" . php2timestamp(time()) . ", INTERVAL 2 MONTH))
	   OR (last_login > 0 AND FROM_UNIXTIME(last_login) < DATE_SUB(" . php2timestamp(time()) . ", INTERVAL 12 MONTH) AND posts = 0)
	   OR (posts = 0 AND (website_url <> '' OR signature <> ''))
	HAVING events IS NULL
	ORDER BY IFNULL(posts, 0) + IFNULL(events, 0) + IFNULL(pm_sent, 0) + IFNULL(pm_recv, 0) > 0
	, IF(last_login > date_registered, last_login, date_registered)
	", array());
while ($row = $smcFunc['db_fetch_assoc']($query)) {
	$allNull = is_null($row['posts']) && is_null($row['events']) && is_null($row['pm_sent']) && is_null($row['pm_recv']);
	echo "<TR>
		<TD><INPUT TYPE='CHECKBOX' NAME='memberToDelete[]' VALUE='{$row['id']}'" . ($allNull ? " CHECKED" : "") . " /></TD>
		<TD TITLE=\"{$row['signature']}\"><A HREF=\"$boardurl/index.php?action=profile;u={$row['id']}\">{$row['name']}</A></TD>
		<TD><SMALL>" . ($row['websiteUrl'] ? "<A HREF=\"{$row['websiteUrl']}\">{$row['websiteTitle']}</A>" : $row['websiteTitle']) . "</SMALL></TD>
		<TD ALIGN=CENTER>{$row['is_activated']}</TD>
		<TD>{$row['dateRegistered']}</TD>
		<TD>{$row['lastLogin']}</TD>
		<TD ALIGN=RIGHT>{$row['posts']}</TD>
		<TD ALIGN=RIGHT>{$row['events']}</TD>
		<TD ALIGN=RIGHT>{$row['pm_sent']}</TD>
		<TD ALIGN=RIGHT>{$row['pm_recv']}</TD>
		</TR>\n";
}
$smcFunc['db_free_result']($query);
?>
</TABLE>
</FORM>
