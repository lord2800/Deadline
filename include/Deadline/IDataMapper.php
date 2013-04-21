<?php
namespace Deadline;

use \Serializable,
	Psr\Log\LoggerInterface;

interface IDataMapper {
	function __construct(App $app, IStorage $store, LoggerInterface $logger);
	function persist(Serializable $object);
	function destroy(Serializable $object);
	function findByKey($model, $key, $value, array $options);
}
