# Deployment Checklist

## ✅ Pre-Deployment Verification

Your system is deployment-ready! All configuration files are in place:

- ✅ `frontend/vercel.json` - Vercel configuration
- ✅ `backend/Procfile` - Railway web process
- ✅ `backend/railway.json` - Railway deployment config
- ✅ `backend/.env.production` - Backend environment template
- ✅ `frontend/.env.production` - Frontend environment template
- ✅ `DEPLOYMENT.md` - Complete deployment guide

## 🚀 Quick Deployment Steps

### 1. Deploy Backend to Railway (Do This First)

1. **Create Railway Account**: https://railway.app/
2. **Create New Project**: 
   - Click "New Project" → "Deploy from GitHub repo"
   - Select your repository
   - Railway will auto-detect Laravel
3. **Add PostgreSQL Database**:
   - In your project → "New" → "Database" → "Add PostgreSQL"
   - Railway auto-creates `DATABASE_URL` variable
4. **Set Environment Variables**:
   - Go to your service → "Variables" tab
   - Copy from `backend/.env.production` and update:
     ```
     APP_KEY=<generate with: php artisan key:generate --show>
     APP_ENV=production
     APP_DEBUG=false
     APP_URL=https://your-app.railway.app
     FRONTEND_URL=https://your-app.vercel.app
     SANCTUM_STATEFUL_DOMAINS=your-app.vercel.app
     ```
5. **Deploy**: Railway will automatically deploy
6. **Copy your Railway URL**: You'll need this for Vercel

### 2. Deploy Frontend to Vercel

1. **Create Vercel Account**: https://vercel.com/
2. **Import Project**:
   - Click "Add New..." → "Project"
   - Import from GitHub
   - **Important**: Set Root Directory to `frontend`
3. **Set Environment Variable**:
   - Add `VITE_API_BASE_URL` = `https://your-railway-backend.railway.app/api`
   - (Use the Railway URL from step 1)
4. **Deploy**: Vercel will build and deploy automatically
5. **Copy your Vercel URL**: Update Railway's `FRONTEND_URL` with this

### 3. Update CORS in Railway

After getting your Vercel URL:
1. Go to Railway → Your Service → Variables
2. Update:
   ```
   FRONTEND_URL=https://your-actual-vercel-url.vercel.app
   SANCTUM_STATEFUL_DOMAINS=your-actual-vercel-url.vercel.app
   ```
3. Redeploy the service

### 4. Create Admin User

Using Railway CLI:
```bash
# Install Railway CLI
npm install -g @railway/cli

# Login
railway login

# Link to your project
railway link

# Open Laravel Tinker
railway run php artisan tinker

# In Tinker, run:
$user = new App\Models\User();
$user->username = 'admin';
$user->email = 'admin@company.com';
$user->password = Hash::make('YourSecurePassword123');
$user->role = 'admin';
$user->name = 'Administrator';
$user->save();
```

## 🔍 Post-Deployment Testing

1. ✅ Visit your Vercel URL (frontend)
2. ✅ Try logging in with admin credentials
3. ✅ Test employee listing page
4. ✅ Test creating a new employee
5. ✅ Check if API calls are working (Network tab in DevTools)

## ⚠️ Important Notes

### Database
- Railway provides PostgreSQL (already configured in your code)
- Your local database is already PostgreSQL, so no migration needed

### File Storage
- Railway storage is **ephemeral** (files deleted on redeploy)
- For production with 1,350 employees, configure AWS S3:
  ```
  FILESYSTEM_DISK=s3
  AWS_ACCESS_KEY_ID=your-key
  AWS_SECRET_ACCESS_KEY=your-secret
  AWS_DEFAULT_REGION=ap-southeast-1
  AWS_BUCKET=your-bucket-name
  ```

### Cost Estimate
- **Railway Pro**: $20/month (PostgreSQL included, 8GB RAM)
- **Vercel Pro**: $20/month (better bandwidth, commercial use)
- **Total**: ~$40/month

For 1,350 employees, Pro plans are recommended for:
- Better performance
- Higher bandwidth limits
- Priority support
- No sleep mode

## 🆘 Troubleshooting

### Frontend shows 404 on refresh
- ✅ Fixed by `vercel.json` rewrite rules (already configured)

### API calls fail (CORS error)
- Check `FRONTEND_URL` in Railway matches Vercel URL exactly
- Check `SANCTUM_STATEFUL_DOMAINS` includes Vercel domain (no https://)

### 500 Error on Backend
- Check Railway logs: `railway logs`
- Verify `APP_KEY` is set
- Verify database connected: Check `DATABASE_URL` variable exists

### Migrations not running
- ✅ Auto-configured in `Procfile` (runs on every deploy)
- Manual run: `railway run php artisan migrate --force`

## 📚 Full Documentation

For detailed instructions, see [DEPLOYMENT.md](./DEPLOYMENT.md)

## ⏱️ Estimated Time

- Railway setup: 10-15 minutes
- Vercel setup: 5-10 minutes
- Admin user creation: 5 minutes
- Testing: 10 minutes

**Total: 30-40 minutes**

## 🎯 Next Steps After Deployment

1. **Custom Domain** (Optional):
   - Railway: Settings → Domains → Add Custom Domain
   - Vercel: Settings → Domains → Add Domain

2. **SSL Certificates**:
   - ✅ Automatic on both platforms (included free)

3. **Monitoring**:
   - Railway: Built-in metrics in dashboard
   - Vercel: Analytics in dashboard (free tier limited)

4. **Backups**:
   - Railway: No auto-backups on Pro plan
   - Manual backup: `railway run pg_dump $DATABASE_URL > backup.sql`
   - Schedule backups with GitHub Actions or cron job

5. **Environment Secrets**:
   - Never commit actual `.env` file
   - Use Railway/Vercel dashboards for secrets
   - Rotate `APP_KEY` periodically for security

---

**Need help?** Check the full [DEPLOYMENT.md](./DEPLOYMENT.md) or contact support:
- Railway: https://railway.app/help
- Vercel: https://vercel.com/support
