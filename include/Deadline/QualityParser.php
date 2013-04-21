<?php
namespace Deadline;

class QualityParser {
	public function parseQuality($value) {
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

	public function sortQuality($value) {
		$parsed = $this->parseQuality($value);
		uksort($parsed, function ($a, $b) {
			return $a['quality'] - $b['quality'];
		});

		return $parsed;
	}

	public function bestQuality($value) {
		return $this->sortQuality($value)[0]['name'];
	}
}
