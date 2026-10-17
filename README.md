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

## Logins

A login token lasts 7 days (`AuthController::TOKEN_DAYS`). The app calls `/auth/refresh` when it
opens, so regular use never expires and a lost phone goes quiet within a week. Expired tokens are
pruned daily by the scheduler (`php artisan schedule:work` locally, a cron entry in production).

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
| PATCH | `/auth/me` | any register field, `feeding_reminder_time?`, `walk_reminder_time?` | update own account. Reminder times are personal, 24h `HH:MM` (default `18:00` / `19:00`) |
| DELETE | `/auth/me` | | delete own account and revoke tokens |
| POST | `/auth/refresh` | | `{user, token}`: swaps the current login for a fresh 7-day one. The old token works for 5 more minutes (so a lost reply doesn't sign anyone out) |
| POST | `/auth/logout` | | revoke the current token |
| POST | `/auth/logout-all` | | revoke every token of this account (sign out everywhere) |

### Pets

`GET|POST /pets`, `GET|PATCH|DELETE /pets/{id}`

Fields: `name` (required), `breed?`, `timezone?` (IANA, default `Europe/Oslo`; decides where "today" starts), `bath_interval_days?` (1-365, default 30; shared by all owners), `date_of_birth?`, You are the first owner; others join with an invitation code (below). `age` is read-only, derived from `date_of_birth`. Deleting a pet also soft-deletes its records.

### Sharing a dog (co-owners)

An owner creates a one-time code and shares it however they like; the other person enters it in the app. Codes are 8 characters from an alphabet without look-alikes (no `0/O`, `1/I/L`), single-use, and expire after 7 days. Typing is forgiving (`abcd-efgh` works). A dog can have 5 open invitations at once.

| Method | Path | Notes |
| --- | --- | --- |
| POST | `/pets/{id}/invitations` | owner only; `201 {id, pet_id, code, expires_at}` |
| GET | `/pets/{id}/invitations` | owner only; open invitations (a plain array) |
| DELETE | `/pets/{id}/invitations/{invitation}` | owner only; cancel a code |
| POST | `/invitations/accept` | `{code}`; you become a co-owner and get the pet back. Every failure (unknown, used, cancelled, expired, deleted dog) returns the same `422` message, and attempts are limited to 10 a minute |
| DELETE | `/pets/{id}/users/{userId}` | leave a dog (yourself) or remove a co-owner. A dog always keeps at least one owner |

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

## Push notifications

Co-owners are told when someone logs a feeding or a walk ("Benjamin fed Toya"). The app sends
its Expo push token to `POST /api/v1/devices` and removes it on logout with
`DELETE /api/v1/devices` (body `{"token": ...}`). Pushes go through Expo's relay
(`services.expo`), after the response, and never fail the request that caused them. Dead tokens
(`DeviceNotRegistered`) are deleted.

- Nobody is pinged for their own action, for entries logged more than 10 minutes in the past
  (corrections, undo), or when they turned off `notify_co_owners` on their profile.
- The message language follows the receiver's `locale` (`nb` or `en`), set with `PATCH /auth/me`.
- Delivery needs the app built with push enabled (a paid Apple Developer account for iOS) and an
  EAS project id; `EXPO_ACCESS_TOKEN` is only needed if enhanced push security is on.

## Adding a per-pet resource

Create a migration (with `pet_id`, `user_id`, soft deletes), a model, and a controller extending `RecordController` that declares `model()`, `dateColumn()`, `storeRules()` and `updateRules()`; register it with `Route::apiResource`, and add the relation to `Pet::CASCADE` so pet deletes cascade.
