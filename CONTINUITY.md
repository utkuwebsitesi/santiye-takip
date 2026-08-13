# Şantiye Kasa — Continuity Guide

## Stack and setup
- Laravel 12, PHP 8.3+, Composer backend with Flutter/mobile and built distribution artifacts.
- Backend: `composer install`, copy `.env.example` to `.env`, configure a local DB, `php artisan key:generate`, `php artisan migrate --seed`. Mobile setup follows its Flutter package configuration.

## Tests and release
- `php artisan test` — last verified: 42 tests / 260 assertions passed.
- Release backend with no-dev Composer install, forced migrations and cache optimization; publish mobile only through a clean Flutter release build. Do not treat `dist` or uploads as source.

## Delivered / remaining
- Delivered: site cash/accounting flows, API/mobile foundation and tenant-isolation planning documented in `MULTI_TENANCY.md`.
- Remaining: staging isolation tests and approved physical per-customer MySQL rollout.

## Architecture and safety
- Preserve `tenant_slug`, `API_BASE_URL` and the planned separate operational database boundary. Never commit customer data, SQL, backups, secrets, logs or generated packages.
