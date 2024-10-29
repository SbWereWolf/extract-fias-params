CREATE TABLE IF NOT EXISTS NORMDOCS<?= $suffix ?>
(
REGION BIGINT,
ID BIGINT,
NAME TEXT,
DATE DATE,
NUMBER TEXT,
TYPE BIGINT,
KIND BIGINT,
UPDATEDATE DATE,
ORGNAME TEXT,
REGNUM TEXT,
REGDATE DATE,
ACCDATE DATE,
COMMENT TEXT
);

/*CREATE UNIQUE INDEX NORMDOCS<?= $suffix ?>_REGION_ID_ux
ON NORMDOCS<?= $suffix ?> (REGION,ID);*/

ALTER TABLE NORMDOCS
ATTACH PARTITION NORMDOCS<?= $suffix ?> FOR VALUES IN (<?= $suffix ?>);