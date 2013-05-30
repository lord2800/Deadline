<?php
namespace Deadline;

use \Serializable,
	Psr\Log\LoggerInterface;

interface IDataMapper {
	function __construct(DatabaseHandle $dbh, LoggerInterface $logger);
	function transaction(callable $code);
	function persist($object);
	function destroy($object);
}
