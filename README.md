# Task App

Minimal PHP foundation for a lightweight field service management application. This project intentionally stays simple: plain PHP, PDO, MySQL/MariaDB, server-rendered HTML, Bootstrap via CDN, and only minimal vanilla JavaScript.

Current MVP scope also includes visible placeholder sections on job detail pages for deferred features. Attachments, job photos, and customer confirmation or signatures are intentionally postponed to a later version, and no upload or signature functionality is implemented in this repository.

## Deferred MVP placeholders

- Attachments are deferred.
- Job photos are deferred.
- Customer confirmation and signatures are deferred.
- Visible placeholders for these sections are included on job detail pages in the MVP.
- No upload, storage, or signature functionality has been implemented.

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

The SQL files remain usable on their own even if you use the helper script.

## Authentication

This project includes simple session-based authentication with role checks and a shared authenticated shell for signed-in pages.

Supported roles:

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
- `GET /tasks` is an administrator and dispatcher placeholder page
- `GET /jobs` lists jobs for administrators and dispatchers
- `GET /jobs/calendar` shows the job calendar for administrators and dispatchers, defaults to Week view, and accepts `?view=week|month`, `?date=YYYY-MM-DD`, and `?month=YYYY-MM`
- `GET /jobs/create` shows the create-job form for administrators and dispatchers
- `POST /jobs` creates a job
- `GET /jobs/{id}` shows a job for administrators and dispatchers
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
- `POST /work/jobs/{id}/start` transitions a worker-accessible job to `in_progress`
- `POST /work/jobs/{id}/complete` transitions an in-progress worker-accessible job to `completed`
- `POST /work/jobs/{id}/notes` creates a plain-text job note

Role access summary:

- `admin`: full customer, location, and job-management access, plus access to worker-facing `/work` pages for testing and operational review
- `dispatcher`: the same customer, location, and job-management access as `admin`, plus access to worker-facing `/work` pages
- `worker`: `/work`, `GET /work/jobs/{id}`, `POST /work/jobs/{id}/start`, `POST /work/jobs/{id}/complete`, and `POST /work/jobs/{id}/notes`

Job calendar behavior:

- `/jobs/calendar` defaults to the current week when no valid calendar query parameters are supplied.
- Supported views are `week` and `month`, selected with `?view=week` or `?view=month`. Invalid view values fall back to `week`.
- Week view uses `?date=YYYY-MM-DD` as its anchor date, starts on Monday, and falls back to today when `date` is missing or invalid.
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
- Workers can only start, complete, and add notes to their own jobs.
- Workers cannot access `/jobs`, `/jobs/create`, `/jobs/{id}`, `/jobs/{id}/edit`, or the job cancel/reactivate routes.
- Unassigned jobs and jobs assigned to another worker return no worker-facing detail to the requesting worker.

Worker workflow status rules:

- Assigned scheduled jobs move from `planned` to `in_progress`.
- Only `in_progress` jobs can move to `completed`.
- Cancelled jobs remain read-only in the worker workflow.
- The workflow reuses the canonical job statuses: `draft`, `planned`, `in_progress`, `completed`, and `cancelled`.

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
└── Location
    └── Task
        └── Jobs
            └── Job notes
```

A Task always belongs to a customer and may also reference a customer location. This means Tasks can be stored directly under a customer even when no specific location is selected yet.

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
