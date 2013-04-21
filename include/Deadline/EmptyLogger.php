<?php
namespace Deadline;

class EmptyLogger {
	public static function init() { return function () {}; }
}
