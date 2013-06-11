<?php
namespace Deadline;

class QualityParser {
	public static function parseQuality($value) {
		$pieces = explode(',', $value);
		$values = [];
		foreach($pieces as $piece) {
			$pos      = strpos($piece, ';');
			$quality  = $pos !== false ? floatval(substr($piece, $pos + 3)) : 1.0;
			$name     = substr($piece, 0, $pos !== false ? $pos : strlen($piece));
			$values[] = ['name' => $name, 'quality' => $quality];
		}

		return $values;
	}

	public static function sortQuality($value) {
		$parsed = self::parseQuality($value);
		uksort($parsed, function ($a, $b) {
			return $a['quality'] - $b['quality'];
		});

		return $parsed;
	}

	public static function bestQuality($value) {
		return self::sortQuality($value)[0]['name'];
	}
}
