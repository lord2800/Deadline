<?php
namespace Deadline;

abstract class View {
	protected $locale;

	public abstract function getContentType();
	public abstract function render(Response $response);

	public function setLocale($locale) { $this->locale = $locale; }
	public function setFilters(array $filters) {}
}
