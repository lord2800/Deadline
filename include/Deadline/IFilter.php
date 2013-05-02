<?php
namespace Deadline;

use \DOMElement;

interface IFilter {
	public function filter(DOMElement $root);
}
