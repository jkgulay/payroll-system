# Deploy to Render - Simple Guide

## 1. Push to GitHub

```bash
git add .
git commit -m "Ready for Render deployment"
git push
```

## 2. Backend (Laravel API) on Render

1. Go to https://render.com and sign up/login
2. Click **New +** → **Web Service**
3. Connect your GitHub repo
4. **Settings:**
   - Name: `payroll-backend`
   - Region: Oregon (or closest)
   - Root Directory: `backend`
   - Environment: **Docker** (Render will detect the Dockerfile)
   - Build Command: (leave empty - uses Dockerfile)
   - Start Command: (leave empty - uses Dockerfile CMD)

5. **Environment Variables** (click "Advanced"):
   ```
   APP_NAME=Payroll System
   APP_ENV=production
   APP_DEBUG=false
   APP_KEY=<generate with: php artisan key:generate --show>
   
   DB_CONNECTION=pgsql
   DATABASE_URL=<will add after creating database>
   
   SESSION_DRIVER=database
   CACHE_DRIVER=database
   FILESYSTEM_DISK=public
   ```

6. Click **Create Web Service**

7. **Add PostgreSQL Database:**
   - In your service dashboard, click **New +** → **PostgreSQL**
   - Name: `payroll-db`
   - Free plan is fine for testing
   - Copy the **Internal Database URL**
   - Go back to your web service → Environment → Add `DATABASE_URL` with that URL

8. **Run Migrations:**
   - In your service → Shell tab
   - Run: `php artisan migrate --force`

9. **Get your backend URL:** 
   - Copy it (e.g., `https://payroll-backend.onrender.com`)

## 3. Frontend (Vue) on Render

1. Click **New +** → **Static Site**
2. Connect your GitHub repo
3. **Settings:**
   - Name: `payroll-frontend`
   - Root Directory: `frontend`
   - Build Command: `npm install && npm run build`
   - Publish Directory: `dist`

4. **Environment Variables:**
   ```
   VITE_API_BASE_URL=https://your-backend.onrender.com/api
   ```
   (Use the backend URL from step 2.9)

5. Click **Create Static Site**

## 4. Update Backend CORS

After frontend deploys:
1. Go to backend service → Environment
2. Add:
   ```
   FRONTEND_URL=https://your-frontend.onrender.com
   SANCTUM_STATEFUL_DOMAINS=your-frontend.onrender.com
   ```
3. Service will auto-redeploy

## 5. Create Admin User

In backend service Shell:
```bash
php artisan tinker

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

Visit your frontend URL and login!

## Cost

- **Free Tier**: Good for testing (services sleep after 15min inactivity)
- **Paid**: $7/month per service ($14 total for backend + frontend)
- **Database**: Free for starter, $7/month for 1GB

## Notes

- Free tier has cold starts (takes 30s to wake up)
- File storage is ephemeral (configure S3 for production)
- Auto-deploys on git push
- Much simpler than Railway!
