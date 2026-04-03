# Basketball Analytics Thesis System

This repository is initialized for a thesis project that recommends the best basketball starting five based on uploaded historical CSV data and opponent context.

## Stack

- Laravel 13
- Breeze + Inertia + React
- Tailwind CSS
- shadcn-ready UI foundation
- SQLite by default for local development
- Python analytics engine invoked from Laravel jobs via subprocess

## Why this architecture

The project is designed around a single-server deployment to keep operations simple while still following a scalable structure:

- Laravel handles authentication, CRUD modules, CSV upload, queue jobs, reports, and the main web app.
- React powers the UI through Inertia so you keep Laravel routing without splitting into a separate frontend deployment.
- Python handles analytics and statistical modeling in its own folder so ML logic stays isolated and easier to evolve.
- SQLite works well for local development, while MySQL can be used later for deployment or larger datasets.

## Phase plan

### Phase 1: 50% target

- User authentication
- School, team, player, and opponent records
- CSV upload and validation
- Historical stat import
- Player performance scoring
- Recommended starting five based on opponent and history
- Win-rate estimate from historical data
- Dashboard for results

### Phase 2: 100% target

- Real-time in-game stat encoding
- Five simultaneous operator views
- Live recalculation of player impact and suggested substitutions
- Event broadcasting / websocket updates
- Stronger statistical or ML model after phase 1 is stable

## Suggested domain modules

- `users`
- `schools`
- `teams`
- `players`
- `opponents`
- `games`
- `game_player_stats`
- `csv_imports`
- `lineup_recommendations`
- `prediction_runs`

## Local setup

### Prerequisites

Install these on your machine first:

- PHP 8.3+
- Composer 2+
- Node.js 20+ and npm
- Python 3.9+

### 1. Clone the project and install dependencies

From the project root:

```bash
composer install
npm install
```

### 2. Configure the Laravel environment

Copy the environment file:

```bash
cp .env.example .env
```

For the simplest local setup, use SQLite in `.env`:

```env
DB_CONNECTION=sqlite
PYTHON_ENGINE_PATH=analytics/engine.py
PYTHON_BIN=python3
```

Make sure the SQLite file exists:

```bash
touch database/database.sqlite
```

Then generate the app key and run migrations:

```bash
php artisan key:generate
php artisan migrate
```

### 3. Prepare the Python analytics engine

The app does not call a separate Python web server during normal local development. Laravel jobs run the engine directly through `analytics/engine.py`.

Create a virtual environment in the project root:

```bash
python3 -m venv .venv
source .venv/bin/activate
pip install -e ./analytics
```

If your system `python3` already works, you can keep:

```env
PYTHON_BIN=python3
```

If you prefer to use a virtual environment interpreter explicitly, point `PYTHON_BIN` to `.venv/bin/python`.

### 4. Start the application locally

Run the full local development stack with:

```bash
composer dev
```

This starts:

- the Laravel development server
- the queue listener
- Laravel logs via Pail
- the Vite frontend dev server

The queue listener matters because the analytics work is dispatched through Laravel jobs.

### 5. Open the app

After `composer dev` starts successfully, open the local URL shown by Laravel in your terminal. In most setups this will be:

```text
http://127.0.0.1:8000
```

### Optional MySQL setup

If you want to use MySQL instead of SQLite, update `.env` like this:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=basketball_analytics
DB_USERNAME=root
DB_PASSWORD=
```

Create the database first, then run:

```bash
php artisan migrate
```

### Optional one-command bootstrap

If your environment is already ready, you can use the built-in setup script:

```bash
composer setup
```

That command installs PHP and Node dependencies, creates `.env` if needed, generates the app key, runs migrations, and builds the frontend assets.

## One-server deployment

Use a single VPS with:

- Nginx
- PHP-FPM
- MySQL or another production-ready database
- Supervisor or systemd for Laravel queue workers
- Python 3 for the analytics engine subprocess

Recommended runtime topology:

- `Nginx -> Laravel public/index.php`
- `Laravel queue jobs -> python3 analytics/engine.py`
- `Laravel queue worker -> heavy CSV processing and prediction jobs`

This keeps operations simple while still letting you separate concerns in code.

## Immediate next build order

1. Create the database schema for teams, players, games, and imported stats.
2. Build CSV upload with validation rules and import logs.
3. Compute core player metrics from historical records.
4. Expand the Python analytics engine for lineup recommendation and win-rate prediction.
5. Surface the results in the dashboard.

## Notes on best practice

- Keep CSV ingestion asynchronous with Laravel jobs.
- Store raw import files and parsed summaries separately.
- Version prediction runs so thesis results are reproducible.
- Treat the Python analytics engine as stateless and deterministic where possible.
- Start with interpretable scoring before jumping into complex AI models.
