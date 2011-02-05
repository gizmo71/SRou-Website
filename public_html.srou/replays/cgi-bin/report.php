<?php

function readUIntLE($gz, $len, $max = 0xffffffff) {
	$n = 0;
	$shift = 0;
	while ($len-- > 0) {
		(($c = gzgetc($gz)) !== false) || die("ran out of data");
		$n |= ord($c) << $shift;
		$shift += 8;
	}
	($n <= $max) || die("$n > $max");
	return $n;
}

function hexDump($s) {
	$buf = sprintf("[%d]", strlen($s));
    for ($i = 0; $i < strlen($s); ++$i) {
    	$buf .= sprintf(" %02x", ord(substr($s, $i, $i + 1)));
    }
    return $buf;
}

function slotInfo($gz) {
	$buf = "";

	$expected4old = "//[[";
	$expected4new = "IRSR";
	$magic = gzread($gz, 4);
	if ($magic == $expected4old) {
		$expected = $magic . "gMb1.000f (c)2004    ]] [[\0\0\0\0\0\0\0\0\004\0\0\0]]\n$expected4new";
		$magic .= gzread($gz, strlen($expected) - strlen($magic));
	} else {
		$expected = $magic;
	}
	// plus ff\206? for GTR2, \303\365\210? for Race '07
	($magic == $expected) || die ("unexpected magic " . urlencode($magic));

	switch ($type = readUIntLE($gz, 4)) {
	case 0x3f866666: // GTR2
		$stuffLen = 9;
		$carSize = 132; // 0x84
		break;
	case 0x3f8147ae: // GTL
		$stuffLen = 4;
		$carSize = 68; // 0x44
		break;
	case 0x3F88F5C3: // Race '07
		$stuffLen = 9;
		$carSize = 0x144;//208; // 0xD0
		break;
	default:
		die("unknown 'type code' $type");
	}
	$buf .= sprintf("?\t%08x\n", $type)
		. gzread($gz, readUIntLE($gz, 4, 100)) . " / " . gzread($gz, readUIntLE($gz, 4, 100)); // Track details.

	$buf .= sprintf("\n0x600 = %03x", readUIntLE($gz, 2));
	$buf .= "\n? " . hexDump(gzread($gz, $stuffLen));

	$slots = readUIntLE($gz, 4, 105); // They start at 0 and the safety car is 104, at least in GTR2 and GTL.
	$buf .= "\nSlotCount $slots";

	while ($slots-- > 0) {
		$slot = ord(gzgetc($gz));
		$buf .= sprintf("\nSLOT\t%d\t-------------------------------------------------", $slot);

		$driverLen = ord(gzgetc($gz));
		$driver = gzread($gz, $driverLen);
		if ($type == 0x3f866666) {
			$driver2Len = ord(gzgetc($gz));
			$driver2 = gzread($gz, $driver2Len);
		} else {
			$driver2 = "n/a";
		}
		$buf .= sprintf("\nDRIVERS\t%s [%d] / %s [%d]"
			, $driver, $driverLen
			, $driver2, $driver2Len);

		if ($type != 0x3f866666) {
			if (($carExtra = ord(gzgetc($gz))) != 0) {
				$buf .= sprintf("\nPRE-CAR\t\"%s\" [%d]", trim(gzread($gz, $carExtra)), $carExtra);
			}
		}

		$buf .= sprintf("\nCAR\t\"%s\" [%d]", trim(gzread($gz, $carSize)), $carSize);

		$buf .= sprintf("\n?\t%08x", readUIntLE($gz, 4));

		if ($type == 0x3F88F5C3) {
			$buf .= sprintf("\nLAPS\t%d", $laps = readUIntLE($gz, 4));
			for ($lap = 0; $lap < $laps; ++$lap) {
				$t1 = readUIntLE($gz, 4);
				$t2 = readUIntLE($gz, 4);
				$buf .= sprintf("\n\tLAP %d\t%08x %08x", $lap, $t1, $t2);
			}
		}

		$buf .= "\n";
	}

	$buf .= "\n? " . hexDump(gzread($gz, 500));

	return $buf;
}

$explodeUrl = 'php://stdin';

($h = gzopen($explodeUrl, "rb")) || die("can't open $explodeUrl");
echo slotInfo($h);
gzclose($h);
?>