<?php
//require_once("../smf/SSI.php");
//require_once("include.php");

class gauge {
	var $img, $center = 40, $min = -350, $max = 100, $pos;
	var $needleColor;

	function gauge($pos) {
		// Prepare image
		$this->img = imagecreate($this->center * 2, $this->center + 3);
		imagecolortransparent($this->img, imagecolorallocate($this->img, 0, 0, 0));

		if ($pos > $this->max) {
			$this->needleColor = imagecolorallocate($this->img, 0, 255, 0);
		} else if ($pos < $this->min) {
			$this->needleColor = imagecolorallocate($this->img, 255, 120, 120);
		} else {
			$this->needleColor = imagecolorallocate($this->img, 255, 255, 0);
		}

		$this->pos = max(min($pos, $this->max), $this->min);

		$minDegrees = 160;
		$maxDegrees = 20;
		$centreDegrees = 180 - ($this->min / ($this->max - $this->min)) * 180;

		// Draw Gauge
		imagefilledarc($this->img, $this->center, $this->center, $this->center * 2, $this->center * 2, $minDegrees, $centreDegrees,
			imagecolorallocate($this->img, 180, 0, 0), IMG_ARC_PIE);
		imagefilledarc($this->img, $this->center, $this->center, $this->center * 2, $this->center * 2, $centreDegrees, $maxDegrees,
			imagecolorallocate($this->img, 0, 180, 0), IMG_ARC_PIE);
	}

	function plot() {
		// Wait for plot to avoid drawing more than one legend or hand
		$long = $this->center * 0.92;
		$degrees = (($this->pos - $this->min) * 180 / ($this->max - $this->min)) + 180;
		$y = $long * sin(deg2rad($degrees));
		$x = $long * cos(deg2rad($degrees));
		imagesetthickness($this->img, 3);
		imageline($this->img, $this->center, $this->center, $this->center + $x, $this->center + $y, $this->needleColor);
		imagestring($this->img, 4, 24, 25, sprintf("UK%+4d", $this->pos), imagecolorallocate($this->img, 255, 255, 255));

		header("Content-type: image/png");
		imagepng($this->img);
		imagedestroy($this->img);
	}
}

//$query = lm2_query("SELECT SUM(amount) AS total FROM {$lm2_db_prefix}money", __FILE__, __LINE__);
//($row = mysql_fetch_assoc($query)) || die("sim $sim not found");
//$pos = "{$row['total']};";
//mysql_fetch_assoc($query) && die("sim $sim found more than once!");
//mysql_free_result($query);
$pos = $_REQUEST['balance'];

$gauge = new gauge(intval($pos, 10));
$gauge->plot();
?>