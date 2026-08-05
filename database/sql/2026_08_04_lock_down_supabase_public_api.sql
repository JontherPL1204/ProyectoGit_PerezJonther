-- N-Woffu Prime
-- Run this in Supabase SQL Editor only if you cannot run:
-- php artisan migrate --force
--
-- This app uses Laravel as the trusted backend, so tables should not be
-- reachable through Supabase's automatic public Data API.

DO $$
DECLARE
    api_role text;
    api_roles text[] := ARRAY['anon', 'authenticated'];
    table_record record;
BEGIN
    FOR table_record IN
        SELECT namespace.nspname AS schema_name, class.relname AS table_name
        FROM pg_class class
        INNER JOIN pg_namespace namespace ON namespace.oid = class.relnamespace
        WHERE namespace.nspname = 'public'
            AND class.relkind IN ('r', 'p')
    LOOP
        EXECUTE format(
            'ALTER TABLE %I.%I ENABLE ROW LEVEL SECURITY',
            table_record.schema_name,
            table_record.table_name
        );
    END LOOP;

    REVOKE USAGE ON SCHEMA public FROM PUBLIC;
    REVOKE ALL PRIVILEGES ON ALL TABLES IN SCHEMA public FROM PUBLIC;
    REVOKE ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public FROM PUBLIC;
    REVOKE EXECUTE ON ALL FUNCTIONS IN SCHEMA public FROM PUBLIC;
    ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE ALL PRIVILEGES ON TABLES FROM PUBLIC;
    ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE ALL PRIVILEGES ON SEQUENCES FROM PUBLIC;
    ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE EXECUTE ON FUNCTIONS FROM PUBLIC;

    FOREACH api_role IN ARRAY api_roles
    LOOP
        IF to_regrole(api_role) IS NOT NULL THEN
            EXECUTE format('REVOKE USAGE ON SCHEMA public FROM %I', api_role);
            EXECUTE format('REVOKE ALL PRIVILEGES ON ALL TABLES IN SCHEMA public FROM %I', api_role);
            EXECUTE format('REVOKE ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public FROM %I', api_role);
            EXECUTE format('REVOKE EXECUTE ON ALL FUNCTIONS IN SCHEMA public FROM %I', api_role);
            EXECUTE format('ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE ALL PRIVILEGES ON TABLES FROM %I', api_role);
            EXECUTE format('ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE ALL PRIVILEGES ON SEQUENCES FROM %I', api_role);
            EXECUTE format('ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE EXECUTE ON FUNCTIONS FROM %I', api_role);
        END IF;
    END LOOP;
END
$$;
