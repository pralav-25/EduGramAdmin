# EduGram Admin

[![Checks](https://github.com/pralav-25/EduGramAdmin/actions/workflows/ci.yml/badge.svg)](https://github.com/pralav-25/EduGramAdmin/actions/workflows/ci.yml)

A local PHP/MySQL prototype for exploring student and teacher directories,
game leaderboards, score submission, and a browser dashboard.

## Quick start

Requires PHP 8.2+ with `pdo_mysql`, MySQL 8, and Python 3 for integration checks.
Run these commands from the repository root:

```bash
mysql -u root -p < sql/edu_schema.sql
export DB_HOST=127.0.0.1 DB_PORT=3306 DB_NAME=edu DB_USER=root
export DB_PASS='your-local-database-password'
export JWT_SECRET="$(openssl rand -hex 32)"
php -S 127.0.0.1:8000 -t .
```

Open **http://127.0.0.1:8000/dashboard/index.html**. Serving the repository root
keeps the dashboard and `api/` on the same origin. A separate static server is
unnecessary. `.env.example` documents the variables; PHP does not load it
automatically. Use an isolated local database: the schema includes sample users
and score data and is intended for an initial import.

Seeded accounts use password `password`: `alice@example.com` and
`bob@example.com` (students), `carol@example.com` (teacher), and
`dave@example.com` (admin). These are demonstration accounts.

## Check the local database connection

After importing the schema and starting the PHP server, request the public
aggregate endpoint:

```bash
curl --fail --silent --show-error 'http://127.0.0.1:8000/api/index.php?route=stats/aggregates'
```

A successful response is a JSON array with `game_name`, `count`, and `avg_score`
for each of the four games. This checks that PHP can query the imported tables;
it does not test login or score-write permissions. If it fails, check the PHP
server log, `pdo_mysql`, the exported database variables, and the schema import
before troubleshooting the dashboard.

## API

All routes use `/api/index.php?route=`; for example:
`http://127.0.0.1:8000/api/index.php?route=leaderboard`.

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `students`, `teachers` | Search and paginated directories (`q`, `page`, `per_page`) |
| GET | `students/{id}` | Student details |
| GET | `games`, `games/game1/scores` | Games and scores |
| GET | `leaderboard`, `stats/aggregates` | Top scores and summaries |
| POST | `auth/login` | Exchange JSON email/password for a 24-hour HS256 token |
| GET | `users` | Admin-only user listing |
| POST | `scores/game1` through `scores/game4` | Authenticated score submission |

Include `Authorization: Bearer <token>` for protected routes. A student can
submit only their own scores; teacher/admin roles may submit for students.
Score payloads contain an integer `student_id`, integer `score` from 0–100,
and optional `subject` of at most 100 characters. JSON requests are capped at
64 KB; malformed JSON and non-object bodies return a clear client error.
Authentication fails closed unless `JWT_SECRET` has at least 32 characters.

Pagination uses native prepared statements with explicitly typed limits and
offsets. API failures return generic JSON errors; details go to PHP's error log.

## Validation

```bash
php tests/helpers_test.php
python3 tests/integration.py
```

Start the local server and import the demo schema before running integration
checks. Set `API_BASE` to test another local URL. GitHub Actions provisions a
fresh MySQL service, checks PHP syntax and token validation, and exercises login,
pagination, leaderboards, ownership checks, malformed input, and score bounds.

## Scope

This is an educational prototype. Directory and leaderboard reads are public,
and login has no rate limiter, password recovery, or account lifecycle. Do not
expose real student data or demo credentials on a public server. The JWT helper
is intentionally small; production adoption needs a maintained authentication
library and a complete authorization model.

The optional `stream/sse` route expects a separately provisioned `events` table.
It is not part of the default setup or integration checks. No realtime delivery
service is claimed by this repository.

Dashboard directories debounce searches, cancel stale responses, show errors and
empty results, and stop pagination at the actual result count. Summary totals
include all records; the attendance chart labels its first-page sample. Database
text is escaped before HTML rendering. Run `node --test tests/*.test.cjs` for the
shared UI safety and request-error checks.
