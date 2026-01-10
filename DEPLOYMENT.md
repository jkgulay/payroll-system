# Simple Deployment Guide - CLI Only

## Prerequisites

Install the CLI tools:
```bash
npm install -g vercel
npm install -g @railway/cli
```

## 1. Deploy Backend to Railway

```bash
cd backend

# Login to Railway
railway login

# Create new project
railway init

# Add PostgreSQL database
railway add --database postgres

# Deploy first (this creates the service)
railway up

# Now set environment variables
railway variables --set APP_NAME="Payroll System"
railway variables --set APP_ENV=production
railway variables --set APP_DEBUG=false
railway variables --set DB_CONNECTION=pgsql
railway variables --set SESSION_DRIVER=database
railway variables --set CACHE_DRIVER=database

# Generate APP_KEY locally then set it
php artisan key:generate --show
# Copy the output and run:
railway variables --set APP_KEY="base64:your-key-here"

# Redeploy with new variables
railway up

# Run migrations
railway run php artisan migrate --force

# Get your backend URL
railway status
```

## 2. Deploy Frontend to Vercel

```bash
cd frontend

# Login to Vercel
vercel login

# Deploy
vercel

# Set environment variable (use your Railway URL from step 1)
vercel env add VITE_API_BASE_URL production
# Enter: https://your-railway-url.railway.app/api

# Deploy to production
vercel --prod
```

## 3. Update Backend CORS

After getting your Vercel URL:

```bash
cd backend
railway variables --set FRONTEND_URL="https://your-vercel-url.vercel.app"
railway variables --set SANCTUM_STATEFUL_DOMAINS="your-vercel-url.vercel.app"
```

## 4. Create Admin User

```bash
cd backend
railway run php artisan tinker

# In tinker, run:
$user = new App\Models\User();
$user->username = 'admin';
$user->email = 'admin@company.com';
$user->password = Hash::make('YourPassword123');
$user->role = 'admin';
$user->name = 'Administrator';
$user->save();
exit
```

## Done!

Visit your Vercel URL and login with the admin credentials.

## Common Commands

```bash
# Railway
railway logs                    # View logs
railway run php artisan migrate # Run migrations
railway status                  # Get app URL
railway variables               # List all variables

# Vercel
vercel logs                     # View logs
vercel ls                       # List deployments
vercel env ls                   # List environment variables
```

## Important Notes

- **PHP 8.2+ required** (Railway doesn't support PHP 8.1)
- Database is PostgreSQL on Railway (auto-configured)
- File storage is ephemeral on Railway (files lost on redeploy)
- For production, configure S3 for file uploads
- Cost: ~$40/month for Pro plans (recommended for 1,350 employees)
