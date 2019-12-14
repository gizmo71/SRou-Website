<?php
require("../www/smf/SSI.php");
header('Content-Type: text/html; charset=utf-8');
?>
<HTML>
 <HEAD>
  <TITLE>SimRacing.org.uk Server Replays</TITLE>
 </HEAD>
<BODY>

<H1>Server Replays</H1>

<?php

if ($user_info['is_guest']) {
	echo "<P><B>You must be logged in to download or examine replays.</B></P>\n";
}

$total_size = 0.0;

$explode = $_REQUEST['explode'];

$ignores = array(
	1 => "index.html",
	2 => "error_log",
	3 => ".ftpquota",
	4 => ".gitignore",
	-2 => ".",
	-1 => ".."
);

function cmpFiles($a, $b) {
	return strcasecmp($a['filename'], $b['filename']);
}

foreach (array(
	//array('dir'=>'ukgtr', 'url'=>'7', 'title'=>'UKGTR'),
	array('dir'=>'ukgtl', 'url'=>'13', 'title'=>'UKGTL'),
	//array('dir'=>'ukrf', 'url'=>'16', 'title'=>'UKrF'),
	//array('dir'=>'ukpng', 'url'=>'213', 'title'=>'UKP&amp;G'),
	array('dir'=>'ukac', 'url'=>'484', 'title'=>'UKAC'),
	array('dir'=>'rre', 'url'=>'498', 'title'=>'RRE'),
	//array(dir=>'race', 'url'=>'63', 'title'=>"Race'07"),
) as $dirA) {
	$dir = $dirA['dir'];
	echo "<H2><A id='$dir' HREF=\"http://{$_SERVER['SROU_HOST_WWW']}/index.php?ind=lm2&group={$dirA['url']}\">{$dirA['title']}</A></H2>"
?>

<TABLE BORDER=1>
  <TR>
    <TH ALIGN=LEFT>Name</TH>
    <TH ALIGN=RIGHT>Size (MB)</TH>
    <TH ALIGN=RIGHT>Timestamp</TH>
  </TR>
<?php

$dh = opendir($dir);
//array_unshift($names, "..");
$list = array();
while ($filename = readdir($dh)) {
	if (array_search($filename, $ignores)) {
		continue;
	}
	$fullname = "$dir/$filename";
	$file = array(
		'displayname'=>htmlentities($filename, ENT_QUOTES),
		'filename'=>$fullname,
		'timestamp'=>filectime($fullname),
		'date'=>strftime("%Y/%m/%d %H:%M:%S", filectime($fullname)),
		'url'=>rawurlencode($filename),
	);
	$file['displayName'] = $file['displayname'];
	if (!is_dir($filename)) {
		$total_size += ($file['size'] = filesize($fullname) / 1000000.0);
		if (substr($file['filename'], -4, 4) == '.zip' && !$user_info['is_guest']) {
			$file['explode'] = "index.php?explode=$dir/{$file['url']}#exploded";
		}
	} else {
		$file['size'] = "&nbsp;";
		$file['displayName'] .= "/";
	}
	array_push($list, $file);
}
closedir($dh);

usort($list, "cmpFiles");

foreach ($list AS $file) {
	$filename = $file['displayName'];
	if (!$user_info['is_guest']) {
		$filename = "<A HREF='$dir/{$file['url']}'>$filename</A>";
	}
	$isExploded = $explode == $file['filename'];
	echo "  <TR", $isExploded ? " id='exploded'" : "", ">\n",
		"    <TD ALIGN=LEFT><TT>$filename</TT></TD>\n",
		"    <TD ALIGN=RIGHT>{$file['size']}</TD>\n",
		"    <TD ALIGN=RIGHT>{$file['date']}</TD>\n",
//TODO: how do we make this work in the split HTTPD/FPM-PHP world? Rewrite in pure PHP?
//		"    <TD ALIGN=RIGHT>", $file['explode'] ?
//			"<A HREF=\"{$file['explode']}\">Examine contents</A>" : "", "</TD>\n",
		"  </TR>\n";
	$explodeUrl = "https://{$_SERVER['SROU_HOST_REPLAY']}/cgi-bin/explode.cgi?zip=$dir/{$file['url']}";
	if ($isExploded && ($ph = fopen($explodeUrl, "r"))) {
		$sep = "";
		while ($read = fgets($ph)) {
			$text .= "$sep<TT>" . str_replace(' ', '&nbsp;', htmlentities($read, ENT_QUOTES)) . "</TT>";
			if (preg_match('/^\s*(\d+)\s+\S+\s+\d+\s+\d%\s+\d\d-\d\d-\d{4}\s+\d\d:\d\d\s+[a-f0-9]{8}\s+(\S.*.(?:txt|xml|vcr))\s*$/i', $read, $matches)) {
				$name = rawurlencode(htmlentities($matches[2], ENT_QUOTES));
				if (($size = $matches[1]) < 250000 && preg_match('/\.(?:txt|xml)$/i', $name, $dummy)) {
					$text .= " (<A HREF=\"$explodeUrl&name=$name\">*</A>)";
				} else if (preg_match('/\.vcr$/i', $name, $dummy)) {
					$text .= " (<A HREF=\"$explodeUrl&vcr=$name\">#</A>)";
				}
			}
			$sep = "<BR/>";
		}
		echo "<TR><TD COLSPAN='4'>$text</TD></TR>\n";
		fclose($ph);
	}
}
?>
</TABLE>

<?php
}
?>

<P>Disk disk used (MB): <?php echo $total_size; ?></P>

</BODY>
</HTML>
