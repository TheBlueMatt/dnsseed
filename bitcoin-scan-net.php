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

	$time = floor(60 / ($CONFIG['SLEEP_BETWEEN_CONNECT'] / 1000000));

	if (!isset($argv[1]) || $argv[1] == "unchecked") {
		if ($result = $db->query("SELECT `ipv4`, `port` FROM `".$CONFIG['MYSQL_BITCOIN_TABLE']."` WHERE `last_check` IS NULL ORDER BY `last_check` DESC;")) {
			$i = 0;
			if ($i % $time == 0 && $result->num_rows != 0)
				echo $i."/".$result->num_rows." (".$i*100/$result->num_rows."%) (1st of 3 rounds)\n";
			while ($row = $result->fetch_assoc()) {
				scan_node($row['ipv4'], $row['port']);
				usleep($CONFIG['SLEEP_BETWEEN_CONNECT']);
				$i++;
				if ($i % $time == 0)
					echo $i."/".$result->num_rows." (".$i*100/$result->num_rows."%) (1st of 3 rounds)\n";
			}
		}
	}

	if (!isset($argv[1]) || $argv[1] == "unaccepting") {
		$db->query("DELETE FROM `".$CONFIG['MYSQL_BITCOIN_TABLE']."` WHERE `last_seen` < NOW() - INTERVAL " . $CONFIG['PURGE_AGE'] . " SECOND AND `accepts_incoming` = b'0';");
		if ($result = $db->query("SELECT `ipv4`, `port` FROM `".$CONFIG['MYSQL_BITCOIN_TABLE']."` WHERE `last_check` < NOW() - INTERVAL " . $CONFIG['UNACCEP_CHECK_RATE'] . " SECOND AND `accepts_incoming` = b'0' ORDER BY `last_check` DESC;")) {
			$i = 0;
			if ($i % $time == 0 && $result->num_rows != 0)
				echo $i."/".$result->num_rows." (".$i*100/$result->num_rows."%) (2nd of 3 rounds)\n";
			while ($row = $result->fetch_assoc()) {
				scan_node($row['ipv4'], $row['port']);
				usleep($CONFIG['SLEEP_BETWEEN_CONNECT']);
				$i++;
				if ($i % $time == 0)
					echo $i."/".$result->num_rows." (".$i*100/$result->num_rows."%) (2nd of 3 rounds)\n";
			}
		}
	}

	if (!isset($argv[1]) || $argv[1] == "accepting") {
		if ($result = $db->query("SELECT `ipv4`, `port` FROM `".$CONFIG['MYSQL_BITCOIN_TABLE']."` WHERE `last_check` < NOW() - INTERVAL " . $CONFIG['ACCEP_CHECK_RATE'] . " SECOND AND `accepts_incoming` = b'1' ORDER BY `last_check` DESC;")) {
			$i = 0;
			if ($i % $time == 0 && $result->num_rows != 0)
				echo $i."/".$result->num_rows." (".$i*100/$result->num_rows."%) (3rd of 3 rounds)\n";
			while ($row = $result->fetch_assoc()) {
				scan_node($row['ipv4'], $row['port']);
				usleep($CONFIG['SLEEP_BETWEEN_CONNECT']);
				$i++;
				if ($i % $time == 0)
					echo $i."/".$result->num_rows." (".$i*100/$result->num_rows."%) (3rd of 3 rounds)\n";
			}
		}
	}
} catch (Exception $e) {
	exit;
}
?>
