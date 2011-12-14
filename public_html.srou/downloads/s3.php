<?php
require("../www/smf/SSI.php");

// e.g. http://downloads.simracing.org.uk/s3.php/gtr2/fred.zip
// e.g. http://downloads.simracing.org.uk/s3.php/gtl/susan.zip?bucket=wibble.davegymer.com
// Old, direct form: http://awsdownloads.simracing.org.uk/gtr2/tracks/SRou_GTR2_FerretVille.exe
// New, time-based form: http://downloads.simracing.org.uk/s3.php/gtr2/tracks/SRou_GTR2_FerretVille.exe

// Need to make sure that new links have metadata: Content-Disposition: attachment; filename=SRou_GTR2_FerretVille.exe

/*

Note on the bucket policy:

The old policy allowed downloads from SRou pages, which might include anonymously accessed ones.
{
	"Version": "2008-10-17",
	"Id": "http referer policy",
	"Statement": [
		{
			"Sid": "Allow get requests referred by SRou sites",
			"Effect": "Allow",
			"Principal": "*",
			"Action": "s3:GetObject",
			"Resource": "arn:aws:s3:::awsdownloads.simracing.org.uk/*",
			"Condition": {
				"StringLike": {
					"aws:Referer": "*.simracing.org.uk/*"
				}
			}
		}
	]
}

The new policy uses active link generation to allow 24H access, and only to signed in members.
{
	"Version": "2011-09-15",
	"Id": "http referer policy",
	"Statement": [
		{
			"Sid": "Allow only via signed URLs",
			"Effect": "Allow",
			"Principal": {
				"CanonicalUser": "e1e0b397c04953533c3e36e31d7fecb7f805cba6d1577b0d4e021463f311b32a"
			},
			"Action": "s3:GetObject",
			"Resource": "arn:aws:s3:::awsdownloads.simracing.org.uk/*"
		}
	]
}

*/

if ($ID_MEMBER <= 0) {
	$me = "http://downloads.simracing.org.uk/s3.php{$_SERVER['PATH_INFO']}?{$_SERVER['QUERY_STRING']}";
	echo "<P><B>You must be logged in to download this file.</B></P>";
	ssi_login($me);
	exit;
}

$dryRun = ($_REQUEST['run'] == 'dry');
($bucket = $_REQUEST['bucket']) || ($bucket = 'awsdownloads.simracing.org.uk');
$key = $_SERVER['PATH_INFO'];
$key = urlencode($key);
$key = str_replace("%2F", "/", $key);
$accessKey = 'AKIAIDZC5AXVTT6EVF2A';
$secretKey = 'RBXzg3VXxItqMd3h9fYRoqbdkvhd0cUgIXiNWEqf';
$expires = time() + 86400;
$canonical = "GET\n\n\n$expires\n/$bucket$key";
$url = sprintf('http://%s%s?AWSAccessKeyId=%s&Signature=%s&Expires=%d',
        $bucket, $key, $accessKey,
        rawurlencode(base64_encode(hash_hmac('sha1', $canonical, $secretKey, true))),
        $expires);
header("Content-Type: text/plain");
printf('%s', $url);
$dryRun || header("Location: $url");
?>
