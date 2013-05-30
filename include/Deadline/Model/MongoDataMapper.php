<?php
namespace Deadline\Model;

use Psr\Log\LoggerInterface;

use Deadline\DatabaseHandle,
	Deadline\IDataMapper;

use \MongoClient,
	\MongoId,
	\Serializable,
	\ReflectionClass,
	\ReflectionProperty as RP;

abstract class MongoDataMapper implements IDataMapper {
	private $client, $dbh, $logger;

	public function __construct(DatabaseHandle $dbh, LoggerInterface $logger) {
		$this->dbh = $dbh;
		$this->logger = $logger;
	}

	public function connect($key = 'primary') {
		$this->client = $this->dbh->get($key);
		$this->db = $this->client->{$settings['db']};
	}

	public function transaction(callable $callable) {
		// MongoDB does not support transactions
		$callable();
	}

	public final function persist($object) {
		$class = get_class($object);
		$collection = $this->db->$class;
		$vars = get_class_vars($object);
		$serialized = [];
		foreach($vars as $name => $default) {
			$serialized[$name] = $object->$name;
		}

		if(isset($serialized['_id'])) {
			$serialized['_id'] = new MongoId($serialized['_id']);
		}
		return $collection->save($serialized);
	}
	public final function destroy($object) {
		$class = get_class($class);
		$collection = $this->db->$class;
		$id = $object->_id;
		return $collection->remove(['_id' => new MongoId($id)], ['justOne' => true]);
	}

	public final function findByKey($collection, $key, $value, array $options = []) {
		$options = array_merge($options, [
			'key' => '_id',
			'value' => new MongoId(''),
			'limit' => 0,
			'projection' => []
		]);
		return $this->query($collection, [$key => $value], $options['projection'])->limit($options['limit'])->batchSize($options['limit']);
	}
	protected final function findById($collection, $id, array $projection) {
		return $this->findByKey($collection, '_id', new MongoId($id), ['limit' => 1, 'projection' => $projection]);
	}
	protected final function query($collection, array $query, array $fields = []) {
		return $this->db->$collection->find($query, $fields);
	}
	protected final function map($class, array $row) {
		$ref = new ReflectionClass($class);
		$instance = $ref->newInstance();
		foreach($ref->getProperties(RP::IS_PUBLIC) as $prop) {
			$name = $prop->getName();
			$instance->$name = $row[$name];
		}
		return $instance;
	}
}
