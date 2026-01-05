-- Create databases for each service
CREATE DATABASE authentik;
CREATE DATABASE keycloak;
CREATE DATABASE grafana;

-- Create users for each service
CREATE USER authentik WITH PASSWORD 'authentik';
CREATE USER keycloak WITH PASSWORD 'keycloak';
CREATE USER grafana WITH PASSWORD 'grafana';

-- Grant privileges
GRANT ALL PRIVILEGES ON DATABASE authentik TO authentik;
GRANT ALL PRIVILEGES ON DATABASE keycloak TO keycloak;
GRANT ALL PRIVILEGES ON DATABASE grafana TO grafana;

-- Grant schema permissions for Grafana
\c grafana
GRANT ALL ON SCHEMA public TO grafana;
ALTER DATABASE grafana OWNER TO grafana;

-- Grant schema permissions for Authentik
\c authentik
GRANT ALL ON SCHEMA public TO authentik;
ALTER DATABASE authentik OWNER TO authentik;

-- Grant schema permissions for Keycloak
\c keycloak
GRANT ALL ON SCHEMA public TO keycloak;
ALTER DATABASE keycloak OWNER TO keycloak;
