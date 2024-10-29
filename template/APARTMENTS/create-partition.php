CREATE TABLE IF NOT EXISTS APARTMENTS<?= $suffix ?>
(
REGION BIGINT,
ID BIGINT,
OBJECTID BIGINT,
OBJECTGUID TEXT,
NUMBER TEXT,
APARTTYPE BIGINT,
OPERTYPEID BIGINT,
PREVID BIGINT,
NEXTID BIGINT,
UPDATEDATE DATE,
STARTDATE DATE,
ENDDATE DATE,
ISACTUAL BIGINT,
ISACTIVE BIGINT
);

/*CREATE UNIQUE INDEX APARTMENTS<?= $suffix ?>_REGION_ID_ux
ON APARTMENTS<?= $suffix ?> (REGION,ID);*/

ALTER TABLE APARTMENTS
ATTACH PARTITION APARTMENTS<?= $suffix ?> FOR
VALUES IN (<?= $suffix ?>);