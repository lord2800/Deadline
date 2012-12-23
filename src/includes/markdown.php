<?php
require_once('deadline://vendor/cbednarski/php-markdown/src/MarkdownParser.php');

class markdown {
	public static function parse($text) { return MarkdownParser::parse($text); }
}
