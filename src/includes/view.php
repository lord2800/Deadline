<?php
// AVOID a namespace here due to class loading
//namespace Deadline;

interface View {
	public function __construct($template, $base, $encoding, $reparse);
	public function output();
	public function getContentType();
	public function setTemplate($template);
}

?>