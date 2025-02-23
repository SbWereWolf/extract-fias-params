CREATE TABLE IF NOT EXISTS ADDR_OBJ_ZIP_CODE<?= $suffix ?>
(
REGION   BIGINT,
ADDR_OBJECTID BIGINT,
LEVEL    BIGINT,
ZIP_CODE    TEXT,
TYPENAME TEXT,
NAME     TEXT
);

/*CREATE INDEX ADDR_OBJ_ZIP_CODE<?= $suffix ?>_REGION_ADDR_OBJECTID_ix
ON ZIP_CODE<?= $suffix ?> (REGION,ADDR_OBJECTID);*/

ALTER TABLE ADDR_OBJ_ZIP_CODE
ATTACH PARTITION ADDR_OBJ_ZIP_CODE<?= $suffix ?> FOR
VALUES IN (<?= $suffix ?>);
