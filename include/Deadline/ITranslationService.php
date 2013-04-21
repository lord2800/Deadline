<?php
namespace Deadline;

interface ITranslationService {
	function setDomain($domain);
	function setLanguage($lang);
	function translate($key);
}
