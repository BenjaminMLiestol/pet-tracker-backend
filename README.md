# pet-tracker-backend

Laravel 12 JSON API for the Pet Tracker mobile app (feedings, walks, baths, weights and vet/vaccination/parasite care records). Authentication uses Laravel Sanctum bearer tokens.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # default DB_CONNECTION=sqlite
php artisan migrate --seed       # seeds users from SEED_USER_* in .env, plus a sample pet
php artisan serve --host=0.0.0.0 # http://localhost:8000/api/v1
```

Seeded users come from `SEED_USER_1_EMAIL`, `SEED_USER_1_PASSWORD`, ... in `.env` (see `.env.example`); no credentials are stored in the repo. A user without a password gets a random one printed once.

Point the frontend at it with `EXPO_PUBLIC_API_URL` (see the frontend's `.env.example`).

```bash
php artisan test
```

Set `APP_DEBUG=false` and `APP_ENV=production` outside local development.

## Conventions

- Every `/api/v1` response is JSON, errors included (`{"message": "..."}`): `401` unauthenticated or bad login, `404` not found **or not yours**, `422` validation (`{"message", "errors"}`), `429` throttled.
- All pet data is scoped to the pets the signed-in user owns. Other users' records answer `404`.
- Dates are ISO 8601 and stored in UTC. Deletes are soft deletes.
- Lists are paginated Laravel-style (`{"data": [...], "total": ...}`): `?per_page=1..500` (default 100) and `?since=<date>` (on the resource's date column).

## Endpoints

All routes are under `/api/v1`; everything except register/login needs `Authorization: Bearer <token>`.

### Auth (register/login throttled to 5 per minute per email + IP)

| Method | Path | Body | Notes |
| --- | --- | --- | --- |
| POST | `/auth/register` | `email, password (min 8), first_name, last_name, date_of_birth?` | `201 {user, token}` |
| POST | `/auth/login` | `email, password` | `{user, token}`; `401` on bad credentials |
| GET | `/auth/me` | | current user |
| PATCH | `/auth/me` | any register field | update own account |
| DELETE | `/auth/me` | | delete own account and revoke tokens |
| POST | `/auth/logout` | | revoke the current token |

### Pets

`GET|POST /pets`, `GET|PATCH|DELETE /pets/{id}`

Fields: `name` (required), `breed?`, `date_of_birth?`, `user_ids?` (extra owners; you are always an owner). `age` is read-only, derived from `date_of_birth`. Deleting a pet also soft-deletes its records.

### Per-pet records

Each supports `GET /x` (`?pet_id=`, `?since=`, `?per_page=`), `POST /x`, `GET|PATCH|DELETE /x/{id}`. `pet_id` is required on create and must be one of your pets. An omitted or `null` date defaults to now.

| Resource | Fields | Date column |
| --- | --- | --- |
| `/feedings` | `feed_check?` (bool, default true), `fed_at?` | `fed_at` |
| `/walks` | `walk_check?` (bool, default true), `walked_at?` | `walked_at` |
| `/baths` | `bathed_at?` | `bathed_at` |
| `/weights` | `value` (kg, 0.01-500), `weighed_at?` | `weighed_at` |
| `/vet-visits` | `reason`, `visited_at?`, `next_visit_at?`, `notes?` | `visited_at` |
| `/vaccinations` | `name`, `given_at?`, `next_due_at?`, `notes?` | `given_at` |
| `/parasite-treatments` | `type` (`flea`\|`tick`\|`worm`), `product?`, `given_at?`, `next_due_at?`, `notes?` | `given_at` |

`next_*` dates may not fall on an earlier day than the visit/treatment date.

## Adding a per-pet resource

Create a migration (with `pet_id`, `user_id`, soft deletes), a model, and a controller extending `RecordController` that declares `model()`, `dateColumn()`, `storeRules()` and `updateRules()`; register it with `Route::apiResource`, and add the relation to `Pet::CASCADE` so pet deletes cascade.
