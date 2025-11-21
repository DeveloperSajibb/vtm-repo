# Railway Environment Variables Reference

This document lists all environment variables used by the VTM Option trading bot on Railway.

## Quick Setup

### Minimum Required Variables

```bash
APP_ENV=production
DB_TYPE=pgsql
ENCRYPTION_KEY=<generate-64-char-hex>
DERIV_APP_ID=105326
DERIV_WS_HOST=ws.derivws.com
```

---

## Environment Variables

### Application Configuration

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `APP_ENV` | Yes | `development` | Application environment: `production` or `development` |
| `APP_URL` | No | Auto-detected | Application URL (Railway sets `RAILWAY_STATIC_URL`) |
| `APP_TIMEZONE` | No | `UTC` | Application timezone |
| `PORT` | Auto | `80` | Port number (automatically set by Railway) |

### Database Configuration

#### PostgreSQL (Recommended)

Railway automatically provides `DATABASE_URL` when you add a PostgreSQL service.

| Variable | Required | Source | Description |
|----------|----------|--------|-------------|
| `DATABASE_URL` | Yes* | Railway | PostgreSQL connection string (auto-set by Railway) |
| `DB_TYPE` | Yes | `pgsql` | Database type: `pgsql` or `mysql` |

*Required if using PostgreSQL

#### MySQL (Alternative)

If using MySQL instead of PostgreSQL:

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `DB_TYPE` | Yes | `mysql` | Set to `mysql` |
| `DB_HOST` | Yes | `localhost` | MySQL hostname |
| `DB_PORT` | No | `3306` | MySQL port |
| `DB_NAME` | Yes | `vtm` | Database name |
| `DB_USER` | Yes | `root` | Database username |
| `DB_PASS` | Yes | (empty) | Database password |
| `DB_CHARSET` | No | `utf8mb4` | Character set |

**Note:** If `DATABASE_URL` is present and `DB_TYPE=pgsql`, the app will use PostgreSQL and parse `DATABASE_URL` automatically.

### Security Configuration

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `ENCRYPTION_KEY` | Yes | (none) | 64-character hex string for encryption |
| `SESSION_LIFETIME` | No | `86400` | Session lifetime in seconds (24 hours) |

**Generate Encryption Key:**
```bash
php -r "echo bin2hex(random_bytes(32));"
```

### Deriv API Configuration

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `DERIV_APP_ID` | Yes | `105326` | Deriv API Application ID |
| `DERIV_WS_HOST` | Yes | `ws.derivws.com` | Deriv WebSocket hostname |

### Railway-Specific Variables (Auto-Set)

These are automatically set by Railway. **Do not set manually:**

| Variable | Description |
|----------|-------------|
| `PORT` | Port number for the service |
| `RAILWAY_ENVIRONMENT` | Environment name (production, staging, etc.) |
| `RAILWAY_STATIC_URL` | Your application's public URL |
| `DATABASE_URL` | PostgreSQL connection string (if PostgreSQL service added) |

---

## Setting Variables in Railway

### Via Railway Dashboard

1. Go to Railway Dashboard
2. Select your project
3. Click on your service (Web or Scheduler)
4. Go to **Variables** tab
5. Click **"New Variable"**
6. Enter variable name and value
7. Click **"Add"**

### Via Railway CLI

```bash
# Set a variable
railway variables set APP_ENV=production

# Set multiple variables
railway variables set APP_ENV=production DB_TYPE=pgsql

# View all variables
railway variables

# Delete a variable
railway variables unset VARIABLE_NAME
```

### Via railway.toml (Not Recommended)

You can set default values in `railway.toml`, but it's better to use the dashboard or CLI for sensitive values.

---

## Environment-Specific Configuration

### Production

```bash
APP_ENV=production
DB_TYPE=pgsql
ENCRYPTION_KEY=<generate-new-key>
DERIV_APP_ID=105326
DERIV_WS_HOST=ws.derivws.com
APP_TIMEZONE=UTC
SESSION_LIFETIME=86400
```

### Development/Staging

```bash
APP_ENV=development
DB_TYPE=pgsql
ENCRYPTION_KEY=<dev-key>
DERIV_APP_ID=105326
DERIV_WS_HOST=ws.derivws.com
APP_TIMEZONE=UTC
```

---

## Variable Priority

The application loads environment variables in this order:

1. Railway environment variables (highest priority)
2. `$_ENV` superglobal
3. `$_SERVER` superglobal
4. Default values in `config.php` (lowest priority)

---

## Security Best Practices

1. **Never commit secrets to Git**
   - Use Railway's environment variables
   - Add `.env` to `.gitignore` (if using locally)

2. **Generate unique keys for production**
   ```bash
   # Generate encryption key
   php -r "echo bin2hex(random_bytes(32));"
   ```

3. **Rotate keys periodically**
   - Update `ENCRYPTION_KEY` regularly
   - Re-encrypt existing data if needed

4. **Use different keys per environment**
   - Production: Strong, unique key
   - Staging: Different key
   - Development: Test key

5. **Limit access to variables**
   - Only set variables in Railway dashboard
   - Don't log sensitive variables
   - Use Railway's variable encryption

---

## Validation

The application validates environment variables on startup. Missing required variables will cause:

- Health check to fail
- Application errors in logs
- Database connection failures

Check health endpoint: `https://your-app.railway.app/healthcheck.php`

---

## Troubleshooting

### Variable Not Found

**Symptoms:**
- Application uses default values
- Errors in logs about missing variables

**Solution:**
1. Check variable name spelling (case-sensitive)
2. Verify variable is set in Railway dashboard
3. Check which service the variable is set for (Web vs Scheduler)
4. Redeploy service after adding variables

### Database Connection Issues

**Symptoms:**
- `DATABASE_URL` not parsed correctly
- Connection refused errors

**Solution:**
1. Verify `DATABASE_URL` format: `postgresql://user:pass@host:port/dbname`
2. Check PostgreSQL service is running
3. Verify `DB_TYPE=pgsql` is set
4. For MySQL, verify all DB_* variables are set

### Encryption Key Issues

**Symptoms:**
- Cannot decrypt existing data
- Encryption errors

**Solution:**
1. Verify `ENCRYPTION_KEY` is exactly 64 characters
2. Ensure key is hex-encoded (0-9, a-f)
3. Don't change key after data is encrypted (or re-encrypt)

---

## Example Configuration

### Complete Production Setup

```bash
# Application
APP_ENV=production
APP_TIMEZONE=UTC
SESSION_LIFETIME=86400

# Database (PostgreSQL)
DB_TYPE=pgsql
# DATABASE_URL is auto-set by Railway

# Security
ENCRYPTION_KEY=7f3a9b2c8d4e1f6a5b9c2d7e3f8a1b4c6d9e2f5a8b1c4d7e0f3a6b9c2d5e8f1a4

# Deriv API
DERIV_APP_ID=105326
DERIV_WS_HOST=ws.derivws.com
```

### Complete MySQL Setup

```bash
# Application
APP_ENV=production
APP_TIMEZONE=UTC

# Database (MySQL)
DB_TYPE=mysql
DB_HOST=<mysql-host>
DB_PORT=3306
DB_NAME=vtm
DB_USER=<mysql-user>
DB_PASS=<mysql-password>
DB_CHARSET=utf8mb4

# Security
ENCRYPTION_KEY=<64-char-hex>

# Deriv API
DERIV_APP_ID=105326
DERIV_WS_HOST=ws.derivws.com
```

---

## Migration Checklist

When deploying to Railway:

- [ ] Set `APP_ENV=production`
- [ ] Generate and set `ENCRYPTION_KEY`
- [ ] Set `DB_TYPE=pgsql` (or `mysql`)
- [ ] Verify `DATABASE_URL` is set (PostgreSQL) or DB_* variables (MySQL)
- [ ] Set `DERIV_APP_ID` and `DERIV_WS_HOST`
- [ ] Verify health check passes
- [ ] Test database connection
- [ ] Run database migrations
- [ ] Create admin user
- [ ] Test application functionality

---

**Last Updated:** 2024

