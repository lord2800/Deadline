<?php

require_once("deadline://thirdparty/PHPTAL/PHPTAL.php");

function plural($s, $c) { return $c > 1 ? $s . 's' : $s; }

function human_date($date) {
	if($date == null) return '';
	if(!($date instanceof \DateTime)) {
		$date = \DateTime::createFromFormat('Y-m-d H:i:s', $date);
	}
	$diff = $date->diff(new \DateTime());
	$string = '';

	if($diff->y > 1) {
			$string = $diff->y . ' ' . plural('year', $diff->y) . ' ago';
	} else if($diff->m > 1) {
			$string = $diff->m . ' ' . plural('month', $diff->m) . ' ago';
	} else if($diff->d > 7) {
			$string = 'on ' . $date->format('l, F n') . ' at ' . $date->format('g:i a');
	} else if($diff->d > 2) {
			$string = $diff->d . ' ' . plural('day', $diff->d) . ' ago at ' . $date->format('g:i a');
	} else if($diff->d == 1) {
			$string = 'yesterday at ' . $date->format('g:i a');
	} else {
			if($diff->h > 1) {
					$string = $diff->h . ' ' . plural('hour', $diff->h) . ' ago';
			} else if($diff->i > 1) {
					$string = $diff->i . ' ' . plural('minute', $diff->i) . ' ago';
			} else if($diff->s > 15) {
					$string = $diff->s . ' seconds ago';
			} else $string = 'just now';
	}

	return $string;
}

function full_date(\DateTime $date) {
	return $date->format('l, F n g:i:s a');
}

function phptal_tales_human_date($src, $nothrow) {
	return 'human_date(' . phptal_tales(trim($src), $nothrow) . ')';
}

function phptal_tales_full_date($src, $nothrow) {
	return 'full_date(' . phptal_tales(trim($src), $nothrow) . ')';
}

function phptal_tales_markdown($src, $nothrow) {
	return 'markdown(' . phptal_tales(trim($src), $nothrow) . ')';
}

?>
