<?php
require("../www/smf/SSI.php");

// Old, direct form: http://awsdownloads.simracing.org.uk/gtr2/tracks/SRou_GTR2_FerretVille.exe
// New, time-based form: https://www.simracing.org.uk/downloads/s3.php/gtr2/tracks/SRou_GTR2_FerretVille.exe

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

if (!$ID_MEMBER || $ID_MEMBER <= 0) {
	$me = "https://{$_SERVER['SROU_HOST_WWW']}/downloads/s3.php{$_SERVER['PATH_INFO']}?{$_SERVER['QUERY_STRING']}";
	echo "<P><B>You must be logged in to download this file.</B></P>";
	ssi_login($me);
	exit;
}

$expires = time() + 86400;
$defaultBucket = 'awsdownloads.simracing.org.uk';
($bucket = $_REQUEST['bucket']) || ($bucket = $defaultBucket);
$bucket = rawurlencode($bucket);
$accessKey = $_SERVER['SROU_S3_ACCESS'];
$secretKey = $_SERVER['SROU_S3_SECRET'];

function encodeKey($key) {
	$key = rawurlencode($key);
	$key = str_replace("%2F", "/", $key);
	return $key;
}

switch ($_REQUEST['run']) {
case 'upload1':
	$expires = strftime("%FT%T.000Z", $expires);
	$ID_MEMBER == 1 || die("You are not the Gizmo!");
	header("Content-Type: text/html; charset=UTF-8");
	$redirect = "https://{$_SERVER['SROU_HOST_WWW']}/downloads/s3.php?run=uploaded";
	$policy = <<<EOF
{
	"expiration": "$expires",
	"conditions": [
		{ "bucket": "$bucket" },
		{ "x-amz-storage-class": "REDUCED_REDUNDANCY" },
		[ "starts-with", "\$Content-Disposition", "attachment; filename=" ],
		[ "starts-with", "\$key", "" ],
		[ "starts-with", "\$success_action_redirect", "$redirect" ]
	]
}
EOF
	;
	$policy = base64_encode($policy);
	$signature = base64_encode(hash_hmac('sha1', $policy, $secretKey, true));
?>
<script>
function setName(keyField, filename) {
	//  filename yields the full path on IE8 but only the name in FF 10
	filename = filename.replace(/\\/g, '/');
	if (filename.substr(0, 8) == 'S:/Sims/') {
		filename = filename.substr(8);
		keyField.value = filename.replace(/^(.+\/)([^\/]+)$/, function filenameRegularizer(matched, p1, p2) {
			return p1.toLowerCase() + '${filename}';
		});
	} else {
		alert('Please select the key from the drop down');
		keyField.focus();
	}
}
</script>
	<form action="http://<?php echo $bucket; ?>" method="post" enctype="multipart/form-data">
	Key <input type="text" size="100" name="key" value="gtr2/tracks/${filename}" />
	<SELECT NAME="x-ignore-nameshortcut" onChange="form.key.value = options[selectedIndex].value + '/${filename}'">
		<optgroup label="GTR2">
			<OPTION VALUE="gtr2/tracks">Tracks</OPTION>
			<OPTION VALUE="gtr2/cars">Cars</OPTION>
			<OPTION VALUE="gtr2/cars/templates">Car Templates</OPTION>
		</optgroup>
		<optgroup label="GTL">
			<OPTION VALUE="gtl/tracks">Tracks</OPTION>
			<OPTION VALUE="gtl/cars">Cars</OPTION>
		</optgroup>
	</SELECT><br/>
	<input type="hidden" name="AWSAccessKeyId" value="<?php echo $accessKey; ?>" />
	<input type="hidden" name="Content-Disposition" value="attachment; filename=&quot;${filename}&quot;">
	<input type="hidden" name="success_action_redirect" value="<?php echo htmlentities($redirect, ENT_QUOTES); ?>" />
	<input type="hidden" name="signature" value="<?php echo $signature; ?>" />
	<input type="hidden" name="x-amz-storage-class" value="REDUCED_REDUNDANCY" />
	<input type="hidden" name="policy" value="<?php echo htmlentities($policy, ENT_QUOTES); ?>" />
	File: <input type="file" name="file" onChange="setName(form.key, value)"><br />
	<input type="submit" />
</form>
<?php
	break;
case 'uploaded':
	header("Content-Type: text/plain");
	printf("https://{$_SERVER['SROU_HOST_WWW']}/downloads/s3.php/%s%s", encodeKey($_REQUEST['key']), $bucket != $defaultBucket ? '?bucket=' . $bucket : '');
	break;
case 'dry':
	$dryRun = true;
default:
	$dryRun = isset($dryRun) ? $dryRun : false;
	$key = encodeKey($_SERVER['PATH_INFO']);
	$canonical = "GET\n\n\n$expires\n/$bucket$key";
	$url = sprintf('http://%s%s?AWSAccessKeyId=%s&Signature=%s&Expires=%d',
	        $bucket, $key, $accessKey,
	        rawurlencode(base64_encode(hash_hmac('sha1', $canonical, $secretKey, true))),
	        $expires);
	header("Content-Type: text/plain");
	printf('%s', $url);
	$dryRun || header("Location: $url");
}
?>
