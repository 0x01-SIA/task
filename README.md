# Task App

Minimal PHP foundation for a lightweight field service management application. This project intentionally stays simple: plain PHP, PDO, MySQL/MariaDB, server-rendered HTML, Bootstrap via CDN, and only minimal vanilla JavaScript.

Manual task management is available for administrators and dispatchers, and `jobs.task_id` intentionally remains nullable so existing standalone jobs continue to work. Job detail pages now also support authenticated attachment downloads, job photo uploads, and completed-job customer confirmation with private signature storage.

The application also includes a lightweight materials catalogue with job-level material usage tracking. Administrators and dispatchers can manage catalogue items and correct usage on any job, while workers can record materials only on their own open jobs.

## Requirements

- PHP 8.1 or newer with PDO and `pdo_mysql`
- MySQL or MariaDB

## Folder structure

```text
/
├── public/              Web root and front controller
├── app/config/          Environment-backed configuration
├── app/database/        PDO connection helper
├── bin/                 Optional command-line helpers
├── database/            SQL schema and seed data
├── app/views/           Layout and page views
├── app/helpers.php      Shared helper functions
├── .env.example         Environment template
└── README.md
```

## Local setup

1. Copy `.env.example` to `.env`.
2. Update the application and database values in `.env`.
3. Create an empty MySQL or MariaDB database.
4. Load the schema manually or by using the helper script below.

Local development credentials in this README are for development only.

## Environment configuration

Supported variables:

- `APP_NAME`
- `APP_ENV`
- `APP_DEBUG`
- `APP_URL`
- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USERNAME`
- `DB_PASSWORD`
- `UPLOAD_BASE_DIR`
- `JOB_ATTACHMENT_MAX_BYTES`
- `JOB_PHOTO_MAX_BYTES`
- `JOB_PHOTO_MAX_FILES`

Sensitive values belong in `.env`, which is ignored by Git.

## Database setup

Database credentials are read from `.env`:

- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USERNAME`
- `DB_PASSWORD`

Example local database creation:

```bash
mysql -u root -p -e "CREATE DATABASE task_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Example local MariaDB user creation:

```bash
mysql -u root -p -e "CREATE USER 'task_app'@'127.0.0.1' IDENTIFIED BY 'task_app_dev';"
mysql -u root -p -e "GRANT ALL PRIVILEGES ON task_app.* TO 'task_app'@'127.0.0.1'; FLUSH PRIVILEGES;"
```

Create `.env` from the example and set the local development values:

```text
APP_URL=http://127.0.0.1:8080
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=task_app
DB_USERNAME=task_app
DB_PASSWORD=task_app_dev
UPLOAD_BASE_DIR=/absolute/path/to/task/storage/uploads
JOB_ATTACHMENT_MAX_BYTES=10485760
JOB_PHOTO_MAX_BYTES=26214400
JOB_PHOTO_MAX_FILES=10
```

Run the schema manually:

```bash
mysql -u root -p task_app < database/schema.sql
```

Load the optional local development seed data:

```bash
mysql -u root -p task_app < database/seed.sql
```

Or use the included PDO helper script:

```bash
php bin/setup-database.php
```

This command loads both the schema and the local development seed data. Use `php bin/setup-database.php --no-seed` only when you explicitly want schema-only setup.

To upgrade an existing development or production database in place, run:

```bash
php bin/upgrade-database.php
```

This upgrade path is idempotent and is the supported deployment path for existing installations. It applies the multi-company schema changes, adds `super_admin` support, and clears only disposable test application data when an older pre-company schema is detected.

Create the first super admin without manual SQL:

```bash
php bin/create-super-admin.php --email=you@example.com --name="Your Name" --password='change-me-now'
```

Existing production deployments should use:

```bash
cd /var/www/task-app
php bin/upgrade-database.php
```

If the installation does not yet have an active super admin after the upgrade, run:

```bash
cd /var/www/task-app
php bin/create-super-admin.php --email=you@example.com --name="Your Name" --password='change-me-now'
```

## Upload storage

- Uploaded files are stored outside the public web root by default in `storage/uploads`.
- If that project-local directory is not writable for the PHP runtime, the app falls back to a non-public system temp directory such as `/tmp/task-app-uploads`.
- Attachment files are written under `storage/uploads/jobs/{job_id}/attachments/`.
- Photo files are written under `storage/uploads/jobs/{job_id}/photos/`.
- Customer confirmation signature files are written under `storage/uploads/jobs/{job_id}/confirmations/`.
- The upload directory is ignored by Git via `.gitignore`.
- In production, `UPLOAD_BASE_DIR` is recommended so uploads land in a stable writable location managed outside the release tree.
- Customer signatures are served only through authenticated application routes and never via a public filesystem path.

## Authentication

This project includes simple session-based authentication with role checks and a shared authenticated shell for signed-in pages.

Supported roles:

- `super_admin`
- `admin`
- `dispatcher`
- `worker`

Authenticated routes:

- `GET /` redirects guests to `/login` and signed-in users to their role landing page
- `GET /login` shows the login form for guests and redirects signed-in users to their role landing page
- `POST /login` verifies credentials and starts a session
- `POST /logout` ends the session
- `GET /dashboard` is the administrator and dispatcher landing page
- `GET /work` is the worker landing page and assigned job list
- `GET /customers` lists customers for administrators and dispatchers
- `GET /customers/{id}` shows a customer with its locations and an add-location action
- `GET /locations` lists locations and supports optional filtering with `?customer_id={id}`
- `GET /locations/create` shows the create form and accepts optional `?customer_id={id}` preselection
- `POST /locations` creates a location
- `GET /locations/{id}` shows location details
- `GET /locations/{id}/edit` shows the edit form
- `POST /locations/{id}/edit` updates a location, including active or inactive state
- `GET /tasks` lists tasks for administrators and dispatchers with search, status, priority, customer, and due-state filters
- `GET /tasks/create` shows the create-task form for administrators and dispatchers
- `POST /tasks` creates a task
- `GET /tasks/{id}` shows a task, its linked jobs, and task actions
- `GET /tasks/{id}/edit` shows the edit-task form for administrators and dispatchers
- `POST /tasks/{id}/edit` updates a task
- `POST /tasks/{id}/status` updates a task status
- `GET /tasks/{id}/jobs/create` redirects into task-linked job creation
- `GET /jobs` lists jobs for administrators and dispatchers
- `GET /jobs/calendar` shows the job calendar for administrators and dispatchers, defaults to Week view, and accepts `?view=week|month`, `?date=YYYY-MM-DD`, and `?month=YYYY-MM`
- `GET /jobs/create` shows the create-job form for administrators and dispatchers and accepts optional `?task_id={id}` preselection
- `POST /jobs` creates a job
- `GET /materials` lists materials for administrators and dispatchers with search and active/inactive filtering
- `GET /materials/create` shows the create-material form for administrators and dispatchers
- `POST /materials/create` creates a material
- `GET /materials/{id}` shows a material detail page
- `GET /materials/{id}/edit` shows the edit-material form
- `POST /materials/{id}/edit` updates a material
- `POST /materials/{id}/status` activates or deactivates a material without deleting historical job usage
- `GET /jobs/{id}` shows a job for administrators and dispatchers
- `POST /jobs/{id}/materials` records job material usage for administrators and dispatchers
- `POST /jobs/{id}/materials/{jobMaterialId}/edit` corrects a recorded material quantity for administrators and dispatchers
- `POST /jobs/{id}/materials/{jobMaterialId}/delete` removes a recorded material usage entry for administrators and dispatchers
- `POST /jobs/{id}/customer-confirmation` records customer confirmation for a completed job
- `GET /jobs/{id}/customer-confirmation/signature` renders the stored confirmation signature after authentication and access checks
- `POST /jobs/{id}/customer-confirmation/delete` removes an existing confirmation for administrators only
- `POST /jobs/{id}/attachments` uploads a job attachment for administrators and dispatchers
- `GET /jobs/{id}/attachments/{attachmentId}/download` downloads a job attachment through an authenticated route
- `POST /jobs/{id}/attachments/{attachmentId}/delete` deletes a job attachment for administrators and dispatchers
- `POST /jobs/{id}/photos` uploads a job photo for administrators and dispatchers
- The photo upload endpoint accepts one or more files and stores each successful photo as a separate private upload record
- `GET /jobs/{id}/photos/{photoId}/view` opens a job photo through an authenticated route
- `POST /jobs/{id}/photos/{photoId}/delete` deletes a job photo while the job is still open
- `GET /jobs/{id}/edit` shows the edit-job form for administrators and dispatchers
- `POST /jobs/{id}/edit` updates a job
- `POST /jobs/{id}/cancel` cancels a job
- `POST /jobs/{id}/reactivate` reactivates a cancelled job
- `GET /users` lists users for administrators only and supports `search`, `role`, and `is_active` filters
- `GET /users/create` shows the create-user form for administrators only
- `POST /users` creates a user account for administrators only
- `GET /users/{id}` shows an administrator-only user detail page with assignment history and password reset controls
- `GET /users/{id}/edit` shows the edit-user form for administrators only
- `POST /users/{id}/edit` updates name, email, role, and active state for administrators only
- `POST /users/{id}/password` resets a user's password for administrators only
- `GET /work/jobs/{id}` shows a worker-facing job detail page
- `POST /jobs/{id}/customer-confirmation` also accepts submissions from the assigned worker and redirects back to `/work/jobs/{id}`
- `POST /work/jobs/{id}/materials` records job material usage on a worker-accessible open job
- `POST /work/jobs/{id}/materials/{jobMaterialId}/edit` corrects a recorded material quantity while the job remains open
- `POST /work/jobs/{id}/materials/{jobMaterialId}/delete` removes a recorded material usage entry while the job remains open
- `POST /work/jobs/{id}/photos` uploads a photo to a worker-accessible job
- The worker photo upload endpoint also accepts one or more files and stores each successful photo separately
- `POST /work/jobs/{id}/photos/{photoId}/delete` deletes a job photo while the job is still open and the worker still has access
- `POST /work/jobs/{id}/start` transitions a worker-accessible job to `in_progress`
- `POST /work/jobs/{id}/complete` transitions an in-progress worker-accessible job to `completed`
- `POST /work/jobs/{id}/notes` creates a plain-text job note

Role access summary:

- `admin`: full customer, location, task, and job-management access, plus access to worker-facing `/work` pages for testing and operational review
- `dispatcher`: the same customer, location, task, and job-management access as `admin`, plus access to worker-facing `/work` pages
- `worker`: `/work`, `GET /work/jobs/{id}`, `POST /work/jobs/{id}/start`, `POST /work/jobs/{id}/complete`, and `POST /work/jobs/{id}/notes`

## Progressive Web App

Task App includes installable Progressive Web App support for Chromium-based browsers with an online-first service worker.

### Android installation

1. Open `https://task.0x01.lv` in Chrome on Android and sign in.
2. Open the account menu.
3. Select `Install Task App` when it appears.
4. Accept the browser install prompt.
5. Launch Task App from the home screen in its standalone window.

The `Install Task App` action is hidden by default and only appears after the browser fires `beforeinstallprompt`. It hides again after the prompt is used or when the app is already installed.

### Current limitations

- Navigation requests stay online-first and always try the live server first.
- Authenticated HTML pages and API responses are never cached for offline use.
- When the network is unavailable, navigation falls back only to `public/offline.html`.
- Users cannot reliably create, edit, or sync jobs offline in this release.
- Background sync and offline mutation replay are intentionally out of scope for the current implementation.

### Icons

PWA icon files live under `public/assets/images/icons/`:

- `icon-192.png`
- `icon-512.png`
- `icon-maskable-512.png`

The source artwork remains `public/assets/images/task-app-icon.png`.

To regenerate the icons from the source image on macOS:

```bash
mkdir -p public/assets/images/icons /tmp/task-pwa-icons
cp public/assets/images/task-app-icon.png /tmp/task-pwa-icons/source.png
sips --resampleHeightWidthMax 192 /tmp/task-pwa-icons/source.png --out /tmp/task-pwa-icons/icon-192-resized.png
sips --padToHeightWidth 192 192 --padColor 6e6e6e /tmp/task-pwa-icons/icon-192-resized.png --out public/assets/images/icons/icon-192.png
sips --resampleHeightWidthMax 512 /tmp/task-pwa-icons/source.png --out /tmp/task-pwa-icons/icon-512-resized.png
sips --padToHeightWidth 512 512 --padColor 6e6e6e /tmp/task-pwa-icons/icon-512-resized.png --out public/assets/images/icons/icon-512.png
sips --resampleHeightWidthMax 410 /tmp/task-pwa-icons/source.png --out /tmp/task-pwa-icons/icon-maskable-resized.png
sips --padToHeightWidth 512 512 --padColor 6e6e6e /tmp/task-pwa-icons/icon-maskable-resized.png --out public/assets/images/icons/icon-maskable-512.png
```

If the base icon artwork changes, regenerate all three derived files and keep extra safe padding on the maskable icon so Android launchers do not crop important details.

### Service worker cache versioning

- The service worker cache name is defined by `CACHE_VERSION` in `public/service-worker.js`.
- Keep the cache prefix as `task-app-` so cleanup only touches Task App caches.
- Increment `CACHE_VERSION` whenever cached asset URLs, offline behavior, or precache lists change.
- After incrementing the version, deploy `service-worker.js` with `Cache-Control: no-cache` so browsers revalidate it promptly.

### Production Nginx configuration

Production must serve the PWA files with the correct MIME types and cache headers:

- `manifest.webmanifest`: `Content-Type: application/manifest+json`
- `service-worker.js`: a JavaScript MIME type such as `application/javascript`
- `service-worker.js`: `Cache-Control: no-cache`
- `offline.html`: standard HTML content type

Example Nginx snippets:

```nginx
types {
    application/manifest+json webmanifest;
}

location = /manifest.webmanifest {
    add_header Cache-Control "public, max-age=300";
}

location = /service-worker.js {
    add_header Cache-Control "no-cache";
}

location = /offline.html {
    add_header Cache-Control "public, max-age=300";
}
```

### Deployment verification

Run these exact commands after deployment:

```bash
curl -I https://task.0x01.lv/manifest.webmanifest
curl -I https://task.0x01.lv/service-worker.js
curl -I https://task.0x01.lv/offline.html
```

Expected results:

- All three requests return successful responses.
- None of them redirect to `/login`.
- `manifest.webmanifest` is served as `application/manifest+json`.
- `service-worker.js` is served with a JavaScript content type.
- `service-worker.js` is served with `Cache-Control: no-cache`.

Browser checks after deployment:

1. Open Chrome DevTools and confirm the Manifest panel shows `Task App`, the three icons, and the shortcuts for `/work`, `/jobs`, and `/jobs/calendar`.
2. In the Application panel, confirm the registered service worker scope is `/` and that it is active.
3. In the Network panel, go offline and verify a fresh navigation falls back to the offline page instead of a cached authenticated page.
4. In Chrome on Android, verify that `Install Task App` appears only when installable, launches the browser install prompt, and disappears after installation.

Job calendar behavior:

- `/jobs/calendar` defaults to the current week when no valid calendar query parameters are supplied.
- Supported views are `week` and `month`, selected with `?view=week` or `?view=month`. Invalid view values fall back to `week`.
- Week view uses `?date=YYYY-MM-DD` as its anchor date, starts on Monday, and falls back to today when `date` is missing or invalid.
- Week view always shows Monday through Friday, and only shows Saturday or Sunday when at least one job is planned on that day.
- Month view uses `?month=YYYY-MM` and falls back to the current month when `month` is missing or invalid.
- Scheduled jobs are placed on the calendar using the existing `jobs.planned_date` field.
- Jobs without a planned date are excluded from calendar day cells and are summarized separately as unscheduled active jobs.
- Month-view overflow links such as `+2 more` open the week containing that date.
- Cancelled jobs remain visible on the calendar with muted styling to match the existing job-list behavior.

User-management access rules:

- Only `admin` users may access `/users`, `/users/create`, `/users/{id}`, `/users/{id}/edit`, and `/users/{id}/password`.
- `dispatcher` and `worker` accounts receive HTTP `403` on all user-management routes even if a URL is entered directly.
- The navigation shell shows the `Users` link only to administrators, but route permissions are also enforced server-side.

User-management safety rules:

- Inactive users cannot log in.
- Inactive workers remain visible in historical records, but they are excluded from job-assignment options in create and edit job forms.
- An administrator cannot deactivate their own currently signed-in account.
- An administrator cannot remove their own `admin` role.
- The last active administrator cannot be deactivated or changed to another role.
- Administrator password resets use a separate form on the user detail page and update only `password_hash`.

Worker permission rules:

- Workers can only view jobs assigned to their own account.
- Workers can only start, complete, add notes to, and record materials on their own open jobs.
- Inactive materials remain visible in historical job usage but cannot be selected for new entries.
- Workers can only record customer confirmation for their own completed jobs.
- Workers can view job attachments assigned to their own jobs, but cannot upload or delete general attachments.
- Workers can upload photos to their own jobs and can delete those photos only while the job remains open.
- Photo uploads remain available even after a job has been completed or closed, but photo deletion still requires the job to be open.

## Production upload limits

The application photo limit is controlled separately from general attachments:

```text
JOB_PHOTO_MAX_BYTES=26214400
JOB_PHOTO_MAX_FILES=10
```

To allow multiple high-resolution photos through the full request chain, production PHP and Nginx limits must also be raised. Update the relevant PHP-FPM or PHP runtime `.ini` file with:

```ini
upload_max_filesize = 25M
post_max_size = 150M
max_file_uploads = 20
```

Then update the Nginx site or server block:

```nginx
client_max_body_size 150M;
```

These settings are not changed automatically by the application. After editing production configuration, reload the services with commands appropriate for the host, for example:

```bash
sudo nginx -t
sudo systemctl reload nginx
sudo systemctl reload php8.2-fpm
```

If your distribution or PHP version uses different service names, adjust the reload command accordingly. Keep `post_max_size` and `client_max_body_size` large enough for several 25 MB photos plus request overhead.
- Workers cannot access `/tasks`, `/tasks/create`, `/tasks/{id}`, `/tasks/{id}/edit`, `/tasks/{id}/status`, or `/tasks/{id}/jobs/create`.
- Workers cannot access `/jobs`, `/jobs/create`, `/jobs/{id}`, `/jobs/{id}/edit`, or the job cancel/reactivate routes.
- Unassigned jobs and jobs assigned to another worker return no worker-facing detail to the requesting worker.

Worker workflow status rules:

- Assigned scheduled jobs move from `planned` to `in_progress`.
- Only `in_progress` jobs can move to `completed`.
- Cancelled jobs remain read-only in the worker workflow.
- The workflow reuses the canonical job statuses: `draft`, `planned`, `in_progress`, `completed`, and `cancelled`.

## Customer confirmation

- One customer confirmation may be stored per job.
- `customer_name` and a drawn signature are required. `customer_email` is optional and validated when supplied.
- The confirmation timestamp is generated on the server and stored in `confirmed_at`.
- The user who records the confirmation is stored in `confirmed_by_user_id`.
- Administrators, dispatchers, and the assigned worker may record confirmation, but only after the job has reached `completed`.
- Existing confirmations render read-only on both administrator and worker job detail pages.
- Administrators may remove a confirmation with a CSRF-protected POST action and then capture a replacement if needed.
- No extra upload root is required when `UPLOAD_BASE_DIR` or the default private upload storage is already available to the PHP runtime.

## Development seed credentials

These credentials are for local development only, and each account uses the password `password`:

- `admin@example.test`
- `dispatcher@example.test`
- `worker@example.test`
- `worker.two@example.test`
- `worker.inactive@example.test` (inactive and expected to fail login)

The seed data creates:

- One active admin, one active dispatcher, two active workers, and one inactive worker
- Jobs assigned to demonstrate unassigned, overdue, in-progress, completed-today, cancelled, and another-worker scenarios
- Sample job notes for worker detail testing

## Dashboard testing checklist

1. Log in as `admin@example.test` and confirm all summary counts render on `/dashboard`.
2. Open job links from each dashboard section and confirm they load the correct `/jobs/{id}` detail page.
3. Confirm overdue and unassigned-soon jobs appear under Jobs requiring attention.
4. Confirm Today&apos;s schedule lists current-day jobs in start-time order, with untimed jobs after timed jobs.
5. Confirm Active workers shows workers with `in_progress` jobs and lists the relevant job numbers.
6. Confirm Recently completed jobs shows the latest completed records.
7. Log in as `dispatcher@example.test` and confirm the same operational dashboard is available.
8. Log in as `worker@example.test` and confirm the app still routes to `/work` instead of exposing the admin dashboard.
9. Remove or update the relevant records locally and confirm each empty dashboard section renders cleanly without warnings.
10. Re-run `php bin/setup-database.php` and confirm the seed restores the dashboard scenarios.

## Manual testing

1. Start the app with `php -S localhost:8000 -t public`.
2. Open `/dashboard` and `/work` as a guest and confirm both redirect to `/login`.
3. Submit invalid credentials and confirm the page shows `Invalid email or password`.
4. Sign in with `worker@example.test` and confirm the app redirects to `/work`.
5. Confirm `/work` shows separate Today, Upcoming, and Completed sections with the seeded assigned jobs.
6. Open an assigned job from `/work` and confirm the worker detail page shows status, schedule, customer, location, address, description, and notes.
7. As `worker@example.test`, open `/work/jobs/6` and confirm the app does not expose another worker's job.
8. Start a valid planned worker job and confirm the status changes to `in_progress` with a success message.
9. Attempt to start an already in-progress, completed, or cancelled worker job and confirm the request is rejected.
10. Complete an in-progress worker job and confirm the status changes to `completed` with a success message.
11. Attempt to complete a job that is not in progress and confirm the request is rejected.
12. Add a valid note to an assigned job and confirm it appears in chronological order.
13. Submit an empty worker note and confirm validation is shown.
14. Confirm note content is safely escaped by entering HTML-like text and verifying it renders as text.
15. Confirm cancelled jobs never show Start or Complete actions.
16. Sign in with `admin@example.test` and confirm `/jobs` management still works, plus `/work/jobs/{id}` opens for operational review.
17. Sign in with `admin@example.test` and confirm `/dashboard` shows summary cards, jobs requiring attention, today&apos;s schedule, active workers, and recently completed jobs.
18. Sign in with `dispatcher@example.test` and confirm both `/dashboard` and job-management pages still work.
19. As `worker@example.test`, confirm sign-in still redirects to `/work` and `/jobs` plus `/dashboard` each return HTTP `403`.
20. Run `php bin/setup-database.php` twice consecutively and confirm both runs succeed.
21. Open `/jobs/calendar`, `/jobs/calendar?view=week`, `/jobs/calendar?view=week&date=2026-07-27`, `/jobs/calendar?view=week&date=invalid`, `/jobs/calendar?view=month`, `/jobs/calendar?view=month&month=2026-07`, `/jobs/calendar?view=month&month=invalid`, and `/jobs/calendar?view=invalid`.
22. Confirm Week is the default view, that week navigation moves by seven days, and that Month navigation moves by one month.
23. Confirm month-view `+N more` links open the relevant week view.
24. As `worker@example.test`, confirm `/jobs/calendar` returns HTTP `403`.
25. Run `php -l` on each changed PHP file and confirm there are no syntax errors.

## User management testing

1. Sign in as `admin@example.test` and open `/users`.
2. Filter the user list by role, active status, and search text.
3. Create a worker account and confirm the app redirects to `/users/{id}` with a success message.
4. Edit that worker and confirm role, email, and active state changes persist.
5. Deactivate the worker, confirm login fails for that account, then reactivate it.
6. Reset the worker password from `/users/{id}` and confirm only the new password works.
7. Open a seeded user detail page and confirm assigned-job counts and recent assigned jobs render.
8. Sign in as `dispatcher@example.test` and confirm `/users` returns HTTP `403`.
9. Sign in as `worker@example.test` and confirm `/users` returns HTTP `403`.
10. Submit duplicate email, invalid role, mismatched password confirmation, and missing-user URLs to confirm validation plus `404` handling.
11. While signed in as `admin@example.test`, confirm you cannot deactivate yourself or remove your own `admin` role.
12. Confirm the last active administrator cannot be deactivated or demoted.

For full local auth testing with the configured `.env`, you can also run:

```bash
php -S 127.0.0.1:8080 -t public
```

## Data model

```text
Customer
├── Location
├── Task
│   └── Jobs
└── Standalone Jobs
    └── Job notes
```

A Task always belongs to a customer and may also reference a customer location. This means Tasks can be stored directly under a customer even when no specific location is selected yet, while jobs can either link back to a task or remain standalone for compatibility.

## Database behavior

The home page reports one of three database states:

- `Connected`
- `Not configured`
- `Connection failed`

Detailed connection errors are only shown when `APP_DEBUG=true`.

## Start the development server

From the project root:

```bash
php -S localhost:8000 -t public
```

Then open [http://localhost:8000](http://localhost:8000).

## Expected result

The home page should show:

- The application name
- The “Lightweight field service management” description
- Application status as running
- Database status
- Current environment

Routes available:

- `/`
- `/error`
- Unknown routes return a 404 error page
