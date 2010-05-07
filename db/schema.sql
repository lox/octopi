
-- GRANT ALL PRIVILEGES ON graphtest.* TO 'graph'@'localhost' IDENTIFIED BY 'graph';
-- CREATE DATABASE graph;

DROP TABLE IF EXISTS node;
CREATE TABLE node (
	`nodeid` INT UNSIGNED NOT NULL auto_increment,
	`json` TEXT,
	PRIMARY KEY (nodeid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS edge;
CREATE TABLE edge (
	`edgeid` INT UNSIGNED NOT NULL auto_increment,
	`outid` INT UNSIGNED NOT NULL,
	`inid` INT UNSIGNED NOT NULL,
	`type` CHAR(32),
	`inferred` TINYINT(1) DEFAULT '0',
	PRIMARY KEY (edgeid)
);

CREATE INDEX out_index USING BTREE ON edge (`outid`);
CREATE INDEX in_index USING BTREE ON edge (`inid`);

DROP TABLE IF EXISTS edgemeta;
CREATE TABLE edgemeta (
	`edgeid` INT UNSIGNED NOT NULL,
	`json` TEXT,
	PRIMARY KEY (edgeid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

