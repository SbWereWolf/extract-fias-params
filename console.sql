CREATE TABLESPACE fias_data
    OWNER postgres
    LOCATION '/mnt/pg-storage/data'
;

SET autovacuum_vacuum_scale_factor = 0.0001;
SET autovacuum_vacuum_insert_scale_factor = 0.0001;
SET autovacuum_analyze_scale_factor = 0.0001;

VACUUM FULL VERBOSE ANALYZE;

select *
from adm_hierarchy
where region = 1
  and objectid = 45894603;

WITH RECURSIVE ctename AS (
    SELECT 1 as level, id, region, objectid, parentobjid
    FROM adm_hierarchy child
    WHERE region = 1
      and objectid = 1472973
    UNION ALL
    SELECT 1 + ctename.level as level, parent.id, parent.region, parent.objectid, parent.parentobjid
    FROM adm_hierarchy parent
             JOIN ctename ON parent.region = ctename.region and parent.objectid = ctename.parentobjid
)
SELECT *
FROM ctename c
         left join addressobjects a on c.region=a.region and c.objectid = a.objectid
;


SELECT *
FROM adm_hierarchy child
WHERE region = 1
  and objectid = 1472973
;

select 'H-- 5 |Почтовый индекс',T1.value from (select value from houses_params01 where typeid=5 limit 1) T1-- 5 |Почтовый индекс
union
select 'H-- 6 |ОКАТО',T2.value from (select value from houses_params01 where typeid=6 limit 1)T2-- 6 |ОКАТО
union
select 'H-- 10|Код КЛАДР',T3.value from (select value from houses_params01 where typeid=10 limit 1)T3-- 10|Код КЛАДР
union
select 'A-- 5 |Почтовый индекс',T4.value from (select value from addr_obj_params01 where typeid=5 limit 1)T4-- 5 |Почтовый индекс
union
select 'A-- 6 |ОКАТО',T5.value from (select value from addr_obj_params01 where typeid=6 limit 1)T5-- 6 |ОКАТО
union
select 'A-- 10|Код КЛАДР',T6.value from (select value from addr_obj_params01 where typeid=10 limit 1)T6-- 10|Код КЛАДР
;


select
    a.typename,
    a.name,
    p.typeid,
    p.value
from
    addressobjects A
        join addr_obj_params p on a.region=p.region and A.objectid =p.objectid and a.isactive=1
where
    a.level in (1,4,5,6)
  and p.typeid in(6,10)
  and '2024-10-30'::date between p.startdate and p.enddate

limit 50
;

WITH RECURSIVE ah AS (
    SELECT prev.region, prev.parentobjid,prev.objectid, z.value,null as typename,null as name,0::bigint as level
    FROM adm_hierarchy prev
             join zip_code z on z.region=prev.region and z.objectid=prev.objectid
    where
        prev.isactive=1
    /*and prev.region=1 and prev.objectid between 1472973 and 1710545*/
    UNION ALL
    SELECT next.region, next.parentobjid,next.objectid,  ah.value,a.typename,a.name,a.level
    FROM adm_hierarchy next
    JOIN ah ON next.region = ah.region and next.objectid = ah.parentobjid and next.isactive=1
    join addressobjects a on next.region=a.region and next.objectid = a.objectid and a.isactive=1
    where a.level>5 --6
)
insert into addr_obj_zip_code(region,zip_code,TYPENAME,NAME,LEVEL,objectid)
SELECT ah.region, min(ah.value) zip,ah.typename,ah.name,ah.level,ah.objectid
FROM ah
where ah.level=6
group by ah.region,ah.typename,ah.name,ah.objectid,ah.level
;


create table gar.zip_code
(
    region      bigint,
    objectid    bigint,
    value       text
);
create unique index zip_code_region_objectid on zip_code(region,objectid);

insert into zip_code
select
    hp.region,hp.objectid,hp.value
from
    houses_params hp
where
    hp.typeid=5
  and '2024-10-30'::date between hp.startdate and hp.enddate
order by hp.region,hp.objectid
;


create table gar.addr_obj_zip_code
(
    region   bigint,
    zip_code text,
    typename text,
    name     text,
    level    bigint,
    objectid bigint
);

create unique index addr_obj_zip_code_region_objectid_ux
    on gar.addr_obj_zip_code (region, objectid);

alter table gar.addr_obj_zip_code
    add OBJECTGUID uuid
;

update addr_obj_zip_code target
set OBJECTGUID =source.objectguid::uuid
from addressobjects source
where
    target.region = source.region
  and target.objectid = source.objectid
  and source.isactive=1
;

create index addr_obj_params_region_objectid_typeid_startdate_ix
    on gar.addr_obj_params (region, objectid, typeid)
;

alter table gar.addr_obj_zip_code
    add okato text
;

update addr_obj_zip_code target
set okato =params.value
    from
     addressobjects source
join addr_obj_params params on source.region = params.region and source.objectid = params.objectid and params.typeid=6
where
    target.region = source.region
  and target.objectid = source.objectid
  and source.isactive=1
  and '2024-10-30'::date between params.startdate and params.enddate
;

alter table gar.addr_obj_zip_code
    add kladr text
;

update addr_obj_zip_code target
set kladr =params.value
    from
    addressobjects source
        join addr_obj_params params on source.region = params.region and source.objectid = params.objectid and params.typeid=10
where
    target.region = source.region
  and target.objectid = source.objectid
  and source.isactive=1
  and '2024-10-30'::date between params.startdate and params.enddate
;

alter table gar.addr_obj_zip_code
    add oktmo text
;

update addr_obj_zip_code target
set oktmo =params.value
    from
    addressobjects source
        join addr_obj_params params on source.region = params.region and source.objectid = params.objectid and params.typeid=7
where
    target.region = source.region
  and target.objectid = source.objectid
  and source.isactive=1
  and '2024-10-30'::date between params.startdate and params.enddate
;

WITH RECURSIVE ah AS (
    SELECT prev.region, prev.parentobjid,prev.objectid, z.value,null as typename,null as name,0::bigint as level
    FROM adm_hierarchy prev
             join zip_code z on z.region=prev.region and z.objectid=prev.objectid
    where
        prev.isactive=1
    UNION ALL
    SELECT next.region, next.parentobjid,next.objectid,  ah.value,a.typename,a.name,a.level
    FROM adm_hierarchy next
             JOIN ah ON next.region = ah.region and next.objectid = ah.parentobjid and next.isactive=1
             join addressobjects a on next.region=a.region and next.objectid = a.objectid and a.isactive=1
)
insert into addr_obj_zip_code(region,zip_code,TYPENAME,NAME,LEVEL,objectid)
SELECT ah.region, min(ah.value) zip,ah.typename,ah.name,ah.level,ah.objectid
FROM ah
where ah.level between 1 and 6
group by ah.region,ah.typename,ah.name,ah.objectid,ah.level
;
