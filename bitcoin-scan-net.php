<?php

require("config.php");

function scan_node($ip, $port) {
	exec("nohup php5 ./bitcoin-scan.php ".long2ip($ip).":".$port." > /dev/null 2>/dev/null &");
}

try {
	$db = new mysqli($CONFIG['MYSQL_HOST'], $CONFIG['MYSQL_USER'], $CONFIG['MYSQL_PASS'], $CONFIG['MYSQL_BITCOIN_DB']);
	if ($db->connect_errno)
		throw new Exception($db->connect_error);
	if (empty($db))
		throw new Exception("\$db is empty");

	if ($result = $db->query("SELECT `ipv4`, `port` FROM `".$CONFIG['MYSQL_BITCOIN_TABLE']."` WHERE `last_check` IS NULL;")) {
		while ($row = $result->fetch_assoc()) {
			scan_node($row['ipv4'], $row['port']);
			sleep(1);
		}
	}

	$db->query("DELETE FROM `".$CONFIG['MYSQL_BITCOIN_TABLE']."` WHERE `last_checked` < NOW() - " . $CONFIG['PURGE_AGE'] . " SEC AND `accepts_incoming` = b'0';");
	if ($result = $db->query("SELECT `ipv4`, `port` FROM `".$CONFIG['MYSQL_BITCOIN_TABLE']."` WHERE `last_checked` < NOW() - " . $CONFIG['UNACCEP_CHECK_RATE'] . " SEC AND `accepts_incoming` = b'0';")) {
		while ($row = $result->fetch_assoc()) {
			scan_node($row['ipv4'], $row['port']);
			sleep(1);
		}
	}

	if ($result = $db->query("SELECT `ipv4`, `port` FROM `".$CONFIG['MYSQL_BITCOIN_TABLE']."` WHERE `last_checked` < NOW() - " . $CONFIG['ACCEP_CHECK_RATE'] . " SEC AND `accepts_incoming` = b'1';")) {
		while ($row = $result->fetch_assoc()) {
			scan_node($row['ipv4'], $row['port']);
			sleep(1);
		}
	}
} catch (Exception $e) {
	exit;
}
?>
