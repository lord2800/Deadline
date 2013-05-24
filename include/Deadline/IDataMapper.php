<?php
namespace Deadline;

use \Serializable,
	Psr\Log\LoggerInterface;

interface IDataMapper {
	function __construct(App $app, IStorage $store, LoggerInterface $logger);
	function transaction(callable $code);
	function persist($object);
	function destroy($object);
	function findByKey($model, $key, $value, array $options);
}
