# Railway.app Deployment Guide

This guide will help you deploy your PHP trading bot to Railway.app.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Railway Setup](#railway-setup)
3. [Database Configuration](#database-configuration)
4. [Environment Variables](#environment-variables)
5. [Deployment Steps](#deployment-steps)
6. [Post-Deployment](#post-deployment)
7. [Troubleshooting](#troubleshooting)

---

## Prerequisites

- Railway account (sign up at [railway.app](https://railway.app))
- GitHub account (for connecting your repository)
- Your code pushed to a GitHub repository

---

## Railway Setup

### 1. Create a New Project

1. Log in to [Railway Dashboard](https://railway.app/dashboard)
2. Click **"New Project"**
3. Select **"Deploy from GitHub repo"**
4. Choose your repository
5. Railway will automatically detect the `railway.toml` file

### 2. Add Database Service

Railway provides PostgreSQL by default. You have two options:

#### Option A: Use PostgreSQL (Recommended)

1. In your Railway project, click **"New"**
2. Select **"Database"** → **"Add PostgreSQL"**
3. Railway will automatically:
   - Create a PostgreSQL database
   - Set the `DATABASE_URL` environment variable
   - Make it available to your web service

#### Option B: Use MySQL

1. In your Railway project, click **"New"**
2. Select **"Database"** → **"Add MySQL"**
3. You'll need to manually set database environment variables (see below)

---

## Database Configuration

### For PostgreSQL (Recommended)

The application automatically detects Railway's `DATABASE_URL` and uses PostgreSQL when:
- `DB_TYPE=pgsql` is set, OR
- `DATABASE_URL` is present (Railway sets this automatically)

**Migration Steps:**

1. After adding PostgreSQL service, run the migration:
   ```bash
   # Connect to Railway PostgreSQL
   railway connect postgres
   
   # Run the PostgreSQL migration
   psql $DATABASE_URL < database/migrations/001_initial_schema_postgresql.sql
   ```

   OR use Railway's web console:
   - Go to your PostgreSQL service
   - Click "Query" tab
   - Copy and paste the contents of `database/migrations/001_initial_schema_postgresql.sql`
   - Execute

### For MySQL

If you prefer MySQL:

1. Set environment variable: `DB_TYPE=mysql`
2. Set database credentials manually:
   - `DB_HOST` (from MySQL service)
   - `DB_PORT` (usually 3306)
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASS`

3. Run MySQL migration:
   ```bash
   mysql -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME < database/migrations/001_initial_schema.sql
   ```

---

## Environment Variables

Set these in Railway Dashboard → Your Service → Variables:

### Required Variables

| Variable | Description | Example |
|----------|-------------|---------|
| `APP_ENV` | Application environment | `production` |
| `DB_TYPE` | Database type | `pgsql` (for PostgreSQL) or `mysql` |
| `ENCRYPTION_KEY` | Encryption key (64-char hex) | Generate with: `php -r "echo bin2hex(random_bytes(32));"` |
| `DERIV_APP_ID` | Deriv API App ID | `105326` |
| `DERIV_WS_HOST` | Deriv WebSocket host | `ws.derivws.com` |

### Optional Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_TIMEZONE` | Application timezone | `UTC` |
| `SESSION_LIFETIME` | Session lifetime in seconds | `86400` (24 hours) |

### Railway Auto-Set Variables

Railway automatically provides these (don't set manually):
- `PORT` - Port number for the service
- `RAILWAY_ENVIRONMENT` - Environment name
- `RAILWAY_STATIC_URL` - Your app's public URL
- `DATABASE_URL` - PostgreSQL connection string (if PostgreSQL service added)

### For MySQL (if not using PostgreSQL)

If using MySQL, set these manually:
- `DB_HOST` - MySQL hostname
- `DB_PORT` - MySQL port (usually 3306)
- `DB_NAME` - Database name
- `DB_USER` - Database username
- `DB_PASS` - Database password
- `DB_CHARSET` - Character set (default: `utf8mb4`)

---

## Deployment Steps

### 1. Configure Services

Railway will automatically create two services based on `railway.toml`:

1. **Web Service** - Your main application
2. **Scheduler Service** - Handles cron jobs

Both services will share the same environment variables.

### 2. Set Environment Variables

1. Go to your Railway project
2. Click on the **Web** service
3. Go to **Variables** tab
4. Add all required environment variables (see above)
5. Repeat for **Scheduler** service (or use shared variables)

### 3. Deploy

Railway will automatically:
- Build your Docker image using `Dockerfile.railway`
- Deploy the web service
- Deploy the scheduler service
- Run health checks

### 4. Run Database Migration

After deployment:

1. Connect to your database (PostgreSQL or MySQL)
2. Run the appropriate migration script:
   - PostgreSQL: `database/migrations/001_initial_schema_postgresql.sql`
   - MySQL: `database/migrations/001_initial_schema.sql`

You can do this via:
- Railway's database query console
- Railway CLI: `railway connect postgres` or `railway connect mysql`
- External database client

### 5. Create Admin User

After migration, create an admin user:

```bash
# Via Railway CLI
railway run php database/create_admin.php

# Or via web interface (if you have a setup endpoint)
```

---

## Post-Deployment

### 1. Verify Health Check

Visit: `https://your-app.railway.app/healthcheck.php`

You should see a JSON response with status `"healthy"`.

### 2. Test Application

1. Visit your Railway app URL
2. Register a new user
3. Test login
4. Verify database connectivity

### 3. Monitor Logs

- Railway Dashboard → Your Service → Logs
- Check both Web and Scheduler services
- Look for any errors or warnings

### 4. Verify Scheduler

The scheduler service should be running continuously. Check logs for:
```
Railway Scheduler: Started at [timestamp]
Trading loop executed successfully
Signal processor: Processed X signals
Contract monitor executed successfully
```

---

## Troubleshooting

### Issue: Database Connection Failed

**Solution:**
1. Verify `DATABASE_URL` is set (for PostgreSQL)
2. Or verify `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` are set (for MySQL)
3. Check database service is running
4. Verify network connectivity between services

### Issue: Health Check Failing

**Solution:**
1. Check application logs
2. Verify all required environment variables are set
3. Check database connection
4. Verify storage directories are writable

### Issue: Scheduler Not Running

**Solution:**
1. Check scheduler service logs
2. Verify scheduler service is deployed (check `railway.toml`)
3. Ensure scheduler service has same environment variables as web service

### Issue: 500 Internal Server Error

**Solution:**
1. Check Railway logs for PHP errors
2. Verify database migration completed successfully
3. Check file permissions
4. Verify all required PHP extensions are installed

### Issue: Port Already in Use

**Solution:**
- Railway automatically sets the `PORT` variable
- Don't hardcode port numbers
- Use `$_SERVER['PORT']` or `getenv('PORT')` in your code

---

## Service Architecture

```
┌─────────────────┐
│  Web Service    │  ← Handles HTTP requests
│  (Port 80)      │
└────────┬────────┘
         │
         ├───→ PostgreSQL/MySQL Database
         │
┌────────┴────────┐
│ Scheduler       │  ← Runs cron jobs continuously
│ Service         │
└─────────────────┘
```

---

## Custom Domain

To add a custom domain:

1. Go to Railway Dashboard → Your Service → Settings
2. Click **"Generate Domain"** or **"Custom Domain"**
3. Follow Railway's instructions to configure DNS

---

## Scaling

Railway automatically scales your services. To manually scale:

1. Go to Railway Dashboard → Your Service → Settings
2. Adjust resource limits (CPU, Memory)
3. Railway will handle the rest

---

## Backup

### Database Backups

Railway provides automatic backups for database services:
- Go to Database Service → Backups
- Configure backup schedule
- Restore from backups as needed

### Application Backups

Your code is backed up in GitHub. Railway deployments are versioned.

---

## Support

- Railway Documentation: [docs.railway.app](https://docs.railway.app)
- Railway Discord: [discord.gg/railway](https://discord.gg/railway)
- Railway Status: [status.railway.app](https://status.railway.app)

---

## Additional Notes

1. **Cron Jobs**: Railway doesn't support traditional cron. The scheduler service (`cron/scheduler.php`) handles all scheduled tasks.

2. **File Storage**: Railway's file system is ephemeral. Use external storage (S3, etc.) for persistent files.

3. **Sessions**: Consider using database-backed sessions or Redis for production.

4. **Logs**: Railway provides log aggregation. Check logs regularly for issues.

5. **Environment**: Always set `APP_ENV=production` for production deployments.

---

## Quick Reference

### Railway CLI Commands

```bash
# Install Railway CLI
npm i -g @railway/cli

# Login
railway login

# Link project
railway link

# View logs
railway logs

# Connect to database
railway connect postgres
railway connect mysql

# Run commands
railway run php [command]

# Set variables
railway variables set KEY=value
```

---

**Last Updated:** 2024
**Railway Version:** Latest

