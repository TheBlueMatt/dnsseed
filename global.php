<?php

/*
 * These are the various backend functions that can be modified to support whatever backend you wish
*/

// Functions used by bitcoin-scan.php and bitcoin-scan-net.php
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

// Functions used only by bitcoin-scan.php
function add_node_to_dns($ip, $version) {
	global $db, $CONFIG;
	if (empty($db))
		connect_to_db();

	if (!empty($ip) && ip2long($ip) != 0 && is_numeric($version) && $version > 0) {
		$db->query("INSERT INTO `".$CONFIG['MYSQL_BITCOIN_TABLE']."` "
			."(`ipv4`, `accepts_incoming`, `last_check`, `version`, `first_up`) VALUES "
			."('" . ip2long($ip) . "' , b'1', NOW(), '" . $version . "', NOW());");
		$db->query("UPDATE `".$CONFIG['MYSQL_BITCOIN_TABLE']."` SET "
			."`accepts_incoming` = b'1', "
			."`last_check` = NOW(), "
			."`version` = '" . $version . "', "
			."`first_up` = IF(`first_up` IS NULL, NOW(), `first_up`) "
			."WHERE `ipv4` = '" . ip2long($ip) . "' AND `port` = '8333';");
	}

	if (!empty($ip) && ip2long($ip) != 0 && $version >= $CONFIG['MIN_VERSION'])
		$db->query("INSERT INTO `".$CONFIG['MYSQL_PDNS_DB']."`.`".$CONFIG['MYSQL_PDNS_RECORDS_TABLE']."` "
			."(`domain_id`, `name`, `type`, `content`, `ttl`, `prio`, `change_date`) VALUES "
			."('" . $CONFIG['PDNS_DOMAIN_ID'] . "', '" . $CONFIG['PDNS_DOMAIN_NAME'] . "', 'A', '" . $ip . "', '" . $CONFIG['PDNS_RECORD_TTL'] . "', '0', '" . date("Ymd") . "00');");
}

function add_untested_node($ip, $port) {
	global $db, $CONFIG;
	if (empty($db))
		connect_to_db();

	if (!empty($ip) && ip2long($ip) != 0 && !empty($port) && is_numeric($port) && $port != 0) {
		$db->query("INSERT INTO `".$CONFIG['MYSQL_BITCOIN_TABLE']."` "
			."(`ipv4`, `port`) VALUES "
			."('" . ip2long($ip) . "', '" . $port . "');");
		$db->query("UPDATE `".$CONFIG['MYSQL_BITCOIN_TABLE']."` SET "
			."`last_seen` = NOW() WHERE "
			."`ipv4` = '" . ip2long($ip) . "' AND "
			."`port` = '" . $port . "';");
	}
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
			."`domain_id` = '" . $CONFIG['PDNS_DOMAIN_ID'] . "' AND "
			."`name` = '" . $CONFIG['PDNS_DOMAIN_NAME'] . "' AND "
			."`type` = 'A' AND "
			."`content` = '" . $ip . "' AND "
			."`ttl` = '" . $CONFIG['PDNS_RECORD_TTL'] . "' AND "
			."`prio` = '0';");
}

// Functions used only by bitcoin-scan-net.php
function scan_node($ip, $port) {
	exec("nohup ./bitcoin-scan.php ".long2ip($ip).":".$port." > /dev/null 2>/dev/null &");
}

function query_unchecked() {
	global $db, $CONFIG;
	return $result = $db->query("SELECT `ipv4`, `port` FROM `".$CONFIG['MYSQL_BITCOIN_TABLE']."` WHERE `last_check` IS NULL ORDER BY `last_check` DESC;");
}

function query_unaccepting() {
	global $db, $CONFIG;
	return $db->query("SELECT `ipv4`, `port` FROM `".$CONFIG['MYSQL_BITCOIN_TABLE']."` WHERE `last_check` < NOW() - INTERVAL " . $CONFIG['UNACCEP_CHECK_RATE'] . " SECOND AND `accepts_incoming` = b'0' ORDER BY `last_check` DESC;");
}

function query_accepting() {
	global $db, $CONFIG;
	$db->query("SELECT `ipv4`, `port` FROM `".$CONFIG['MYSQL_BITCOIN_TABLE']."` WHERE `last_check` < NOW() - INTERVAL " . $CONFIG['ACCEP_CHECK_RATE'] . " SECOND AND `accepts_incoming` = b'1' ORDER BY `last_check` DESC;");
}

function get_count_of_results($result) {
	return $result->num_rows;
}

function get_assoc_result_row($result) {
	return $result->fetch_assoc();
}

function prune_nodes() {
	$db->query("DELETE FROM `".$CONFIG['MYSQL_BITCOIN_TABLE']."` WHERE `last_seen` < NOW() - INTERVAL " . $CONFIG['PURGE_AGE'] . " SECOND AND `accepts_incoming` = b'0';");
}
?>
