<?php
namespace Deadline\Factory;

use Psr\Log\LoggerInterface;

use \RuntimeException,
	\ReflectionClass;

class InstanceFactory {
	private $instances = [];
	private $dependents = [];

	public function addDependent($name, $obj) {
		if(isset($this->dependents[$name])) throw new RuntimeException('Duplicate dependency ' . $name);
		$this->dependents[$name] = $obj;
	}

	public function get($class, array $options = []) {
		$options = array_merge(['mustBeNew' => false, 'try' => 'Deadline', 'fallback' => 'Deadline'], $options);
		$tryNamespace = $options['try'];
		$fallbackNamespace = $options['fallback'];
		$fqn = $tryNamespace . '\\' . $class;

		if(!class_exists($fqn)) $fqn = $fallbackNamespace  . '\\' . $class;
		if(!class_exists($fqn)) throw new RuntimeException('Class ' . $class . ' not found, tried namespaces: ' .
			$tryNamespace . '\\' . $class . ', ' . $fallbackNamespace . '\\' . $class);

		if(!isset($this->instances[$fqn])) {
			$ref = new ReflectionClass($fqn);
			$ctor = $ref->getConstructor();
			$args = [];
			if($ctor !== null) {
				foreach($ctor->getParameters() as $parameter) {
					$dependent = isset($this->dependents[$parameter->getName()]) ? $this->dependents[$parameter->getName()] : null;
					if($dependent != null) {
						if(is_callable($dependent)) {
							$dependent = $dependent();
						}
						$args[] = $dependent;
					} else {
						throw new RuntimeException('Could not satisfy dependency ' . $parameter->getName() . ' for class ' . $class);
					}
				}
			}

			$instance = $ref->newInstanceArgs($args);
			if(!!!$options['mustBeNew']) $this->instances[$fqn] = $instance;
		} else {
			$instance = $this->instances[$fqn];
		}
		return $instance;
	}
}
