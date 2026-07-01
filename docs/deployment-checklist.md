# Deployment Checklist — DIUS Management Portal

## Pre-Deployment

### Environment
- [ ] Copy `.env.example` to `.env`
- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Generate app key: `php artisan key:generate`
- [ ] Set `APP_URL` to production domain
- [ ] Configure database credentials
- [ ] Set `LOG_LEVEL=error` for production

### Cache & Performance
- [ ] Switch to Redis: `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=redis`
- [ ] Run `php artisan config:cache`
- [ ] Run `php artisan route:cache`
- [ ] Run `php artisan view:cache`
- [ ] Run `php artisan event:cache`

### Database
- [ ] Run migrations: `php artisan migrate --force`
- [ ] Run seeders: `php artisan db:seed --force`
- [ ] Verify `users` table has at least one Admin user

### Storage
- [ ] Run `php artisan storage:link` for public disk
- [ ] Ensure `storage/app/public/profile-photos/` is writable
- [ ] Ensure `storage/logs/` is writable

### Frontend
- [ ] Run `npm run build` for production assets
- [ ] Verify `public/build/` directory exists

### Security
- [ ] Remove registration routes (already disabled in code)
- [ ] Verify `.env` is not publicly accessible
- [ ] Verify `APP_DEBUG=false` (prevents stack trace exposure)
- [ ] Set up HTTPS/SSL certificate
- [ ] Configure firewall rules for database port

### Monitoring
- [ ] Set up error logging (Sentry, Loggly, etc.)
- [ ] Configure health check endpoint: `GET /up`
- [ ] Set up database backup schedule
- [ ] Set up file storage backup

## Post-Deployment Verification

### Functional Tests
- [ ] Admin can log in
- [ ] Admin can create users
- [ ] Admin can assign roles
- [ ] Project CRUD works
- [ ] Task/Subtask assignment works
- [ ] File upload works (JPG/PNG/WebP)
- [ ] File upload rejects SVG/scripts
- [ ] Dashboard loads with real data
- [ ] Reports generate correctly

### Error Page Tests
- [ ] `GET /nonexistent` → 404 page (not blank)
- [ ] `GET /admin/users` as Subordinate → 403 page (not blank)
- [ ] Trigger 500 error → custom error page (not blank)

### Performance
- [ ] Dashboard loads in < 2 seconds
- [ ] Project list paginates correctly
- [ ] Cache is being used (check Redis keys)
- [ ] Queue worker is running (if using redis queue)

## Rollback Plan

If deployment fails:
1. Restore previous database backup
2. Revert code to previous git commit
3. Clear caches: `php artisan optimize:clear`
4. Rebuild assets: `npm run build`
5. Verify application is functional
