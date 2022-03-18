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

function bdecode($enc)
{
	$result = null;
	$encLen = strlen($enc);
	for ($i = 0; $i < $encLen; $i++)
	{
		$type = substr($enc, $i, 1);
		if ($type == 'i') {
			$start = ++$i;
			$end = strpos($enc, 'e', $i);
			$result = substr($enc, $start, $end - $start);
			$i = $end;
			continue;
		}
	}
	return $result;
}

?>