<?php
namespace Deadline;

use Psr\Log\LoggerInterface;

use \RuntimeException,
	\ReflectionClass;

class Injector {
	private $instances = [];
	private $classcache = [];
	private $namecache = [];

	public function provide($name, $obj) {
		$class = get_class($obj);
		if(isset($this->namecache[$name])) throw new RuntimeException(sprintf('Duplicate dependency name %s', $name));
		if(isset($this->classcache[$class])) throw new RuntimeException(sprintf('Duplicate dependency class %s', $class));
		$this->namecache[$name] = $obj;
		$this->classcache[$class] = $obj;
	}
	private function getProvider($name) {
		if(isset($this->namecache[$name])) return $this->namecache[$name];
		if(isset($this->classcache[$name])) return $this->classcache[$name];
		return null;
	}

	public function get($class, array $options = []) {
		if(!isset($this->instances[$class])) {
			$instance = $this->create($class, $options);
			$this->instances[$class] = $instance;
		} else {
			$instance = $this->instances[$class];
		}
		return $instance;
	}
	public function create($class, array $options = []) {
		$options = array_merge(['try' => 'Deadline', 'fallback' => 'Deadline'], $options);
		$tryNamespace = $options['try'];
		$fallbackNamespace = $options['fallback'];
		$fqn = $class;
		$instance = null;

		if(!class_exists($fqn)) $fqn = $tryNamespace . '\\' . $class;
		if(!class_exists($fqn)) $fqn = $fallbackNamespace  . '\\' . $class;
		if(!class_exists($fqn)) throw new RuntimeException(sprintf('Class %1$s not found, tried namespaces: %1$s, %2$s\\%1$s, %3$s\\%1$s',
			$class, $tryNamespace, $fallbackNamespace));
		$ref = new ReflectionClass($fqn);
		$ctor = $ref->getConstructor();
		$args = [];
		if($ctor !== null) {
			foreach($ctor->getParameters() as $parameter) {
				$dependency = null;
				// try by typehint first, if that fails try by name
				$name = $parameter->getClass()->getName();
				if($name != null) {
					$dependency = $this->getProvider($name);
				}
				if($dependency === null) {
					$dependency = $this->getProvider($parameter->getName());
				}
				if($dependency != null) {
					if(is_callable($dependency)) {
						$dependency = $dependency();
					}
					$args[$parameter->getPosition()] = $dependency;
				} else {
					var_dump($this);
					throw new RuntimeException(sprintf('Could not satisfy dependency %s for class %s', $parameter->getName(), $class));
				}
			}
		}

		return $ref->newInstanceArgs($args);
	}
}
