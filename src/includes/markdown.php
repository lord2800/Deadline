<?php
require_once('deadline://thirdparty/Markdown/Text.php');

class markdown {
	public static function parse($text) { return (string)new Markdown\Text($text); }
}
?>