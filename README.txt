This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.

DB
--

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
  KEY `last_check` (`last_check`),
  KEY `last_seen` (`last_seen`)
);

Running
-------

To bootstrap call php bitcoin-scan.php (the ip of a known-good node)
ie
php bitcoin-scan.php `dig +short bluematt.me`
warning: this node will end up in the database, so call a node by its public ip
followed by repeated calls to php bitcoin-scan-net.php which will fill the dbs
quite quickly.
bitcoin-scan-net.php should also be put on an appropriate cron job, checking
to make sure it isnt already running (which would just duplicate effort)

Bugs/Todo/etc
-------------
TODO: I didnt bother with setting up PDNS to simply pull from the bitcoin db, which
is probably more ideal than its own separate db simply becaues I already have a pdns
db and server configured.
This could be achieved using the appropriate gmysql-*-query settings in pdns.conf

