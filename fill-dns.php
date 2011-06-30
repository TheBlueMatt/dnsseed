#!/usr/bin/php

<?php
require("config.php");
require("global.php");

$file = fopen($CONFIG['BIND_RECORD_FILE'], "r+");
$headerfile = fopen($CONFIG['BIND_HEADER_FILE'], "r");
if(flock($file, LOCK_EX)) {
	$i = 0;
	$serial = 0;
	while (($line = fgets($file)) !== false && $i < 11) {
		if (strpos($line, "; Serial") !== false) {
			sscanf($line, "\t\t\t%i\t\t; Serial\n", $serial);
			$serial++;
			break;
		}
		$i++;
	}
	fseek($file, 0);
	ftruncate($file, 0);
	while (($line = fgets($headerfile)) !== false) {
		if (strpos($line, "; Serial") !== false)
			fwrite($file, "\t\t\t".$serial."\t\t; Serial\n");
		else
			fwrite($file, $line);
	}
	fclose($headerfile);
	connect_to_db();
	$result = get_list_of_nodes_for_dns();
	$result = init_results($result);
	$row = get_assoc_result_row($result);
	while (!empty($row)) {
		fwrite($file, $CONFIG['DOMAIN_NAME'] . ".\tIN\tA\t" . long2ip($row['ipv4']) . "\n");
		$row = get_assoc_result_row($result);
	}
	flock($file, LOCK_UN);
	fclose($file);
}
?>
