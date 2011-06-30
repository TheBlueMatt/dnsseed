#!/usr/bin/php

<?php
require("config.php");
require("global.php");

$file = fopen($CONFIG['BIND_RECORD_FILE'], "w");
$headerfile = fopen($CONFIG['BIND_HEADER_FILE'], "r");
if(flock($file, LOCK_EX)) {
	stream_copy_to_stream($headerfile, $file);
	fclose($headerfile);
	connect_to_db();
	$result = get_list_of_nodes_for_dns();
	$row = get_assoc_result_row($result);
	while (!empty($row)) {
		fwrite($file, $CONFIG['DOMAIN_NAME'] . "\tIN\tA\t" . long2ip($row['ipv4'] . "\n"));
		$row = get_assoc_result_row($result);
	}
	flock($file, LOCK_UN);
	fclose($file);
}
?>
