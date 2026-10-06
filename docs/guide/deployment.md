# Deployment and production checklist

## Deploying a release

```bash
php artisan down                         # optional, for releases with long migrations
git pull                                 # or upload the release
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force   # only when PermissionRegistry changed
php artisan optimize                     # caches config, routes, views and events
php artisan queue:restart                # workers pick up the new code
php artisan up
```

On the **first** deployment also run `php artisan key:generate` (once only; changing the key signs everyone out and breaks encrypted values) and `php artisan db:seed --force`. In production this creates the roles, permissions and grading defaults but **no demo accounts or demo data**. Then create the first super admin:

```bash
php artisan tinker
>>> $u = App\Models\User::create(['name' => '…', 'email' => '…', 'password' => '…']);
>>> $u->forceFill(['email_verified_at' => now(), 'is_active' => true])->save();
>>> $u->assignRole('super-admin');
```

## Production checklist

### Environment

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` set to the real `https://` address
- [ ] `APP_KEY` generated once and kept in the server's secrets, not in the repository
- [ ] `LOG_LEVEL=warning` or `error`, with `LOG_STACK=daily` (or a log service) so logs do not grow forever
- [ ] `SCHOOL_REGISTRATION_ENABLED=false` unless the school wants self-registration
- [ ] `SCHOOL_AUDIT_STORE_IP` matches the school's privacy policy

### HTTPS and sessions

- [ ] HTTPS everywhere, with HTTP redirected to HTTPS
- [ ] `SESSION_SECURE_COOKIE=true`; `SESSION_DRIVER=database` (or `redis`)
- [ ] If behind a load balancer or proxy, its addresses are trusted (`trustProxies` in `bootstrap/app.php`), so links and secure cookies use `https`

### Database and backups

- [ ] A dedicated database user with access to this database only
- [ ] Nightly database backups, kept off the server, and a restore tested at least once
- [ ] `storage/app/private` (uploaded files) included in the backups; files and database rows go together
- [ ] Never run `migrate:fresh` or `db:seed` with demo seeders on production

### Queue worker

Notifications and announcements are sent by the queue. Without a worker they wait in the `jobs` table.

- [ ] `QUEUE_CONNECTION=database` (or `redis`)
- [ ] A worker kept running by a process manager, for example Supervisor:

```ini
[program:school-worker]
command=php /var/www/school/artisan queue:work --sleep=3 --tries=3 --max-time=3600
user=www-data
numprocs=1
autostart=true
autorestart=true
stopwaitsecs=3600
stdout_logfile=/var/www/school/storage/logs/worker.log
```

- [ ] Failed jobs checked regularly (`php artisan queue:failed`)

### Scheduler

- [ ] One cron entry for the web server user: `* * * * * cd /var/www/school && php artisan schedule:run >> /dev/null 2>&1`. It closes expired exam attempts every minute.

### Storage and permissions

- [ ] The web server user can write to `storage/` and `bootstrap/cache/`, and nothing else in the project
- [ ] The web server's document root is `public/` only, so `storage/app/private` cannot be reached from a browser
- [ ] `SCHOOL_ATTACHMENT_MAX_KB` is not larger than PHP's `upload_max_filesize` and `post_max_size`, or the web server's body limit (for nginx, `client_max_body_size`)

### Mail

- [ ] `MAIL_MAILER` set to a real service (SMTP, SES, Postmark or another), with `MAIL_FROM_ADDRESS` on a domain that has SPF and DKIM set up
- [ ] A password reset sent and received as a test

### Cache and performance

- [ ] `CACHE_STORE=database` (or `redis`); the analytics page caches its figures for five minutes
- [ ] `php artisan optimize` run on every release (see above)
- [ ] OPcache turned on in PHP
- [ ] `SCHOOL_SLOW_QUERY_MS` left on (500 by default) and the log checked for slow queries

### Realtime (optional)

- [ ] Only if wanted: `BROADCAST_CONNECTION=reverb`, `SCHOOL_REALTIME=true`, the `REVERB_*` keys set, the frontend built after setting them, and `php artisan reverb:start` kept running under Supervisor like the queue worker, behind the same HTTPS proxy (`wss://`)
- [ ] Without it, leave `BROADCAST_CONNECTION=log` and `SCHOOL_REALTIME=false`; pages check for notifications every minute

### Monitoring

- [ ] Errors reported somewhere someone will see them: an error tracker (Sentry, Flare, Bugsnag or Nightwatch) or a log alert
- [ ] Uptime checks on `/up` (Laravel's health route)
- [ ] Disk space on the server watched, since uploads and logs grow

### Before opening to users

- [ ] `php artisan test`, `vendor/bin/pint --test` and `npm run build` pass on the release
- [ ] `composer audit` and `npm audit --omit=dev` report no known vulnerabilities
- [ ] There is no `public/hot` file on the server (it is left by `npm run dev` and makes pages load scripts from a development server)
- [ ] The first super admin can sign in; demo accounts do **not** exist
- [ ] Grading settings, academic year and terms set up for the school

## Known limitations

- Realtime notifications were tested with automated tests, not against a live Reverb server; test them on the target server before relying on them.
- Bulk changes (for example recording attendance for a whole class) write one row at a time; this is fine for class-sized lists.
- The audit trail page counts all entries for its pagination; with millions of entries, consider archiving old ones.
- Teachers can moderate the forum by design (`forum.moderate`). Remove the permission from the teacher role in `PermissionRegistry` if only admins should moderate.
- Some policies rely on the permission alone (no ownership check) for actions only admins have, which is safe with the roles as shipped. Re-check them before giving those permissions to teachers or students.
