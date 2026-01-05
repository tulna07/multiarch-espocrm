# Authentik Portal Setup with Keycloak, Grafana, and pgAdmin

## Architecture

```
User → Authentik Portal (shows app tiles)
         ↓ (authenticates via)
       Keycloak
         ↓ (after login, see tiles)
       [Grafana] [pgAdmin]
```

## Quick Start

```bash
docker-compose up -d
```

Wait 2-3 minutes for all services to start.

## Step 1: Configure Keycloak

**Access:** http://localhost:8080

1. Login with `admin` / `admin`

2. **Create Realm:**

   - Hover over "Master" dropdown (top left)
   - Click "Create Realm"
   - Name: `authentik-realm`
   - Click "Create"

3. **Create Client for Authentik:**

   - Go to "Clients" → "Create client"
   - Client ID: `authentik`
   - Click "Next"
   - Client authentication: ON
   - Click "Next"
   - Valid redirect URIs: `http://localhost:9000/*`
   - Click "Save"
   - Go to "Credentials" tab
   - **Copy the Client Secret** (you'll need this)

4. **Create Test User:**
   - Go to "Users" → "Add user"
   - Username: `testuser`
   - Click "Create"
   - Go to "Credentials" tab
   - Set password: `password`
   - Temporary: OFF
   - Click "Set password"

## Step 2: Configure Authentik

**Access:** http://localhost:9000

### Initial Setup

1. Complete the initial setup wizard
2. Create admin account (akadmin / Aqswde123@@)

### Add Keycloak as Authentication Source

1. Go to **Directory → Federation & Social login**
2. Click **Create** → Select **OAuth Source**
3. Configure:
   - Name: `Keycloak`
   - Slug: `keycloak`
   - Provider type: `OpenID Connect`
   - Consumer key: `authentik`
   - Consumer secret: `<paste-client-secret-from-keycloak>`
   - OIDC Well-known URL: `http://keycloak:8080/realms/authentik-realm/.well-known/openid-configuration`
   - OIDC JWKS URL: `http://keycloak:8080/realms/authentik-realm/protocol/openid-connect/certs`
4. Click **Create**

### Create Grafana Application

**Create Provider:**

1. Go to **Applications → Providers** → **Create**
2. Select **OAuth2/OpenID Provider**
3. Configure:
   - Name: `grafana-provider`
   - Authorization flow: `default-provider-authorization-implicit-consent`
   - Redirect URIs/Origins: `http://localhost:3000/login/generic_oauth`
4. Click **Finish**
5. **Copy Client ID and Client Secret**

**Create Application:**

1. Go to **Applications → Applications** → **Create**
2. Configure:
   - Name: `Grafana`
   - Slug: `grafana`
   - Provider: `grafana-provider`
   - Launch URL: `http://localhost:3000`
3. Click **Create**

### Create pgAdmin Application

**Create Provider:**

1. Go to **Applications → Providers** → **Create**
2. Select **OAuth2/OpenID Provider**
3. Configure:
   - Name: `pgadmin-provider`
   - Authorization flow: `default-provider-authorization-implicit-consent`
   - Redirect URIs/Origins: `http://localhost:5050/oauth2/authorize`
4. Click **Finish**
5. **Copy Client ID and Client Secret**

**Create Application:**

1. Go to **Applications → Applications** → **Create**
2. Configure:
   - Name: `pgAdmin`
   - Slug: `pgadmin`
   - Provider: `pgadmin-provider`
   - Launch URL: `http://localhost:5050`
3. Click **Create**

## Step 3: Update Docker Compose

Edit `docker-compose.yml` and replace the placeholders:

**For Grafana:**

- Replace `<REPLACE_WITH_GRAFANA_CLIENT_ID>` with the Client ID from Authentik
- Replace `<REPLACE_WITH_GRAFANA_CLIENT_SECRET>` with the Client Secret from Authentik

**For pgAdmin:**

- Replace `<REPLACE_WITH_PGADMIN_CLIENT_ID>` with the Client ID from Authentik
- Replace `<REPLACE_WITH_PGADMIN_CLIENT_SECRET>` with the Client Secret from Authentik

## Step 4: Restart Services

```bash
docker-compose restart grafana pgadmin
```

## Step 5: Test the Workflow

1. Go to **http://localhost:9000**
2. Click **"Login with Keycloak"**
3. Enter Keycloak credentials (`testuser` / `password`)
4. After login, you'll see the Authentik portal with application tiles:
   - **Grafana** tile
   - **pgAdmin** tile
5. Click any tile to launch the application with SSO (no re-login needed)

## Access URLs

- **Authentik Portal:** http://localhost:9000
- **Keycloak Admin:** http://localhost:8080
- **Grafana:** http://localhost:3000
- **pgAdmin:** http://localhost:5050

## Default Credentials

### Keycloak

- Admin: `admin` / `admin`
- Test User: `testuser` / `password`

### Authentik

- Set during initial setup

### Grafana (fallback)

- Admin: `admin` / `admin`

### pgAdmin (fallback)

- Email: `admin@admin.com`
- Password: `admin`

## Troubleshooting

### Services not starting

```bash
docker-compose logs -f <service-name>
```

### Reset everything

```bash
docker-compose down -v
docker-compose up -d
```

### Keycloak connection issues

- Ensure you're using `http://keycloak:8080` (internal) for Authentik configuration
- Use `http://localhost:8080` only for browser access

### OAuth errors

- Verify redirect URIs match exactly
- Check client IDs and secrets are correct
- Ensure services can communicate on the internal network

## Production Notes

Before deploying to production:

1. Change `AUTHENTIK_SECRET_KEY` to a random 50+ character string
2. Update all default passwords
3. Use HTTPS with proper SSL certificates
4. Configure proper database backups
5. Review and harden security settings
6. Use `start` instead of `start-dev` for Keycloak
