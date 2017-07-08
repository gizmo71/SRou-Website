<?php

function scoringSchemeRefDataFieldFKSQL($where) {
	return "
		SELECT id_scoring_scheme AS id, scoring_scheme_name AS description
		FROM {$GLOBALS['lm2_db_prefix']}scoring_schemes
		WHERE $where
		ORDER BY description
	";
}

class RefDataField {
	var $name;

	function __construct($name) {
		$this->name = $name;
	}

	function getName() {
		return $this->name;
	}

	function render($row, $rownum) {
		die("cannot render from the base class");
	}

	function sqlize($value) {
		return sqlString($value);
	}

	function buildJS() {
		return null;
	}

	function compare($value1, $value2) {
		if (is_null($value1) xor is_null($value2))
			return is_null($value1) ? 1 : -1;
		if (is_numeric($value1) && is_numeric($value2)) {
			if ($value1 > $value2)
				return 1;
			else if ($value1 < $value2)
				return -1;
			else
				return 0;
		}
		return strcasecmp($value1, $value2);
	}

	function getSql($table) {
		return "$table." . $this->getName();
	}
}

class RefDataFieldReadOnly extends RefDataField {
	var $isHtml;
	var $maxWidth;

	function __construct($name, $isHtml = false, $maxWidth = 30) {
		parent::__construct($name);
		$this->isHtml = $isHtml;
		$this->maxWidth = $maxWidth;
	}

	function render($row, $rownum) {
		$value = $row[$this->getName()];
		if ($this->isHtml) {
			$value = html_entity_decode($value, ENT_QUOTES);
		}
		$extraValue = '';
		if (strlen($value) > $this->maxWidth) {
			$extra = htmlentities($value, ENT_QUOTES);
			$value = substr($value, 0, $this->maxWidth - 1);
			$extraValue = "&hellip;";
		} else {
			$extra = null;
		}
		$value = htmlentities($value, ENT_QUOTES);
		return "<SMALL" . (is_null($extra) ? '' : " TITLE='$extra'") . ">$value$extraValue</SMALL>";
	}

	function sqlize($value) {
		return null;
	}
}

class RefDataFieldReadOnlySql extends RefDataFieldReadOnly {
	var $sql;

	function __construct($name, $is_html, $sql, $maxWidth = 30) {
		parent::__construct($name, $is_html, $maxWidth);
		$this->sql = $sql;
	}

	function getSql($table) {
		return "($this->sql) AS " . $this->getName();
	}
}

class RefDataFieldUpgradeCodeReadOnly extends RefDataFieldReadOnly {
	function render($row, $rownum) {
		$value = $row[$this->getName()];
		if (!is_null($value)) {
			$value = "<TT>$value</TT>";
		}
		return $value;
	}

	function getSql($table) {
		return "HEX($table." . $this->getName() . ") AS " . $this->getName();
	}
}

class RefDataFieldEdit extends RefDataField {
	var $size;
	var $max;

	function __construct($name, $size = 5, $max = null) {
		parent::__construct($name);
		$this->size = $size;
		$this->max = is_null($max) ? $size : $max;
	}

	function render($row, $rownum) {
		$name = $this->getName();
		$value = $row[$this->getName()];
		if (($max = strlen($value)) < ($size = $this->size)) {
			$max = $this->max;
		}
		if ($size > 30) {
			$size = 30;
		}
		$value = htmlentities($value, ENT_QUOTES);
		return "<INPUT NAME=\"$name$rownum\" TYPE=\"TEXT\" VALUE=\"$value\" MAXLENGTH=\"$max\" SIZE=\"$size\" />";
	}
}

class RefDataFieldDate extends RefDataField {
	var $noTime;

	function __construct($name, $noTime = false) {
		parent::__construct($name);
		$this->noTime = $noTime;
	}

	function sqlize($value) {
		$day =  $month = $year = $hour = $minute = $second = null; // Shut the compiler up.
		if ($value) {
			if ($this->noTime) {
				(sscanf($value, "%d-%d-%d", $day, $month, $year) == 3)
					|| die("bad Tigra timestamp $value");
				$value = sprintf("%04d-%02d-%02d", $year, $month, $day);
			} else {
				(sscanf($value, "%d-%d-%d %d:%d:%d", $day, $month, $year, $hour, $minute, $second) == 6)
					|| die("bad Tigra timestamp $value");
				$value = sprintf("%04d-%02d-%02d %02d:%02d:%02d", $year, $month, $day, $hour, $minute, $second);
			}
		}
		return sqlString($value);
	}

	function compare($value1, $value2) {
		return strcmp($this->sqlize($value1), $this->sqlize($value2));
	}

	function getSql($table) {
		return sprintf("DATE_FORMAT($table.%s, '%s') AS %s", $this->getName(), '%d-%m-%Y %T', $this->getName());
	}

	function render($row, $rownum) {
		$name = $this->getName() . $rownum;
		$value = $row[$this->getName()];
		$value = htmlentities($value, ENT_QUOTES);

		return sprintf("<INPUT TYPE=\"EDIT\" NAME=\"$name\" VALUE=\"$value\" SIZE='%d'>"
			. "<IMG SRC=\"/images/tigra_calendar/cal.gif\" onClick=\"showCal(document.rdForm.$name);\">\n",
			$this->noTime ? 8 : 17);
	}
}

?>
<SCRIPT>
function write_options(list, currentId) {
	for (var i in list) {
		var item = list[i];
		var id = item['id'];
		var sel = '';
		if (id == currentId) {
			currentId = null;
			sel = " SELECTED";
		} else if (item['hide']) {
			continue;
		} else {
			continue;
		}
		document.write('<OPTION VALUE="' + id + '"' + sel + '>' + item['description'] + '</OPTION>\n');
	}
	if (currentId != null) {
		document.write('<OPTION VALUE="' + currentId + '" SELECTED>??? (' + currentId + ')</OPTION>\n');
	}
}

function fill_options(list, select) {
	if (!select.has_been_filled_in) {
		select.has_been_filled_in = true;
		currentId = select.options[select.selectedIndex].value;
		for (var i in list) {
			var item = list[i];
			if (item['id'] == currentId || item['hide']) {
				continue;
			}
			select.options.add(new Option(item['description'], item['id']));
		}
	}
}
</SCRIPT>
<?php

class RefDataFieldFK extends RefDataField {
	var $map = array();
	var $valueMap = array();
	var $jsName;
	var $sql = null;
	var $allowNull = null;
	var $width = null;

	function __construct($name, $sql, $allowNull = false, $width = null) {
		parent::__construct($name);
		$this->sql = $sql;
		$this->allowNull = $allowNull;
		$this->width = $width;
	}

	function maybeLoadData() {
		if (is_null($this->sql)) {
			return;
		}

		static $cacheMap = array();
		$cacheKey = print_r($this->sql, true);
		$cacheEntry = $cacheMap[$cacheKey];

		if (is_null($cacheEntry)) {
			if ($this->allowNull) {
				array_push($this->map, array(id=>null, description=>""));
			}
			if (is_array($this->sql)) {
				foreach ($this->sql as $id=>$description) {
					array_push($this->map, array(id=>$id, description=>$description));
					$this->valueMap[$id] = $description;
				}
			} else  {
				$query = lm2_query($this->sql, __FILE__, __LINE__);
				while ($row = mysql_fetch_assoc($query)) {
					array_push($this->map, $row);
					$this->valueMap[$row['id']] = $row['description'];
				}
				mysql_free_result($query);
			}

			$cacheEntry = array(name=>$this->name, map=>$this->map, vm=>$this->valueMap);
			$cacheMap[$cacheKey] = $cacheEntry;
		}

		$this->jsName = $cacheEntry['name'];
		$this->map = $cacheEntry['map'];
		$this->valueMap = $cacheEntry['vm'];

		$this->sql = $this->allowNull = null;
	}

	function useJS() {
		$this->maybeLoadData();

		global $rows;
		return count($this->map) > 10 || count($this->map) * count($rows) > 15;
	}

	function makeDesc($row, $type) {
		$desc = $row['description'];
		if ($type == 'JS') {
			if ($row['is_html'])
				$desc = html_entity_decode($desc, ENT_QUOTES);
			$desc = str_replace("'", '\\\'', $desc);
		} else if ($type == 'HTML') {
			if (!$row['is_html'])
				$desc = htmlentities($desc);
		} else {
			die("unknown text type $type");
		}
		return $desc;
	}

	function buildJS() {
		if (!$this->useJS() || $this->jsName != $this->name)
			return null;

		$js = "var {$this->jsName}_list = [";
		$sep = "\n";
		foreach ($this->map as $row) {
			$desc = $this->makeDesc($row, 'JS');
			$js .= "$sep { id: \"{$row['id']}\", description: '$desc', hide: " . ($row['hide'] ? 'true' : 'false') . " }";
			$sep = ",\n";
		}
		$js .= "]";
		return $js;
	}

	function getMap($row) {
		$this->maybeLoadData();

		return $this->map;
	}

	function render($row, $rownum) {
		$this->maybeLoadData();

		$id_selected = $row[$this->getName()];
		$name = $this->getName();
		
		$map = $this->getMap($row);
		
		$html = "<" . $this->makeSelect($rownum);
		if ($this->useJS()) {
			$html .= " onFocus='fill_options({$this->jsName}_list, this)'>";
			$html .= "<SCRIPT>write_options({$this->jsName}_list, \"$id_selected\");</SCRIPT>";
		} else {
			$html .= ">";
			$default = '???';
	
			$found = false;
			foreach ($map AS $item) {
				$id = $item['id'];
				if ($id == $id_selected) {
					$sel = " SELECTED";
					$found = true;
				} else if ($item['hide']) {
					continue;
				} else {
					$sel = "";
				}
				$html .= "    <OPTION VALUE=\"$id\"$sel>" . $this->makeDesc($item, 'HTML') . "</OPTION>\n";
			}
			if (!$found) {
				$html .= "    <OPTION VALUE=\"$id_selected\" SELECTED>$default: $id_selected</OPTION>\n";
			}
		}
		$html .= "</SELECT>";

		return $html;
	}

	function makeSelect($rownum) {
		$name = $this->getName();
		$size = $this->width ? sprintf(" STYLE='max-width: %s; min-width: %s;'", $this->width, $this->width) : "";
		return "SELECT NAME='$name$rownum'$size";
	}

	function valueOrKey($key) {
		$this->maybeLoadData();
		$value = $this->valueMap[$key];
		return is_null($value) ? $key : $value;
	}

	function compare($value1, $value2) {
		$v1 = $this->valueOrKey($value1);
		$v2 = $this->valueOrKey($value2);
		return RefDataField::compare($v1, $v2);
	}
}

?>
