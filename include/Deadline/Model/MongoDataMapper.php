<?php
namespace Deadline\Model;

use Psr\Log\LoggerInterface;

use Deadline\App,
	Deadline\IStorage,
	Deadline\IDataMapper;

use \MongoClient,
	\MongoId,
	\Serializable,
	\ReflectionClass,
	\ReflectionProperty as RP;

class MongoDataMapper implements IDataMapper {
	private $client, $store, $mode, $logger;

	public function __construct(App $app, IStorage $store, LoggerInterface $logger) {
		$this->store = $store;
		$this->mode = $app->mode();
	}

	public function connect($key = 'connection_settings') {
		$default = [
			'production' => [
				'dsn' => 'mongodb://' . MongoClient::DEFAULT_HOST . ':' . MongoClient::DEFAULT_PORT,
				'username' => '', 'password' => '', 'db' => ''
			],
			'debug' => [
				'dsn' => 'mongodb://' . MongoClient::DEFAULT_HOST . ':' . MongoClient::DEFAULT_PORT,
				'username' => '', 'password' => '', 'db' => ''
			]
		];
		$settings = $this->store->get($key, $default)[$this->mode];

		$this->client = new MongoClient($settings['dsn'], [
			'db' => $settings['db'],
			'username' => $settings['username'],
			'password' => $settings['password']
		]);
		$this->db = $this->client->{$settings['db']};
	}

	public final function persist(Serializable $object) {
		$class = get_class($object);
		$collection = $this->db->$class;
		$serialized = $object->serialize();
		if(isset($serialized['_id'])) {
			$serialized['_id'] = new MongoId($serialized['_id']);
		}
		return $collection->save($serialized);
	}
	public final function destroy(Serializable $object) {
		$class = get_class($class);
		$collection = $this->db->$class;
		$id = $object->serialize()['_id'];
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
