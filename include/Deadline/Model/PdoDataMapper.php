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
		$parts = explode('\\', get_class($object));
		return end($parts);
	}

	/* 
	 * NB: this function is limited; it assumes your table has an id field that is unique, and does not
	 * handle composite keys in any way whatsoever, nor does it attempt to handle foreign keys--you must
	 * pre-process that info elsewhere before persisting, and it assumes you want to persist all public
	 * properties on your object.
	 */
	public final function persist($object) {
		$table = $this->mung($this->getClassFromObject($object));
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
		$projection = empty($projection) ? '*' : '`' . implode('`,`', $projection) . '`';
		if(!is_array($keys)) {
			$keys = [$keys];
		}
		// finding by an array of keys should always use an AND-joined where clause
		$keys = $this->genSlots(['type' => 'where', 'keys' => $keys, 'join' => 'AND']);
		return $this->query('SELECT ' . $projection . ' FROM ' . $this->mung($model) . ' WHERE ' . $keys . $limit . ';', [$value], $model);

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
		$projection = empty($projection) ? '*' : '`' . implode('`,`', $projection) . '`';
		return $this->query('SELECT ' . $projection . ' FROM ' . $this->mung($model) . $limit . ';');
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
		$opts = array_merge(['type' => 'placeholders', 'keys' => [], 'join' => 'AND'], $opts);
		$returns = [
			'insert' => function () use($opts) {
				$values = '';
				array_walk($opts['keys'], function ($value, $name) use($values) { $values .= '`' . $name . '` = :' . $name . ', '; });
				return substr($values, 0, -2);
			},
			// TODO update is a special case of where in that the join is a ,
			'update' => function () use($opts) {
				return substr(array_reduce($opts['keys'], function (&$result, $k) { $result .= ':' . $k . ', '; return $result; }, ''), 0, -2);
			},
			'where' => function () use($opts) {
				return substr(array_reduce($opts['keys'], function (&$result, $k) { $result .= ':' . $k . ' = ? ' . $opts['join'] . ' '; return $result; }, ''), 0, -4);
			},
			'placeholders' => function () use($opts) {
				return substr(str_repeat('?, ', count($opts['keys'])), 0, -2);
			}
		];

		return $returns[$opts['type']]();
	}
}
