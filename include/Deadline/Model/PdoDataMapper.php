<?php
namespace Deadline\Model;

use Psr\Log\LoggerInterface;

use Deadline\DatabaseHandle,
	Deadline\IDataMapper,
	Deadline\DeadlineStreamWrapper;

use \PDO,
	\Serializable;

abstract class PdoDataMapper implements IDataMapper {
	private $db, $dbh, $logger;

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

	public function __construct(DatabaseHandle $dbh, LoggerInterface $logger) {
		$this->dbh = $dbh;
		$this->logger = $logger;
	}

	public function connect($key = 'primary') {
		$this->db = $this->dbh->get($key);
	}

	public function transaction(callable $callable) {
		$this->db->beginTransaction();
		try {
			$callable();
		} catch(Exception $e) {
			$this->db->rollback();
			throw new RuntimeException('Transaction threw an exception, changes not committed', 0, $e);
		}
		$this->db->commit();
	}

	private final function getClassFromObject($object) {
		return $this->getClassname(get_class($object));
	}
	private final function getClassname($ns) {
		$parts = explode('\\', $ns);
		return end($parts);
	}

	protected final function getTableName($model) {
		return self::mung($this->getClassname($model));
	}

	protected final function getColumns($object) {
		if(!is_string($object)) {
			$object = get_class($object);
		}

		return get_class_vars($object);
	}

	/* 
	 * NB: this function is limited; it assumes your table has an id field that is unique, and does not
	 * handle composite keys in any way whatsoever, nor does it attempt to handle foreign keys--you must
	 * pre-process that info elsewhere before persisting, and it assumes you want to persist all public
	 * properties on your object.
	 */
	public final function persist($object) {
		if(!property_exists($object, 'id')) {
			throw new LogicException('In order to persist an object, it must have a unique property called "id"');
		}
		if(empty($object->id)) {
			// if the id is empty, we want to insert
			$this->insert($object);
		} else {
			// otherwise, we want to update on that id
			$this->update($object);
		}
	}
	// internal helper code de-duplication function
	private function massageObject($object) {
		$table = $this->mung($this->getClassFromObject($object));
		$vars = $this->getColumns($object);
		$data = [];
		foreach($vars as $name => $default) {
			$data[$name] = $object->$name;
		}
		$keys = array_keys($data);
		return [$table, $keys, $data];
	}
	public final function insert($object) {
		list($table, $keys, $data) = $this->massageObject($object);

		// never include an id for an insert, we assume the id is the primary key of the table and should be autoincremented
		unset($keys[array_search('id', $keys, true)]);
		unset($data['id']);

		$sql = 'INSERT INTO ' . $table . ' (' . $this->genSlots(['type' => 'fields', 'keys' => $keys, 'rename' => false]) .
			') VALUES (' . $this->genSlots(['type' => 'insert', 'keys' => $keys]) . ');';
		$this->logger->debug('Generated SQL: ' . $sql . ' for data ' . var_export($data, true));
		$query = $this->db->prepare($sql);
		foreach($data as $name => $value) {
			$query->bindValue($name, $value, self::$typemap[strtolower(gettype($value))]);
		}
		$query->execute();
		$object->id = $this->db->lastInsertId();
		return $object;
	}
	public final function update($object) {
		list($table, $keys, $data) = $this->massageObject($object);
		// remove the id from the slots list--it's handled externally
		$slots = array_diff($keys, ['id']);
		$slots = array_map(function ($slot) { return $this->mung($slot); }, $slots);

		$keys = array_map(function ($key) { return $this->mung($key); }, array_keys($data));
		$values = array_values($data);

		$sql = 'UPDATE ' . $table . ' SET ' . $this->genSlots(['type' => 'update', 'keys' => $slots]) . ' WHERE `id` = :id LIMIT 1;';
		$this->logger->debug('Generated SQL: ' . $sql);
		$query = $this->db->prepare($sql);
		foreach($data as $name => $value) {
			$query->bindValue($name, $value, self::$typemap[strtolower(gettype($value))]);
		}
		$query->execute();
		return $object;
	}
	public final function replace($object) {
		// TODO make this portable, damnedable upserts...
		list($table, $keys, $data) = $this->massageObject($object);
		$sql = 'INSERT INTO ' . $table . ' (' .
				$this->genSlots(['type' => 'fields', 'keys' => $keys, 'rename' => false]) .
			') VALUES (' .
				$this->genSlots(['type' => 'insert', 'keys' => $keys]) .
			') ON DUPLICATE KEY UPDATE ' .
				$this->genSlots(['type' => 'update', 'keys' => array_map(function ($key) { return $this->mung($key); }, $keys)]) .
			';';
		$this->logger->debug('Generated SQL: ' . $sql);
		$query = $this->db->prepare($sql);
		foreach($data as $name => $value) {
			$query->bindValue($name, $value, self::$typemap[strtolower(gettype($value))]);
		}
		$query->execute();
		$object->id = $this->db->lastInsertId();
		return $object;
	}
	public final function destroy($object) {
		$table = $this->mung($this->getClassFromObject($object));
		$id = $object->id;
		return $this->query('DELETE FROM ' . $table . ' WHERE id = ? LIMIT 1;', [$id]);
	}

	protected final function findByKey($model, $keys, $values, array $options = []) {
		$options = array_merge($options, [
			'limit' => 0,
			'projection' => []
		]);
		$limit = $options['limit'] > 0 ? ' LIMIT ' . $options['limit'] : '';
		$projection = $options['projection'];
		$projection = empty($projection) ? array_keys(get_class_vars($model)) : $projection;
		if(!is_array($keys)) {
			$keys = [$keys];
		}
		if(!is_array($values)) {
			$values = [$values];
		}
		// finding by an array of keys should always use an AND-joined where clause
		$keys = array_map(function ($key) { return $this->mung($key); }, $keys);
		$slots = $this->genSlots(['type' => 'where', 'keys' => $keys, 'link' => 'AND']);
		return $this->query('SELECT ' . $this->genSlots(['type' => 'fields', 'keys' => $projection]) .
			' FROM ' . $this->mung($this->getClassname($model)) . ' WHERE ' . $slots . $limit . ';', array_combine($keys, $values), $model);

	}
	protected final function findById($model, $id, array $projection = []) {
		return $this->findByKey($model, 'id', $id, ['limit' => 1, 'projection' => $projection]);
	}
	protected final function findAll($model, array $options = []) {
		$options = array_merge($options, [
			'limit' => 0,
			'projection' => []
		]);
		$limit = $options['limit'] > 0 ? ' LIMIT ' . $options['limit'] : '';
		$projection = $options['projection'];
		$projection = empty($projection) ? array_keys(get_class_vars($model)) : $projection;
		return $this->query('SELECT ' . $this->genSlots(['type' => 'fields', 'keys' => $projection]) .
			' FROM ' . $this->mung($this->getClassname($model)) . $limit . ';', [], $model);
	}
	protected final function query($sql, array $params, $model = '') {
		$this->logger->debug('Running SQL: ' . $sql);
		$query = $this->db->prepare($sql);
		if(!empty($model)) {
			if(!$query->setFetchMode(self::FETCH_CLASS | self::FETCH_PROPS_LATE, $model)) {
				throw new \LogicException('Failed to set fetch mode to class: ' . $model);
			}
		}
		if($query->execute($params)) {
			return $query;
		}
		return [];
	}
	protected static final function mung($thing) {
		// name munging strategy for now: translate camelCase into camel_case
		return preg_replace_callback('/([A-Z])/', function ($m) { return '_' . strtolower($m[1]); }, lcfirst($thing));
	}
	protected static final function unmung($thing) {
		// name unmunging strategy: undo the above
		return lcfirst(preg_replace_callback('/_([a-z])/', function ($m) { return strtoupper($m[1]); }, $thing));
	}
	public static final function genSlots(array $opts) {
		$opts = array_merge(['type' => 'placeholders', 'keys' => [], 'link' => 'AND', 'rename' => true], $opts);
		$returns = [
			'update' => function () use($opts) {
				return substr(array_reduce($opts['keys'], function (&$result, $k) { $result .= '`' . $k . '` = :' . self::unmung($k) . ', '; return $result; }, ''), 0, -2);
			},
			'insert' => function () use($opts) {
				return substr(array_reduce($opts['keys'], function (&$result, $k) { $result .= ':' . $k . ', '; return $result; }, ''), 0, -2);
			},
			'where' => function () use($opts) {
				$j = $opts['link'];
				$sub = -1*(strlen($j)+2);
				return substr(array_reduce($opts['keys'], function (&$result, $k) use($j) { $result .= '`' . $k . '` = :' . $k . ' ' . $j . ' '; return $result; }, ''), 0, $sub);
			},
			'placeholders' => function () use($opts) {
				return substr(str_repeat('?, ', count($opts['keys'])), 0, -2);
			},
			'fields' => function () use($opts) {
				return implode(',', array_map(function ($key) use($opts) {
					return '`' . (empty($opts['prefix']) ? '' : $opts['prefix'] . '`.`') . self::mung($key) . '`' . ($opts['rename'] ? ' AS `' . self::unmung($key) . '`' : '');
				}, $opts['keys']));
			}
		];

		$slots = $returns[$opts['type']]();
		return $slots;
	}
}
