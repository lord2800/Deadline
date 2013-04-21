<?php
namespace Deadline;

interface IBenchmark {
	function snapshot($name);
	function output();
}
