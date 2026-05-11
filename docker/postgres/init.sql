-- First-boot DB bootstrap. Postgres image runs anything in
-- /docker-entrypoint-initdb.d/ once on a fresh data volume.
--
-- The POSTGRES_DB env var already creates `champions_league`, so this file
-- is reserved for future schema needs (extensions, roles). Kept as a
-- placeholder so the init.d directory exists and the volume mount path
-- in compose.yml resolves cleanly.

SELECT 'champions_league bootstrap';
