<?php

$CONFIG['MYSQL_HOST']			 = "localhost";
$CONFIG['MYSQL_USER']			 = "bitcoin";
$CONFIG['MYSQL_PASS']			 = "pass";
$CONFIG['MYSQL_BITCOIN_DB']		 = "bitcoin";
$CONFIG['MYSQL_BITCOIN_TABLE']		 = "nodes";
$CONFIG['MYSQL_PDNS_DB']		 = "powerdns";
$CONFIG['MYSQL_PDNS_RECORDS_TABLE']	 = "records";
$CONFIG['PDNS_DOMAIN_ID']		 = "2";
$CONFIG['PDNS_DOMAIN_NAME']		 = "dnsseed.bitcoin.bit";
$CONFIG['PDNS_RECORD_TTL']		 = "60";

// The minimum version to be added to the DNS database
$CONFIG['MIN_VERSION']			 = 31900; // 0.3.19
// Timeout to connect to nodes
$CONFIG['CONNECT_TIMEOUT']		 = 5;
// Rate at which nodes which do not accept incoming connections are rechecked (seconds)
$CONFIG['UNACCEP_CHECK_RATE']		 = 3600;
// Time since last seen nodes which do not accept incoming connections are removed (seconds)
$CONFIG['PURGE_AGE']			 = 604800;
// Rate at which nodes which do accept incoming connections are rechecked (seconds)
$CONFIG['ACCEP_CHECK_RATE']		 = 3600;
// Sleep time between launching each new attempt to connect to a node (seconds)
$CONFIG['SLEEP_BETWEEN_CONNECT']	 = 1;

/*
TODO: I didnt bother with setting up PDNS to simply pull from the bitcoin db, which
is probably more ideal than its own separate db simply becaues I already have a pdns
db and server configured.
This could be achieved using the appropriate gmysql-*-query settings in pdns.conf

The PDNS DB should be the default one according to the PDNS docs
http://doc.powerdns.com/generic-mypgsql-backends.html
Additionally, a low query-cache should be set so that new nodes are always
being returned, and gmysql-any-query should be set to
select content,ttl,prio,type,domain_id,name from records where name='%s' order by rand() limit 10

The bitcoin db should be as follows:

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

-- Database: `bitcoin`

CREATE TABLE IF NOT EXISTS `nodes` (
  `ipv4` int(11) NOT NULL,
  `port` smallint(5) unsigned NOT NULL DEFAULT '8333',
  `last_check` timestamp NULL DEFAULT NULL,
  `accepts_incoming` bit(1) NOT NULL DEFAULT b'0',
  `version` int(11) DEFAULT NULL,
  `last_seen` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `first_up` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`ipv4`,`port`),
  KEY `last_check` (`last_check`)
);


To bootstrap call php bitcoin-scan.php (the ip of a known-good node)
ie
php bitcoin-scan.php `dig +short bluematt.me`
warning: this node will end up in the database, so call a node by its public ip
followed by repeated calls to php bitcoin-scan-net.php which will fill the dbs
quite quickly.
bitcoin-scan-net.php should also be put on an appropriate cron job, checking
to make sure it isnt already running (which would just duplicate effort)
*/

?>
