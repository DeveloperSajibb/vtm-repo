# Railway Quick Start Guide

Get your VTM Option trading bot deployed to Railway in 5 minutes!

## Prerequisites

- [ ] Railway account ([sign up here](https://railway.app))
- [ ] GitHub repository with your code
- [ ] 5 minutes

---

## Step 1: Connect Repository (2 minutes)

1. Go to [Railway Dashboard](https://railway.app/dashboard)
2. Click **"New Project"**
3. Select **"Deploy from GitHub repo"**
4. Choose your repository
5. Railway will automatically detect `railway.toml` and start building

---

## Step 2: Add Database (1 minute)

1. In your Railway project, click **"New"**
2. Select **"Database"** → **"Add PostgreSQL"**
3. Railway automatically sets `DATABASE_URL` - that's it!

---

## Step 3: Set Environment Variables (1 minute)

Go to your **Web** service → **Variables** tab, and add:

```bash
APP_ENV=production
DB_TYPE=pgsql
ENCRYPTION_KEY=<generate-with-command-below>
DERIV_APP_ID=105326
DERIV_WS_HOST=ws.derivws.com
```

**Generate Encryption Key:**
```bash
php -r "echo bin2hex(random_bytes(32));"
```

Copy the output and paste it as `ENCRYPTION_KEY` value.

**Repeat for Scheduler service** (same variables).

---

## Step 4: Run Database Migration (1 minute)

After deployment, run the migration:

**Option A: Via Railway CLI**
```bash
railway run php database/migrate_railway.php
```

**Option B: Via Railway Dashboard**
1. Go to PostgreSQL service
2. Click **"Query"** tab
3. Copy contents of `database/migrations/001_initial_schema_postgresql.sql`
4. Paste and execute

---

## Step 5: Verify (30 seconds)

1. Visit your app URL (Railway provides it)
2. Check health: `https://your-app.railway.app/healthcheck.php`
3. Should see: `{"status":"healthy",...}`

---

## ✅ Done!

Your app is now live on Railway!

---

## Next Steps

- [ ] Create admin user: `railway run php database/create_admin.php`
- [ ] Test registration and login
- [ ] Monitor logs in Railway dashboard
- [ ] Set up custom domain (optional)

---

## Troubleshooting

**Build fails?**
- Check Railway logs
- Verify `Dockerfile.railway` exists
- Check for syntax errors

**Database connection fails?**
- Verify `DATABASE_URL` is set (check PostgreSQL service)
- Verify `DB_TYPE=pgsql` is set
- Check database service is running

**Health check fails?**
- Check all environment variables are set
- Verify database migration completed
- Check application logs

---

## Need Help?

- Full guide: See `RAILWAY_DEPLOYMENT.md`
- Environment variables: See `RAILWAY_ENV_VARS.md`
- Railway docs: [docs.railway.app](https://docs.railway.app)

---

**That's it! Happy deploying! 🚀**

