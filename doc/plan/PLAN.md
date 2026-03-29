# Workout Tracking App Plan

## Summary
Build a mobile-first gym coaching app on the existing Laravel 12 codebase using a Laravel API backend plus a Vue 3 + Vite SPA frontend. The first release supports `admin`, `trainer`, and `client` roles; invited users sign in with email/password, verify email, reset password, receive scheduled workouts, log completed sessions from phone, and review progress analytics focused on PRs and exercise trends.

## Implementation Changes
- Frontend stack: Vue 3 SPA with Vue Router, Pinia, Axios, and Chart.js; keep Laravel as the API/auth host and serve one SPA shell from `web.php`.
- Auth/account model: replace open self-registration with invite-created accounts; keep password reset and email verification; use Sanctum for SPA auth; add role-based authorization for `admin`, `trainer`, `client`.
- User model: keep `is_active` and soft-delete behavior, add `role` enum/string, stop using `is_admin` for permissions, and treat existing generic user APIs as legacy to be replaced by REST-style endpoints.
- Exercise catalog: add shared exercises plus trainer-created custom exercises. Exercise fields: name, slug, muscle group, equipment, movement type, default unit, visibility (`shared` or trainer-owned), active flag.
- Program model: add `programs`, `program_days`, and `program_day_exercises`. Programs belong to a client and trainer, have goal/name/status/date range, and contain date-assigned workout days. Each workout day stores its own exercise lineup so programming stays free-form.
- Session logging: add `workout_sessions`, `workout_session_exercises`, and `workout_session_sets`. Starting a session snapshots the planned workout into session tables so later program edits do not rewrite history. Both trainer and client can create/update sessions; capture who logged the entry and last editor.
- Body metrics: add `body_metrics` for client body weight and notes by date.
- Analytics: add dashboard queries/endpoints for heaviest set PR, estimated 1RM trend, total volume trend, recent completed sessions, adherence/completion rate, and body-weight trend. Scope analytics to client dashboards and trainer-over-client dashboards.
- SPA screens:
  - Auth: login, forgot/reset password, verify-email, invited-account activation.
  - Admin: user list, invite/create user, activate/deactivate, role assignment.
  - Trainer: exercise catalog, client list, create/edit programs, assign workout days to dates, view client progress.
  - Client: upcoming workouts, session logger, history, body-weight log, progress charts.
- API surface:
  - `/api/auth/*` for login/logout/password reset/email verification/account activation.
  - `/api/users`, `/api/clients`, `/api/trainers` for role-aware user management.
  - `/api/exercises` for shared/custom catalog CRUD.
  - `/api/programs` and `/api/program-days` for program builder and scheduling.
  - `/api/workout-sessions` for start, save progress, finish, edit history.
  - `/api/body-metrics` for weight and notes.
  - `/api/dashboard/*` for analytics summaries and charts.

## Public Interfaces / Data Rules
- Roles are fixed in v1: `admin`, `trainer`, `client`; no custom-role system.
- Accounts are created by admin or trainer invitation, not public signup.
- Trainers can manage only their own clients, custom exercises, programs, and analytics scope.
- Clients can view assigned workouts, log sessions, update their own body metrics, and view only their own analytics.
- Workout history is immutable at the structure level after session creation because it is stored as a snapshot; later program edits affect future sessions only.
- Scheduled workouts can still be logged ad hoc by allowing a session with no linked `program_day` when trainer/client starts an unscheduled workout.

## Test Plan
- Feature tests for login, logout, password reset, email verification, and invited-account activation.
- Authorization tests proving admins, trainers, and clients are restricted to the correct data.
- CRUD tests for exercises, programs, assigned workout days, and body metrics.
- Session tests for start/save/finish flows, snapshot integrity, trainer edit vs client edit, and unscheduled workout logging.
- Analytics tests for PR calculation, estimated 1RM trend, volume totals, adherence, and body-weight trend.
- SPA acceptance checks for mobile viewport usability on login, program assignment, live session logging, and dashboard screens.

## Assumptions And Defaults
- No payments, memberships, nutrition tracking, attendance kiosk, or offline/PWA support in v1.
- Email is available for invites, verification, and password reset.
- Charting uses Chart.js; state management uses Pinia.
- Existing leftover generic DIA-style user endpoints/controllers can be refactored or replaced rather than preserved as a public contract.
- Primary success criteria for v1: a trainer can invite a client, assign workouts to dates, the client can log sets/reps/weight from a phone in the gym, and both can view reliable progress analytics afterward.
