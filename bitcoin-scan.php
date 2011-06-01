#!/usr/bin/php

<?php

require("config.php");
require("bitcoin-node.php");

function connect_to_db() {
	global $db, $CONFIG;
	try {
		$db = new mysqli($CONFIG['MYSQL_HOST'], $CONFIG['MYSQL_USER'], $CONFIG['MYSQL_PASS'], $CONFIG['MYSQL_BITCOIN_DB']);
		if ($db->connect_errno)
			throw new Exception($db->connect_error);
		if (empty($db))
			throw new Exception("\$db is empty");
	} catch (Exception $e) {
		exit;
	}
}

function add_node_to_dns($ip, $version) {
	global $db, $CONFIG;
	if (empty($db))
		connect_to_db();

	if (!empty($ip) && ip2long($ip) != 0 && is_numeric($version) && $version > 0)
		$db->query("INSERT INTO `".$CONFIG['MYSQL_BITCOIN_TABLE']."` "
			."(`ipv4`, `accepts_incoming`, `last_check`, `version`, `first_up`) VALUES "
			."('" . ip2long($ip) . "' , b'1', NOW(), '" . $version . "', NOW());");

	if (!empty($ip) && ip2long($ip) != 0 && is_numeric($version) && $version > 0)
		$db->query("UPDATE `".$CONFIG['MYSQL_BITCOIN_TABLE']."` SET "
			."`accepts_incoming` = b'1', "
			."`last_check` = NOW(), "
			."`version` = '" . $version . "', "
			."`first_up` = IF(`first_up` IS NULL, NOW(), `first_up`) "
			."WHERE `ipv4` = '" . ip2long($ip) . "' AND `port` = '8333';");

	if (!empty($ip) && ip2long($ip) != 0 && $version >= $CONFIG['MIN_VERSION'])
		$db->query("INSERT INTO `".$CONFIG['MYSQL_PDNS_DB']."`.`".$CONFIG['MYSQL_PDNS_RECORDS_TABLE']."` "
			."(`domain_id`, `name`, `type`, `content`, `ttl`, `prio`, `change_date`) VALUES "
			."('" . $CONFIG['PDNS_DOMAIN_ID'] . "', '" . $CONFIG['PDNS_DOMAIN_NAME'] . "', 'A', '" . $ip . "', '" . $CONFIG['PDNS_RECORD_TTL'] . "', '0', '" . date("Ymd") . "00');");
}

function add_untested_node($ip, $port) {
	global $db, $CONFIG;
	if (empty($db))
		connect_to_db();

	if (!empty($ip) && ip2long($ip) != 0 && !empty($port) && is_numeric($port) && $port != 0)
		$db->query("INSERT INTO `".$CONFIG['MYSQL_BITCOIN_TABLE']."` "
			."(`ipv4`, `port`) VALUES "
			."('" . ip2long($ip) . "', '" . $port . "');");

	if (!empty($ip) && ip2long($ip) != 0 && !empty($port) && is_numeric($port) && $port != 0)
		$db->query("UPDATE `".$CONFIG['MYSQL_BITCOIN_TABLE']."` SET "
			."`last_seen` = NOW() WHERE "
			."`ipv4` = '" . ip2long($ip) . "' AND "
			."`port` = '" . $port . "';");
}

function remove_node($ip, $port) {
	global $db, $CONFIG;
	if (empty($db))
		connect_to_db();

	if (!empty($ip) && ip2long($ip) != 0 && !empty($port) && is_numeric($port) && $port != 0)
		$db->query("UPDATE `".$CONFIG['MYSQL_BITCOIN_TABLE']."` SET "
			."`last_check` = NOW(), "
			."`accepts_incoming` = b'0' WHERE "
			."`ipv4` = '" . ip2long($ip) . "' AND "
			."`port` = '" . $port . "';");

	if (!empty($ip) && ip2long($ip) != 0)
		$db->query("DELETE FROM `".$CONFIG['MYSQL_PDNS_DB']."`.`".$CONFIG['MYSQL_PDNS_RECORDS_TABLE']."` WHERE "
			."`domain_id` = '" . $CONFIG['PDNS_DOMAIN_ID'] . "', "
			."`name` = '" . $CONFIG['PDNS_DOMAIN_NAME'] . "', "
			."`type` = 'A', "
			."`content` = '" . $ip . "', "
			."`ttl` = '" . $CONFIG['PDNS_RECORD_TTL'] . "', "
			."`prio` = '0';");
}

if ($argc != 2)
	exit;

$arr = explode(":", $argv[1]);
if (count($arr) > 2 || count($arr) == 0)
	exit;

$port = count($arr)==1 ? 8333 : $arr[1];

try {
	$origNode = new Bitcoin\Node($arr[0], $port, $CONFIG['CONNECT_TIMEOUT']);

	if ($port == 8333)
		add_node_to_dns($arr[0], $origNode->getVersion());

	$nodes = $origNode->getAddr();
	foreach ($nodes as &$node)
		add_untested_node($node["ipv4"], $node["port"]);
} catch (Exception $e) {
	remove_node($arr[0], $port);
}

exit;

/*
$c = new Node('127.0.0.1');
var_dump($c->getVersionStr());
echo "completed, waiting...\n";

var_dump($c->getAddr());

while(true) {
	$pkt = $c->readPacket();
	if ($pkt) {
		echo $pkt['type'].': '.bin2hex($pkt['payload'])."\n";
	} else {
		exit;
	}
}*/
?>
