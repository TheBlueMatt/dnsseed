#!/usr/bin/php

<?php
require("config.php");
require("global.php");

connect_to_db();
$result = query_version_count();
echo "Nodes viable for DNS (including low version)";
$result = init_results($result);
while($row = get_assoc_result_row($result)) {
	echo $row["version"]."\t".$row["COUNT(*)"]."\n";
}
$result = query_dns_total();
$result = init_results($result);
$row = get_assoc_result_row($result);
echo "In DNS:\t".$row['COUNT(*)']."\n";
$result = query_total();
$result = init_results($result);
$row = get_assoc_result_row($result);
echo "Total:\t".$row['COUNT(*)']."\n";
?>

