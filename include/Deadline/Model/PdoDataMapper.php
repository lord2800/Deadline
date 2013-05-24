<?php
namespace Deadline\Model;

use Psr\Log\LoggerInterface;

use Deadline\App,
	Deadline\IStorage,
	Deadline\IDataMapper,
	Deadline\DeadlineStreamWrapper;

use \PDO,
	\Serializable;

abstract class PdoDataMapper implements IDataMapper {
	private $db, $store, $mode, $logger;

	private static $typemap = [
		"boolean" => PDO::PARAM_BOOL,
		"integer" => PDO::PARAM_INT,
		"double"  => PDO::PARAM_STR, // PDO doesn't seem to support doubles/decimals out of the box
		"string"  => PDO::PARAM_STR,
		"null"    => PDO::PARAM_NULL
	];

	const FETCH_ASSOC = PDO::FETCH_ASSOC,
		  FETCH_BOTH  = PDO::FETCH_BOTH,
		  FETCH_BOUND = PDO::FETCH_BOUND,
		  FETCH_CLASS = PDO::FETCH_CLASS,
		  FETCH_INTO  = PDO::FETCH_INTO,
		  FETCH_LAZY  = PDO::FETCH_LAZY,
		  FETCH_NUM   = PDO::FETCH_NUM,
		  FETCH_OBJ   = PDO::FETCH_OBJ,
		  FETCH_PROPS_LATE = PDO::FETCH_PROPS_LATE;

	public function __construct(App $app, IStorage $store, LoggerInterface $logger) {
		$this->store = $store;
		$this->mode = $app->mode();
		$this->logger = $logger;
	}

	public function connect($key = 'connection_settings') {
		$default = [
			'production' => ['dsn' => 'sqlite:' . DeadlineStreamWrapper::resolve('deadline://database.db3'), 'user' => null, 'pass' => null],
			'debug'      => ['dsn' => 'sqlite::memory:', 'user' => null, 'pass' => null]
		];
		$settings = $this->store->get($key, $default)[$this->mode];

		$this->db = new PDO($settings['dsn'], $settings['user'], $settings['pass']);
		$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}

	/* 
	 * NB: this function is limited; it assumes your table has an id field that is unique, and does not
	 * handle composite keys in any way whatsoever, nor does it attempt to handle foreign keys--you must
	 * pre-process that info elsewhere before persisting, and it assumes you want to persist all public
	 * properties on your object.
	 */
	public final function persist($object) {
		$table = $this->mung(get_class($object));
		$vars = get_class_vars($object);
		$data = [];
		foreach($vars as $name => $default) {
			$data[$name] = $object->$name;
		}
		$keys = array_keys($data);
		foreach($keys as &$key) {
			$key = $this->mung($key);
		}

		$isNew = !array_key_exists('id', $data) || empty($data['id']);
		$sql = null;
		if($isNew) {
			// if we don't have an id or the id is empty, we want to insert
			$sql = 'INSERT INTO ' . $table . ' (`' . implode('`,`', $keys) . '`) VALUES (' . $this->genSlots(['type' => 'insert', 'keys' => $keys]) . ');';
		} else {
			// otherwise, we want to update on that id
			unset($keys['id']);
			$sql = 'UPDATE ' . $table . ' SET ' . $this->genSlots(['type' => 'update', 'keys' => $keys]) . ' WHERE `id` = :id LIMIT 1;';
		}

		$this->logger->debug('Generated SQL: ' . $sql);
		$query = $this->db->prepare($sql);
		foreach($data as $name => $value) {
			$query->bindParam($this->mung($name), $value, static::$typemap[gettype($value)]);
		}
		$query->execute($data);
		if($isNew) {
			$object->id = $query->lastInsertId();
		}
		return $object;
	}
	public final function destroy(Serializable $object) {
		$table = $this->mung(get_class($object));
		$id = $object->serialize()['id'];
		return $this->query('DELETE FROM ' . $table . ' WHERE id = ? LIMIT 1;', [$id]);
	}

	public final function findByKey($model, $key, $value, array $options = []) {
		$options = array_merge($options, [
			'limit' => 0,
			'projection' => []
		]);
		$limit = $options['limit'] > 0 ? ' LIMIT ' . $options['limit'] : '';
		$projection = $options['projection'];
		$projection = empty($projection) ? '*' : '`' . implode('`,`', $projection) . '`';
		return $this->query('SELECT ' . $projection . ' FROM ' . $this->mung($model) . ' WHERE ' . $key . ' = ?' . $limit . ';', [$value], $model);

	}
	protected final function findById($model, $id, array $projection = []) {
		return $this->findByKey($model, 'id', $id, ['limit' => 1, 'projection' => $projection]);
	}
	protected final function query($sql, array $params, $model = '') {
		$this->logger->debug('Running SQL: ' . $sql);
		$query = $this->db->prepare($sql);
		if(!empty($model)) {
			$query->setFetchMode(self::FETCH_CLASS | self::FETCH_PROPS_LATE, $model);
		}
		if($query->execute($params)) {
			return $query;
		}
		return [];
	}
	protected final function mung($thing) {
		// name munging strategy for now: translate camelCase into camel_case
		return preg_replace_callback('/([A-Z])/', function ($m) { return '_' . strtolower($m[1]); }, lcfirst($thing));
	}
	protected final function unmung($thing) {
		// name unmunging strategy: undo the above
		return lcfirst(preg_replace_callback('/_([a-z])/', function ($m) { return strtoupper($m[1]); }, $thing));
	}
	protected final function genSlots(array $opts) {
		$opts = array_merge(['type' => 'placeholders', 'keys' => []], $opts);
		$returns = [
			'insert' => function () use($opts) {
				$values = '';
				array_walk($opts['keys'], function ($value, $name) use($values) { $values .= '`' . $name . '` = :' . $name . ', '; });
				return substr($values, 0, -2);
			},
			'update' => function () use($opts) {
				return substr(array_reduce($opts['keys'], function (&$result, $k) { $result .= ':' . $k . ', '; return $result; }, ''), 0, -2);
			},
			'placeholders' => function () use($opts) {
				return substr(str_repeat('?, ', count($opts['keys'])), 0, -2);
			}
		];

		return $returns[$opts['type']]();
	}
}
