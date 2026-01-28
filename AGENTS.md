# Repository Guidelines

## Project Structure & Module Organization
- `backend/` is a Laravel 12 API (PHP 8.4). Core domains live in `backend/app/` (e.g., `Auth/`, `Game/`, `MatchMaking/`, `User/`). HTTP routes are under `backend/routes/`, tests in `backend/tests/`.
- `frontend/` is a React 19 + Vike SSR app. Pages are file-based under `frontend/pages/`, shared UI in `frontend/components/`, state in `frontend/store/`, and API helpers in `frontend/api/`.
- `infra/dev/` contains the Docker Compose dev environment (nginx, php-fpm, frontend, redis, mysql, etc.).

## Build, Test, and Development Commands
Run commands from the noted directories.
- Backend:
  - `php artisan test` (run all tests)
  - `php artisan test --testsuite=Unit` (unit tests only)
  - `php artisan test tests/Feature/MatchMaking` (targeted suite)
  - `./vendor/bin/pint` (format/lint PHP)
  - `php artisan migrate` (database migrations)
  - `php artisan app:match-making:daemon` (matchmaker daemon)
- Frontend:
  - `npm run dev` (Vite + SSR dev server)
  - `npm run build` (production build)
- Docker (from `infra/dev/`):
  - `docker-compose up -d` (start services)
  - `docker-compose exec workspace bash` (PHP CLI shell)
  - `docker-compose exec frontend sh` (Node shell)

## Coding Style & Naming Conventions
- Backend: PHP uses `declare(strict_types=1)` and Laravel conventions (PSR-4 namespaces, StudlyCase classes). Use Pint for formatting.
- Frontend: follow existing style (2-space indent, double quotes, no semicolons). React components are `PascalCase`, hooks are `useX`.
- File naming: Vike pages use `+Page.jsx`, route groups live in `(group-name)/` folders.

## Testing Guidelines
- Backend tests are PHPUnit via `php artisan test`. Test files live in `backend/tests/` and use `*Test.php` naming.
- No dedicated frontend test script is defined in `frontend/package.json`; add tests alongside components/pages if you introduce a framework.

## Commit & Pull Request Guidelines
- Commit history favors short, direct messages (e.g., “Fix …”, “Refine …”, “Basic … impl”). No strict prefixing required.
- PRs should include a concise summary, mention affected areas (`backend/` or `frontend/`), and note test results. Include screenshots for UI changes.

## Security & Configuration Tips
- Laravel secrets live in `backend/.env`; do not commit secrets. Configure Redis/MySQL via the Docker environment when possible.
