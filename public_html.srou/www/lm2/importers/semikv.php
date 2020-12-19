<?php

/*
	Class to parse semicolon delimited key-value format as defined by rpydump utility
*/

class SemiKV
{
	private $data; //array of lines

	private function startsWith($haystack, $needle)
	{
		$length = strlen($needle);
		return (substr($haystack, 0, $length) === $needle);
	}

	private function endsWith($haystack, $needle)
	{
		$length = strlen($needle);
		if ($length == 0)
			return TRUE;

		return (substr($haystack, -$length) === $needle);
	}

	private function splitLine($line)
	{
		$row = array(); //result
		$tokens = explode(';', $line, -1);
		foreach ($tokens as $token)
		{
			if (! strlen($token))
				continue;
			
			if (strpos($token, '=') == FALSE)
			{
				$row[$token] = TRUE;
				continue;
			}
			
			$epos = strpos($token, '=');
			$col = substr($token, 0, $epos);
			$val = substr($token, $epos+1);
			if (strpos($val, "\xcd\xbe"))
				$val = str_replace("\xcd\xbe", ';', $val); //replace escaped semicolon
			
			//print "col:$col, val:$val\n";
			$row[$col] = $val;
		}
		return $row;
	}

	function __construct(array &$data)
	{
		$this->data = $data;
	}

	// returns SEMIKV version; 0 == no semikv format
	// parameter is requested format id (format=xxx;)
	public function getVersion($reqFormat = NULL)
	{
		$hasStart = FALSE;
		$hasEnd = FALSE;
		$version = 0;
		$format = NULL;
		foreach ($this->data as $line)
		{
			if ($this->startsWith($line, "semikv_start;"))
			{
				$row = $this->splitLine($line);
				if ($reqFormat and array_key_exists("format", $row))
					$format = $row["format"];
				else
					$format = NULL;
				if ($format == $reqFormat and array_key_exists("semikv_version", $row))
				{
					$version = $row["semikv_version"];
					break;
				} else
				{
					$version = 0;
					break;
				}
			}
			if (!$reqFormat and $this->startsWith($line, "table_start;"))
				$hasStart = TRUE;
			if (!$reqFormat and $this->startsWith($line, "table_end;"))
				$hasEnd = TRUE;
			if ($hasStart and $hasEnd)
			{
				$version = 1;
				break;
			}
		}

		return $version;
	}

	public function parse($reqFormat = NULL)
	{
		$ti = 0;
		$maxversion = 2; //what semikv version we suppport
		$version = $this->getVersion($reqFormat);
	
		if (!$version)
			return NULL;
		if ($version > $maxversion)
		{
			print "Warning: unsupported semikv version $version\n";
			return NULL;
		}

		$tables = array(); // array of $table
		$table = array();  // array of $row
		$tableName = NULL; // string
		$inParse = $version > 1 ? FALSE : TRUE;
		foreach ($this->data as $line)
		{
			if ($this->startsWith($line, "semikv_start;"))
				$inParse = TRUE;
			if ($this->startsWith($line, "semikv_end;"))
				$inParse = FALSE;
			
			if (! $inParse)
				continue;
			
			$row = $this->splitLine($line);
			if ($row == FALSE)
				continue;

			if (array_key_exists("table_start", $row))
			{
				if (array_key_exists("table", $row))
					$tableName = $row["table"];
				else
					$tableName = sprintf("Table_%d", ++$ti);
				continue;
			}
			if (array_key_exists("table_end", $row))
			{
				if ($table)
					$tables[$tableName] = $table;
				$tableName = NULL;
				$table = NULL;
				continue;
			}
			if ($tableName)
				$table[] = $row; //add row to table
		}
		return $tables;
	}


	public function timeAsSeconds($t) {
		if (!$t/* || !($t = trim($t))*/) {
			return null;
		}

		if (!preg_match("%^(?:(?:(\\d+):)?(\\d+):)?(\\d+\\.\\d{3})$%is", $t, $matches)) {
			throw new InvalidArgumentException("bad time '$t')");
		}
		return ($matches[1] * 60.0 + $matches[2]) * 60.0 + $matches[3];
	}
}
