<?php

/*
 * These are the various backend functions that can be modified to support whatever backend you wish
*/

// MySQL/PowerDNS version
/*
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
function start_db_transaction() {

}

function commit_db_transaction() {

}

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
			."('" . $CONFIG['PDNS_DOMAIN_ID'] . "', '" . $CONFIG['DOMAIN_NAME'] . "', 'A', '" . $ip . "', '" . $CONFIG['PDNS_RECORD_TTL'] . "', '0', '" . date("Ymd") . "00');");
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
			."`name` = '" . $CONFIG['DOMAIN_NAME'] . "' AND "
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
	return $result = $db->query("SELECT `ipv4`, `port` FROM `".$CONFIG['MYSQL_BITCOIN_TABLE']."` WHERE `last_check` IS NULL;");
}

function query_unaccepting() {
	global $db, $CONFIG;
	return $db->query("SELECT `ipv4`, `port` FROM `".$CONFIG['MYSQL_BITCOIN_TABLE']."` WHERE `last_check` < NOW() - INTERVAL " . $CONFIG['UNACCEP_CHECK_RATE'] . " SECOND AND `accepts_incoming` = b'0' ORDER BY `last_check` ASC;");
}

function query_accepting() {
	global $db, $CONFIG;
	return $db->query("SELECT `ipv4`, `port` FROM `".$CONFIG['MYSQL_BITCOIN_TABLE']."` WHERE `last_check` < NOW() - INTERVAL " . $CONFIG['ACCEP_CHECK_RATE'] . " SECOND AND `accepts_incoming` = b'1' ORDER BY `last_check` ASC;");
}

function init_results($result) {
	return $result;
}

function get_count_of_results($result) {
	return $result->num_rows;
}

function get_assoc_result_row($result) {
	return $result->fetch_assoc();
}

function prune_nodes() {
	global $db, $CONFIG;
	$db->query("DELETE FROM `".$CONFIG['MYSQL_BITCOIN_TABLE']."` WHERE `last_seen` < NOW() - INTERVAL " . $CONFIG['PURGE_AGE'] . " SECOND AND `accepts_incoming` = b'0';");
}*/

// SQLite/BIND version
// Functions used by bitcoin-scan.php and bitcoin-scan-net.php
function connect_to_db() {
	global $db, $CONFIG;
	$db = new SQLite3($CONFIG['SQLITE_FILE']);
	if (empty($db))
		die("\$db is empty");
	$db->busyTimeout(5000);
	$db->exec("CREATE TABLE IF NOT EXISTS nodes (
			ipv4 INT NOT NULL,
			port INT NOT NULL DEFAULT 8333,
			last_check INT DEFAULT NULL,
			accepts_incoming INT NOT NULL DEFAULT 0,
			version INT DEFAULT NULL,
			last_seen INT NOT NULL,
			first_up INT DEFAULT NULL,
			PRIMARY KEY (ipv4,port)
		);
		CREATE INDEX IF NOT EXISTS last_seen ON nodes(last_seen);
		CREATE INDEX IF NOT EXISTS last_check ON nodes(last_check);");
}

// Functions used only by bitcoin-scan.php
function start_db_transaction() {
	global $transaction_open, $db, $CONFIG;
	if (empty($db))
		connect_to_db();

	if (!isset($transaction_open))
		$transaction_open = false;

	if (!$transaction_open) {
		if (!$db->exec("BEGIN TRANSACTION;"))
			die ("Transaction create failed");
	}

	$transaction_open = true;
}

function commit_db_transaction() {
	global $transaction_open, $db, $CONFIG;
	if (empty($db))
		connect_to_db();

	if ($transaction_open)
		@$db->exec("COMMIT TRANSACTION;");
	$transaction_open = false;
}

function add_node_to_dns($ip, $version) {
	global $db, $CONFIG;
	if (empty($db))
		connect_to_db();

	if (!empty($ip) && ip2long($ip) != 0 && is_numeric($version) && $version > 0) {
		@$db->exec("INSERT INTO nodes "
			."(ipv4, accepts_incoming, last_check, version, last_seen, first_up) VALUES "
			."(" . ip2long($ip) . " , 1, ".time().", " . $version . ",".time().", ".time().");");
		$db->exec("UPDATE nodes SET "
			."accepts_incoming = 1, "
			."last_check = ".time().", "
			."version = " . $version . ", "
			."last_seen = ".time().", "
			."first_up = CASE WHEN first_up > 0 THEN first_up ELSE ".time()." END "
			."WHERE ipv4 = " . ip2long($ip) . " AND port = 8333;");
	}
}

function add_untested_node($ip, $port) {
	global $db, $CONFIG;
	if (empty($db))
		connect_to_db();

	if (!empty($ip) && ip2long($ip) != 0 && !empty($port) && is_numeric($port) && $port != 0) {
		@$db->exec("INSERT INTO nodes "
			."(ipv4, port, last_seen) VALUES "
			."(" . ip2long($ip) . ", " . $port . ", ".time().");");
		$db->exec("UPDATE nodes SET "
			."last_seen = " . time() . " WHERE "
			."ipv4 = " . ip2long($ip) . " AND "
			."port = " . $port . ";");
	}
}

function remove_node($ip, $port) {
	global $db, $CONFIG;
	if (empty($db))
		connect_to_db();

	if (!empty($ip) && ip2long($ip) != 0 && !empty($port) && is_numeric($port) && $port != 0)
		$db->exec("UPDATE nodes SET "
			."last_check = " . time() . ", "
			."accepts_incoming = 0 WHERE "
			."ipv4 = " . ip2long($ip) . " AND "
			."port = " . $port . ";");
}

// Functions used only by bitcoin-scan-net.php
function scan_node($ip, $port) {
	exec("nohup ./bitcoin-scan.php ".long2ip($ip).":".$port." > /dev/null 2>/dev/null &");
}

function query_unchecked() {
	global $db, $CONFIG;
	return $result = $db->query("SELECT ipv4, port FROM nodes WHERE last_check IS NULL;");
}

function query_unaccepting() {
	global $db, $CONFIG;
	$check_time = time() - $CONFIG['UNACCEP_CHECK_RATE'];
	if ($CONFIG['MIN_UP_TIME_TO_CHECK'] != 0) {
		$up_time = time() - $CONFIG['MIN_UP_TIME_TO_CHECK'];
		return $db->query("SELECT ipv4, port FROM nodes WHERE last_check < " . $check_time . " AND accepts_incoming = 0 AND first_up <= " . $up_time . " ORDER BY last_check ASC;");
	}else{
		return $db->query("SELECT ipv4, port FROM nodes WHERE last_check < " . $check_time . " AND accepts_incoming = 0 ORDER BY last_check ASC;");
	}
}

function init_results($result) {
	$rows = array();
	$row = $result->fetchArray(SQLITE3_ASSOC);
	while (!empty($row)) {
		$rows[] = $row;
		$row = $result->fetchArray(SQLITE3_ASSOC);
	}
	$result->finalize();
	return $rows;
}

function query_accepting() {
	global $db, $CONFIG;
	$current_time = time() - $CONFIG['ACCEP_CHECK_RATE'];
	return $db->query("SELECT ipv4, port FROM nodes WHERE last_check < " . $current_time . " AND accepts_incoming = 1 ORDER BY last_check ASC;");
}

function get_count_of_results($result) {
	return count($result);
}

function get_assoc_result_row(&$result) {
	return array_shift($result);
}

function prune_nodes() {
	global $db, $CONFIG;
	$current_time = time() - $CONFIG['PURGE_AGE'];
	$db->query("DELETE FROM nodes WHERE last_seen < " . $current_time . " AND accepts_incoming = 0;");
}

// Functions used only by fill-dns.php
function get_list_of_nodes_for_dns() {
	global $db, $CONFIG;
	return $db->query("SELECT ipv4 FROM nodes WHERE accepts_incoming = 1 AND port = 8333 AND version >= ".$CONFIG['MIN_VERSION']." ORDER BY last_check DESC LIMIT 20;");
}

// Functions used only by count-nodes.php
function query_version_count() {
	global $db, $CONFIG;
	return $db->query("SELECT COUNT(*), version FROM nodes WHERE accepts_incoming = 1 AND port = 8333 GROUP BY version ORDER BY version;");
}

function query_dns_total() {
	global $db, $CONFIG;
	return $db->query("SELECT COUNT(*) FROM nodes WHERE accepts_incoming = 1 AND port = 8333 AND version >= ".$CONFIG['MIN_VERSION'].";");
}

function query_total() {
	global $db, $CONFIG;
	return $db->query("SELECT COUNT(*) FROM nodes;");
}
?>
