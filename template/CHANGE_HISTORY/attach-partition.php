ALTER TABLE CHANGE_HISTORY
ATTACH PARTITION CHANGE_HISTORY<?= $suffix ?> FOR
VALUES IN (<?= $suffix ?>)
