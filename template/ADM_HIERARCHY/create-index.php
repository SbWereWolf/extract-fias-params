CREATE UNIQUE INDEX ADM_HIERARCHY<?= $suffix ?>_REGION_OBJECTID_ISACTIVE_UX
ON ADM_HIERARCHY (REGION,OBJECTID,ISACTIVE);