<?php
// AVOID a namespace here due to class loading
//namespace Deadline;

interface View {
	public function __construct($encoding, $reparse);
	public function prepare(Deadline\Response $response);
	public function output();
	public function getTemplate();
	public function setTemplate($template);
}
