ALTER SYSTEM SET AUTOVACUUM TO FALSE;
SELECT PG_RELOAD_CONF();

truncate table
    addhousetypes
    ,addr_obj_params
    ,addressobjects
    ,addressobjecttypes
    ,adm_hierarchy
    ,apartmenttypes
    ,houses_params
    ,housetypes
    ,mun_hierarchy
    ,ndockinds
    ,ndoctypes
    ,objectlevels
    ,operationtypes
    ,paramtypes
    ,roomtypes
;

ALTER SYSTEM SET AUTOVACUUM TO TRUE;
SELECT PG_RELOAD_CONF();

VACUUM FULL VERBOSE ANALYZE;

ALTER SYSTEM SET autovacuum_vacuum_scale_factor = 0.00001;
ALTER SYSTEM SET autovacuum_vacuum_insert_scale_factor = 0.00001;
ALTER SYSTEM SET autovacuum_analyze_scale_factor = 0.00001;
ALTER SYSTEM SET AUTOVACUUM TO TRUE;
SELECT PG_RELOAD_CONF();

VACUUM FULL VERBOSE ANALYZE;

truncate table houses;

select region, housetype, addtype1, addtype2
from houses
group by region, housetype, addtype1, addtype2
order by region, housetype, addtype1, addtype2
;
select addtype2
from houses
group by addtype2
order by addtype2
;

insert into zip_code
(region, h_objectid, zip_code, addr_objectid, typename, name, level, housenum, housetype, addnum1, addtype1, addnum2,
 addtype2)
select hp.region,
       hp.objectid,
       hp.value,
       s.objectid,
       s.typename,
       s.name,
       s.level,
       h.housenum,
       htt.descr,
       h.addnum1,
       ht1.descr,
       h.addnum2,
       ht2.descr
from houses h
         join houses_params hp on
    hp.region = h.region
        and hp.objectid = h.objectid
         join mun_hierarchy mh_h
              on h.region = mh_h.region
                  and h.objectid = mh_h.objectid
                  and mh_h.isactive = 1
         join mun_hierarchy mh_s
              on mh_h.region = mh_s.region
                  and mh_h.parentobjid = mh_s.objectid
                  and mh_s.isactive = 1
         join addressobjects s on
    mh_s.region = s.region
        and mh_s.objectid = s.objectid
        and s.isactive = 1
         left join housetypes htt on h.housetype = htt.id
         left join addhousetypes ht1 on h.addtype1 = ht1.id
         left join addhousetypes ht2 on h.addtype2 = ht2.id
where h.region in (01, 02, 03, 04, 05, 06, 07, 08, 09, 10, 11, 12, 13, 14, 15, 16, 17, 18)
  and h.isactive = 1
  and hp.typeid = 5
  and current_date::date between hp.startdate and hp.enddate
order by hp.region,
         s.objectid,
         h.housenum
;

truncate table zip_code;

create index houses_params_region_typeid_startdate_enddate_ix
    on gar.houses_params (region asc, typeid asc, startdate desc, enddate asc);

CREATE INDEX HOUSES_PARAMS_REGION_TYPEID_OBJECTID_STARTDATE_ENDDATE_UX
    ON HOUSES_PARAMS (REGION,TYPEID,OBJECTID,STARTDATE,ENDDATE);

VACUUM FULL VERBOSE ANALYZE houses_params;

CREATE INDEX MUN_HIERARCHY_REGION_OBJECTID_ISACTIVE_IX
    ON MUN_HIERARCHY (REGION,OBJECTID,ISACTIVE);
CREATE INDEX MUN_HIERARCHY_REGION_PARENTOBJID_IX
    ON MUN_HIERARCHY (REGION,PARENTOBJID);


EXPLAIN
WITH R AS (select region
           from (VALUES (77)) AS T (region)),
     C AS (select current_date::date as curr_date)
select distinct  h.region,
                 h.objectid,
                 hp.value,
                 s.objectid,
                 s.typename,
                 s.name,
                 s.level,
                 htt.descr,
                 h.housenum,
                 ht1.descr,
                 h.addnum1,
                 ht2.descr,
                 h.addnum2
from C,
     R r
         join houses h on r.region = h.region
         join houses_params hp on
         hp.region = h.region
             and hp.typeid = 5
             and hp.objectid = h.objectid
         join mun_hierarchy mh_h
              on h.region = mh_h.region
                  and h.objectid = mh_h.objectid
         join mun_hierarchy mh_s
              on mh_h.region = mh_s.region
                  and mh_h.parentobjid = mh_s.objectid
         join addressobjects s on
         mh_s.region = s.region
             and mh_s.objectid = s.objectid
         left join housetypes htt on h.housetype = htt.id
         left join addhousetypes ht1 on h.addtype1 = ht1.id
         left join addhousetypes ht2 on h.addtype2 = ht2.id
where
    h.region=77 and h.objectid=49393049
  and h.isactive = 1
  and C.curr_date between hp.startdate and hp.enddate
  and mh_h.isactive = 1
  and mh_s.isactive = 1
  and s.isactive = 1
order by h.region,
         hp.value,
         s.objectid,
         h.housenum
;

truncate table zip_code;

truncate table addr_obj_zip_code;

update city_indexes
set zip_code=code::text
where code =code;

insert
into addr_obj_zip_code
(region,  level, zip_code, typename, name, city)
select region,  level, zip_code, typename, name, (select city_name from city_indexes c where c.zip_code=z.zip_code)
from zip_code z
group by region,  level, zip_code, typename, name
order by region, zip_code, name
;

update zip_code z
set city_name=(select city_name from city_indexes c where c.zip_code=z.zip_code)
where h_objectid =h_objectid;

select count(*) zip_code_ from zip_code; -- 30 327 498

select count(*) addr_obj_zip_code from addr_obj_zip_code; -- 1 090 134

-- string_agg(employee, ', ' ORDER BY employee)

select count(*) addr_obj_zip_code from addr_obj_zip_code;

CREATE TABLE IF NOT EXISTS GROUP_ADDR_OBJ_ZIP_CODE
(
    REGION   BIGINT,
    CITY_NAME TEXT,
    ZIP_CODE    TEXT,
    NAME     TEXT
)
;

insert into group_addr_obj_zip_code
(REGION, CITY_NAME, ZIP_CODE, NAME)
select
    REGION
     , (select city_name from city_indexes c where c.zip_code=a.zip_code)
     , ZIP_CODE
     , string_agg(NAME, ', ' ORDER BY NAME)
from addr_obj_zip_code a
group by REGION, ZIP_CODE
;

CREATE TABLE IF NOT EXISTS GROUP_HOUSES_ZIP_CODE
(
    REGION    BIGINT,
    CITY_NAME TEXT,
    ZIP_CODE  TEXT,
    TYPENAME  TEXT,
    NAME      TEXT,
    HOUSENUM  TEXT
)
;

insert into GROUP_HOUSES_ZIP_CODE
(REGION, CITY_NAME, ZIP_CODE, TYPENAME, NAME, HOUSENUM)
select REGION,
       (select city_name from city_indexes c where c.zip_code = a.zip_code),
       ZIP_CODE,
       TYPENAME,
       NAME,
       string_agg(
               CONCAT(housenum, ' '
                      housetype, ' '
                      addnum1, ' '
                      addtype1, ' '
                      addnum2, ' '
                      addtype2)
           , ', ' ORDER BY NAME)
from zip_code a
group by REGION, ZIP_CODE, TYPENAME, NAME
;


select REGION,
       (select city_name from city_indexes c where c.zip_code = a.zip_code) city_name,
       ZIP_CODE,
       TYPENAME,
       NAME,
       string_agg(
               REPLACE(
                       REPLACE(
                               REPLACE(
                                       REPLACE(
                                               CONCAT(housenum, ' ',
                                                      housetype, ' ',
                                                      addtype1, ' ',
                                                      addnum1, ' ',
                                                      addtype2, ' ',
                                                      addnum2)
                                           , '  '
                                           , ' '
                                       )
                                   , '  '
                                   , ' ')
                           , '  '
                           , ' ')
                   , '  '
                   , ' ')

           , ',' ORDER BY housenum) housenum
from zip_code a
group by REGION, ZIP_CODE, TYPENAME, NAME
;

select REPLACE(REPLACE('1aaa;10aaa;12aaa;14aaa','aa','a'),'aa','a');
;

select count(*) from group_houses_zip_code;

-- EXPLAIN
WITH R AS (select region
           from (VALUES (01)
                      , (02)
                      , (03)
                      , (04)
                      , (05)
                      , (06)
                      , (07)
                      , (08)
                      , (09)
                      , (10)
                      , (11)
                      , (12)
                      , (13)
                      , (14)
                      , (15)
                      , (16)
                      , (17)
                      , (18))
                    AS T (region)),
     C AS (select current_date::date as curr_date)
insert
into zip_code
(region, h_objectid, zip_code, addr_objectid, typename, name, level, housetype, housenum, addtype1, addnum1,
 addtype2, addnum2)
select distinct h.region,
                h.objectid,
                hp.value,
                s.objectid,
                s.typename,
                s.name,
                s.level,
                htt.descr,
                h.housenum,
                ht1.descr,
                h.addnum1,
                ht2.descr,
                h.addnum2
from C ,R r
            join houses h on r.region = h.region
            join houses_params hp on
    hp.region = h.region
        and hp.typeid = 5
        and hp.objectid = h.objectid
            join mun_hierarchy mh_h
                 on h.region = mh_h.region
                     and h.objectid = mh_h.objectid
            join mun_hierarchy mh_s
                 on mh_h.region = mh_s.region
                     and mh_h.parentobjid = mh_s.objectid
            join addressobjects s on
    mh_s.region = s.region
        and mh_s.objectid = s.objectid
            left join housetypes htt on h.housetype = htt.id
            left join addhousetypes ht1 on h.addtype1 = ht1.id
            left join addhousetypes ht2 on h.addtype2 = ht2.id
where h.isactive = 1
  and C.curr_date between hp.startdate and hp.enddate
  and mh_h.isactive = 1
  and mh_s.isactive = 1
  and s.isactive = 1
order by h.region,
         hp.value,
         s.objectid,
         h.housenum
;

WITH R AS (select region
           from (VALUES (01),
                        (02),
                        (03),
                        (04),
                        (05),
                        (06),
                        (07),
                        (08),
                        (09),
                        (10),
                        (11),
                        (12),
                        (13),
                        (14),
                        (15),
                        (16),
                        (17),
                        (18)) AS T (region))
insert
into addr_obj_zip_code
(region,  level, zip_code, typename, name, city)
select z.region,  level, zip_code, typename, name, (select city_name from city_indexes c where c.zip_code=z.zip_code)
from R r
         join zip_code z on r.region = z.region

group by z.region,  level, zip_code, typename, name
order by z.region, zip_code, name
;

update zip_code z
set city_name=(select city_name from city_indexes c where c.zip_code=z.zip_code)
where h_objectid =h_objectid;

WITH R AS (select region
           from (VALUES (01),
                        (02),
                        (03),
                        (04),
                        (05),
                        (06),
                        (07),
                        (08),
                        (09),
                        (10),
                        (11),
                        (12),
                        (13),
                        (14),
                        (15),
                        (16),
                        (17),
                        (18)) AS T (region))
update zip_code z
set city_name=(select city_name from city_indexes c where c.zip_code=z.zip_code)
from R
where R.region=z.region and  h_objectid =h_objectid;


WITH R AS (select region
           from (VALUES (01),
                        (02),
                        (03),
                        (04),
                        (05),
                        (06),
                        (07),
                        (08),
                        (09),
                        (10),
                        (11),
                        (12),
                        (13),
                        (14),
                        (15),
                        (16),
                        (17),
                        (18)) AS T (region))
insert into GROUP_HOUSES_ZIP_CODE
(REGION, CITY_NAME, ZIP_CODE, TYPENAME, NAME, HOUSENUM)
select R.REGION,
       (select city_name from city_indexes c where c.zip_code = a.zip_code),
       ZIP_CODE,
       TYPENAME,
       NAME,
       REPLACE(
               string_agg(
                       REPLACE(
                               REPLACE(
                                       REPLACE(
                                               REPLACE(
                                                       CONCAT(housenum, ' ',
                                                              housetype, ' ',
                                                              addtype1, ' ',
                                                              addnum1, ' ',
                                                              addtype2, ' ',
                                                              addnum2)
                                                   , '  '
                                                   , ' '
                                               )
                                           , '  '
                                           , ' ')
                                   , '  '
                                   , ' ')
                           , '  '
                           , ' ')
                   , ',' ORDER BY housenum)
           , ' ,'
           , ', ')
from R join zip_code a on R.region = a.region
group by R.REGION, ZIP_CODE, TYPENAME, NAME
;