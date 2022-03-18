<?php

function bencode($var)
{
	if (is_string($var)) {
		return strlen($var) .':'. $var;
	} else if (is_int($var)) {
		return 'i'. $var .'e';
	} else if (is_float($var)) {
		return 'i'. sprintf('%.0f', $var) .'e';
	} else if (is_array($var)) {
		if (count($var) == 0) {
			return 'de';
		} else {
			$assoc = false;

			foreach ($var as $key => $val) {
				if (!is_int($key)) {
					$assoc = true;
					break;
				}
			}

			if ($assoc) {
				ksort($var, SORT_REGULAR);
				$ret = 'd';

				foreach ($var as $key => $val) {
					$ret .= bencode($key) . bencode($val);
				}
				return $ret .'e';
			} else {
				$ret = 'l';

				foreach ($var as $val) {
					$ret .= bencode($val);
				}
				return $ret .'e';
			}
		}
	} else {
		do_error('bencode wrong data type');
	}
}

function bdecode($enc, &$off = null)
{
	$result = null;
	if ($off == null) {
		$_off = 0;
		$off = $_off;
	}

	$type = substr($enc, $off, 1);
	if ($type == 'i') {
		$end = strpos($enc, 'e', $off + 1);
		$result = intval(substr($enc, $off + 1, $end - $off - 1));
		$off = $end + 1;
	} else if ($type == 'l') {
		$off++;
		$result = array();
		while (substr($enc, $off, 1) != 'e') {
			array_push($result, bdecode($enc, $off));
		}
	} else if ($type == 'd') {
		$off++;
		$result = array();
		while (substr($enc, $off, 1) != 'e') {
			$key = bdecode($enc, $off);
			$value = bdecode($enc, $off);
			$result[$key] = $value;
		}
	} else {
		$sep = strpos($enc, ':', $off);
		$len = intval(substr($enc, $off, $sep - $off));
		$result = substr($enc, $sep + 1, $len);
		$off = $sep + 1 + $len;
	}

	return $result;
}

?>