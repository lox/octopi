
-- GRANT ALL PRIVILEGES ON graphtest.* TO 'graph'@'localhost' IDENTIFIED BY 'graph';
-- CREATE DATABASE graph;

DROP TABLE IF EXISTS node;
CREATE TABLE node (
	`nodeid` INT NOT NULL auto_increment,
	`json` TEXT,
	PRIMARY KEY (nodeid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS edge;
CREATE TABLE edge (
	`edgeid` INT NOT NULL auto_increment,
	`outid` INT NOT NULL,
	`inid` INT NOT NULL,
	`type` CHAR(64),
	PRIMARY KEY (edgeid)
);

CREATE INDEX out_index USING BTREE ON edge (`out`);
CREATE INDEX in_index USING BTREE ON edge (`in`);

DROP TABLE IF EXISTS edgemeta;
CREATE TABLE edgemeta (
	`edgeid` INT NOT NULL,
	`json` TEXT,
	PRIMARY KEY (edgeid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS index_user;
CREATE TABLE index_user (
	`nodeid` INT NOT NULL,
	`value` VARCHAR(255) NOT NULL,
	INDEX (`value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


