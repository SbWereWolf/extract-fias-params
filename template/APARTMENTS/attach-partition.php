ALTER TABLE APARTMENTS
ATTACH PARTITION APARTMENTS<?= $suffix ?> FOR
VALUES IN (<?= $suffix ?>)