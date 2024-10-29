CREATE TABLE IF NOT EXISTS ROOMTYPES
(
ID BIGINT,
NAME TEXT,
SHORTNAME TEXT,
DESCR TEXT,
UPDATEDATE DATE,
STARTDATE DATE,
ENDDATE DATE,
ISACTIVE BIGINT
);

/*CREATE UNIQUE INDEX ROOMTYPES_ID_ux
ON ROOMTYPES (ID);*/