-- WhiteGlove Render bootstrap (MySQL CLI)
-- Usage:
--   mysql -h <HOST> -P <PORT> -u <USER> -p < database/init_render.sql
--
-- Loads:
-- 1) Base schema
-- 2) Minimal account seed (admin + provider users)
-- 3) Realistic services dump
-- 4) Premium services dump


SOURCE database/schema_v2.sql;
SOURCE database/seed_admin_only.sql;
SOURCE database/service_dump_realistic.sql;
SOURCE database/service_dump_premium.sql;

