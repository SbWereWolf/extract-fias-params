ALTER TABLE STEADS_PARAMS
ATTACH PARTITION STEADS_PARAMS<?= $suffix ?> FOR
VALUES IN (<?= $suffix ?>)