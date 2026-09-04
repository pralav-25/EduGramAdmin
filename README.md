# Edugram Admin API (PHP + MySQL)

This folder contains a minimal PHP backend (PDO) and SQL schema for the Edugram admin dashboard.

Files added:

- `sql/edu_schema.sql` - Creates `edu` database, tables, sample data and a view `student_test_counts`.
- `api/db.php` - PDO connection (update credentials via env vars or edit the file).
- `api/helpers.php` - CORS headers and JSON helper.
- `api/index.php` - Simple router and API endpoints.

Setup

1. Import the database schema into MySQL (run on your machine):

```bash
# from project root
mysql -u root -p < sql/edu_schema.sql
```

2. Configure credentials and a JWT signing secret

Set `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, and `DB_PORT` as needed. Also set a strong `JWT_SECRET`; login and score submission fail closed when it is missing.

```bash
export JWT_SECRET='replace-with-a-long-random-value'
```

3. Start PHP built-in server for development

```bash
php -S 127.0.0.1:8000 -t api
```

4. Example endpoints

- GET /index.php?route=students  -> list students with tests_attended
- GET /index.php?route=teachers  -> list teachers
- GET /index.php?route=games -> list games
- GET /index.php?route=games/game1/scores -> scores for game1
- GET /index.php?route=leaderboard -> top scores across games
- POST /index.php?route=scores/game1  with JSON {"student_id":1,"score":80,"subject":"Math"}

Dashboard (static files)

You can open the files under the `dashboard/` folder in a browser. For the frontend to call the API, run the PHP built-in server from the project root and point the browser at `dashboard/index.html` served via a simple static server or open the file directly and ensure `api/index.php` is reachable at `../api/index.php?route=` relative to the HTML files.

Quick local setup (recommended):

```bash
# start PHP server for API
php -S 127.0.0.1:8000 -t api

# open dashboard in a simple static server (optional) e.g. Python
python3 -m http.server 8080 --directory dashboard

# then browse to http://127.0.0.1:8080/index.html
```

Notes & Next steps

- This backend is intentionally simple and suitable for local development. It uses prepared statements, bcrypt password hashes, expiring JWTs, and role-aware score submission, but still requires a production security review before deployment.
- For realtime game data, consider using a message queue or WebSockets. I can add Server-Sent Events or a simple Socket server next.

Authentication & realtime

- A simple login endpoint is available at `POST /index.php?route=auth/login` with JSON {"email","password"}. The seeded users use password `password` for demo; the schema stores it as a bcrypt hash. The endpoint returns an expiring JWT token that you can include as an `Authorization: Bearer <token>` header when calling protected endpoints (e.g. posting scores).
- For SSE (Server-Sent Events) you can create an `events` table to capture new score events for streaming to dashboards. Example SQL to create it:

```sql
CREATE TABLE events (
	id INT AUTO_INCREMENT PRIMARY KEY,
	type VARCHAR(50),
	payload JSON,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

The API will try to append new score posts to this table (if present). I can add an SSE endpoint that streams these rows as they arrive.
