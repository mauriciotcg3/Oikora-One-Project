-- Só para o dev e para os testes (OIK-ADR-0011 §4): roda uma vez, quando o volume do Postgres é criado.
-- Papéis do OIK-ADR-0002 §5. As senhas abaixo valem apenas dentro do Compose local.
-- O dono precisa de BYPASSRLS: o Postgres valida FK como dono, e a RLS forçada barraria a validação.
-- Não amplia o poder real dele, que já pode desligar a RLS com ALTER TABLE.
CREATE ROLE oikora_dono LOGIN PASSWORD 'oikora_dono_dev' BYPASSRLS;
CREATE ROLE oikora_app LOGIN PASSWORD 'oikora_app_dev' NOBYPASSRLS;
CREATE ROLE oikora_plataforma LOGIN PASSWORD 'oikora_plataforma_dev' BYPASSRLS;

CREATE DATABASE oikora OWNER oikora_dono;
CREATE DATABASE oikora_teste OWNER oikora_dono;

\connect oikora
ALTER SCHEMA public OWNER TO oikora_dono;
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS unaccent;
GRANT USAGE ON SCHEMA public TO oikora_app, oikora_plataforma;
ALTER DEFAULT PRIVILEGES FOR ROLE oikora_dono IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO oikora_app, oikora_plataforma;
ALTER DEFAULT PRIVILEGES FOR ROLE oikora_dono IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO oikora_app, oikora_plataforma;

\connect oikora_teste
ALTER SCHEMA public OWNER TO oikora_dono;
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS unaccent;
GRANT USAGE ON SCHEMA public TO oikora_app, oikora_plataforma;
ALTER DEFAULT PRIVILEGES FOR ROLE oikora_dono IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO oikora_app, oikora_plataforma;
ALTER DEFAULT PRIVILEGES FOR ROLE oikora_dono IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO oikora_app, oikora_plataforma;
