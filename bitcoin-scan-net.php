#!/usr/bin/php

<?php

require("config.php");

function scan_node($ip, $port) {
	exec("nohup ./bitcoin-scan.php ".long2ip($ip).":".$port." > /dev/null 2>/dev/null &");
}

try {
	$db = new mysqli($CONFIG['MYSQL_HOST'], $CONFIG['MYSQL_USER'], $CONFIG['MYSQL_PASS'], $CONFIG['MYSQL_BITCOIN_DB']);
	if ($db->connect_errno)
		throw new Exception($db->connect_error);
	if (empty($db))
		throw new Exception("\$db is empty");

	if ($result = $db->query("SELECT `ipv4`, `port` FROM `".$CONFIG['MYSQL_BITCOIN_TABLE']."` WHERE `last_check` IS NULL;")) {
		$i = 0;
		while ($row = $result->fetch_assoc()) {
			scan_node($row['ipv4'], $row['port']);
			usleep($CONFIG['SLEEP_BETWEEN_CONNECT']);
			$i++;
			if ($i % floor(60 / ($CONFIG['SLEEP_BETWEEN_CONNECT'] / 1000000)) == 0)
				echo $i."/".$result->num_rows." (".$i/$result->num_rows*100."%) (1st of 3 rounds)\n";
		}
	}

	$db->query("DELETE FROM `".$CONFIG['MYSQL_BITCOIN_TABLE']."` WHERE `last_checked` < NOW() - " . $CONFIG['PURGE_AGE'] . " SEC AND `accepts_incoming` = b'0';");
	if ($result = $db->query("SELECT `ipv4`, `port` FROM `".$CONFIG['MYSQL_BITCOIN_TABLE']."` WHERE `last_checked` < NOW() - " . $CONFIG['UNACCEP_CHECK_RATE'] . " SEC AND `accepts_incoming` = b'0';")) {
		$i = 0;
		while ($row = $result->fetch_assoc()) {
			scan_node($row['ipv4'], $row['port']);
			usleep($CONFIG['SLEEP_BETWEEN_CONNECT']);
			$i++;
			if ($i % floor(60 / ($CONFIG['SLEEP_BETWEEN_CONNECT'] / 1000000)) == 0)
				echo $i."/".$result->num_rows." (".$i/$result->num_rows*100."%) (2nd of 3 rounds)\n";
		}
	}

	if ($result = $db->query("SELECT `ipv4`, `port` FROM `".$CONFIG['MYSQL_BITCOIN_TABLE']."` WHERE `last_checked` < NOW() - " . $CONFIG['ACCEP_CHECK_RATE'] . " SEC AND `accepts_incoming` = b'1';")) {
		$i = 0;
		while ($row = $result->fetch_assoc()) {
			scan_node($row['ipv4'], $row['port']);
			usleep($CONFIG['SLEEP_BETWEEN_CONNECT']);
			$i++;
			if ($i % floor(60 / ($CONFIG['SLEEP_BETWEEN_CONNECT'] / 1000000)) == 0)
				echo $i."/".$result->num_rows." (".$i/$result->num_rows*100."%) (3rd of 3 rounds)\n";
		}
	}
} catch (Exception $e) {
	exit;
}
?>
