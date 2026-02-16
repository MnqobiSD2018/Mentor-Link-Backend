# Copilot Instructions for Mentor Link (Backend API)

This project is a **Laravel 12** application serving as the backend for Mentor Link. It uses modern Laravel conventions (Bootstrap configuration, Vite).

## Architecture & Core Components

- **Framework:** Laravel 12.x (PHP 8.2+).
- **Configuration:** Configuration is centralized in `bootstrap/app.php` (Routing, Middleware, Exceptions) rather than `app/Http/Kernel.php` (removed in Laravel 11+).
- **Database:**
  - Default development database is **SQLite** (`database/database.sqlite`).
  - **Schema:**
    - `users`: Core identity (auth).
    - `mentor_profiles`: Extends user with mentor-specific fields (`user_id`, `bio`, `expertise`).
    - `mentee_profiles`: Extends user with mentee-specific fields.
    - `sessions`, `payments`, `ratings`: Core domain entities.
- **Frontend Integration:** Uses **Vite** (`vite.config.js`) for asset bundling, primarily for the `resources/` directory (Blade/JS/CSS).

## Developer Workflows

- **Local Development:**
  - Run server and helpers: `composer run dev` (Runs `artisan serve`, `queue:listen`, `pail`, `npm run dev` concurrently).
  - Database: `php artisan migrate --seed` to setup schema and default user.
- **Testing:**
  - Run tests: `php artisan test` (Uses Pest/PHPUnit as configured in `phpunit.xml`).

## Coding Conventions & Patterns

- **Routing:**
  - Current configuration only enables `routes/web.php` and `routes/console.php`.
  - **IMPORTANT:** If creating API endpoints, ensure `routes/api.php` is installed (`php artisan install:api`) or explicitly configured in `bootstrap/app.php`, as it is not present by default.
- **Models & Relationships:**
  - Use `User` as the central auth model.
  - Profile data should be in `MentorProfile` or `MenteeProfile`, not on the `User` table directly.
  - Use **UUIDs** or standard auto-incrementing IDs consistently (check migrations).
- **Controller Pattern:**
  - Keep controllers thin. Use Form Requests for validation.
  - Return JSON responses for API endpoints (even if currently in `web.php`).

## Critical Files

- `bootstrap/app.php`: Application bootstrapping and configuration.
- `database/migrations/`: Source of truth for database schema.
- `composer.json`: Dependency and script management.
