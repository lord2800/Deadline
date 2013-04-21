<?php
namespace Deadline;

class EmptyBenchmark implements IBenchmark {
	public function snapshot($name) { }
	public function output() { return ''; }
}
