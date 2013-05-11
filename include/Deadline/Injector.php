<?php
namespace Deadline;

use Psr\Log\LoggerInterface;

use \RuntimeException,
	\ReflectionClass;

class Injector {
	private $instances = [], $classcache = [], $namecache = [];

	/**
	 * Provide a dependency to the injector. You may make use of the dependency either by classname (of the object) or
	 * by the provided name (in the case of a closure)
	 * @param string [$name] The name of the dependency being provided
	 * @param mixed [$obj] The actual object backing the dependency (which may be a closure)
	 * @throws RuntimeException If the provided dependency name or class already exists
	 */
	public function provide($name, $obj) {
		// store dependencies by class and by name, so you can match by either the typehint or the parameter name
		$class = get_class($obj);
		if(isset($this->namecache[$name])) throw new RuntimeException(sprintf('Duplicate dependency name %s', $name));
		if(isset($this->classcache[$class])) throw new RuntimeException(sprintf('Duplicate dependency class %s', $class));
		$this->namecache[$name] = $obj;
		// don't add closures to the class cache--it's only for concrete instances (which means this won't trip up the isset above, either)
		if($class !== 'Closure') $this->classcache[$class] = $obj;
	}

	private function getProvider($name) {
		// the class cache is more robust, so check it first
		if(isset($this->classcache[$name])) return $this->classcache[$name];
		if(isset($this->namecache[$name])) return $this->namecache[$name];
		return null;
	}
	private function getFullyQualifiedClassName($class, $options) {
		$fqn = $class;
		if(!class_exists($fqn)) $fqn = $options['try'] . '\\' . $class;
		if(!class_exists($fqn)) $fqn = $options['fallback']  . '\\' . $class;
		if(!class_exists($fqn)) throw new RuntimeException(sprintf('Class %1$s not found, tried namespaces: %1$s, %2$s\\%1$s, %3$s\\%1$s',
			$class, $options['try'], $options['fallback']));
		return $fqn;
	}

	/**
	 * Get an instance of the specified class, with the specified options
	 * Supported options:
	 * try - The first namespace to look for the class in
	 * fallback - The second namespace to look for the class in
	 * @param string [$class] The name of the class (either full or partial) to look up
	 * @param array [$options] The options
	 * @return mixed
	 */
	public function get($class, array $options = []) {
		$options = array_merge(['try' => 'Deadline', 'fallback' => 'Deadline'], $options);
		$class = $this->getFullyQualifiedClassName($class, $options);
		if(!isset($this->instances[$class])) {
			$instance = $this->create($class, $options);
			$this->instances[$class] = $instance;
		} else {
			$instance = $this->instances[$class];
		}
		return $instance;
	}

	/**
	 * Create a new instance of the specified class, with the specified options
	 * @see get
	 * @param string [$class] The name of the class (either full or partial) to look up
	 * @param array [$options] The options
	 * @return mixed
	 */
	public function create($class, array $options = []) {
		$options = array_merge(['try' => 'Deadline', 'fallback' => 'Deadline'], $options);
		$instance = null;
		$fqn = $this->getFullyQualifiedClassName($class, $options);

		$ref = new ReflectionClass($fqn);
		$ctor = $ref->getConstructor();

		$args = [];
		if($ctor !== null) {
			// if the class has a ctor, inject the dependencies it wants
			foreach($ctor->getParameters() as $parameter) {
				$dependency = null;
				// try by typehint first, if that fails try by name
				$name = $parameter->getClass()->getName();
				if($name !== null) $dependency = $this->getProvider($name);
				if($dependency === null) $dependency = $this->getProvider($parameter->getName());

				if($dependency !== null) {
					if(is_callable($dependency)) $dependency = $dependency();
					$args[$parameter->getPosition()] = $dependency;
				} else {
					throw new RuntimeException(sprintf('Could not satisfy dependency %s for class %s', $parameter->getName(), $class));
				}
			}
		}

		return $ref->newInstanceArgs($args);
	}
}
