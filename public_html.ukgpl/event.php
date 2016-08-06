<HTML>
<HEAD>
</HEAD>
<FRAMESET COLS="14%,*">
  <FRAME SRC="ukgpl/nav.html" NAME="mainnav">
<?php
if (!($season = $_GET['s'])) die('no season');
if (!($division = $_GET['d'])) die('no division');
if (!($event = $_GET['e'])) die('no event');
?>
  <FRAME SRC="ukgpl/seasons/<?php print $season; ?>/main.php?mainseas=<?php print "${division}/event${event}.html"; ?>" NAME="main">
</FRAMESET>
</HTML>
