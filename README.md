# Basketball Analytics Thesis System

This repository is initialized for a thesis project that recommends the best basketball starting five based on uploaded historical CSV data and opponent context.

## Stack

- Laravel 13
- Breeze + Inertia + React
- Tailwind CSS
- shadcn-ready UI foundation
- MySQL
- Python analytics service on the same server

## Why this architecture

The project is designed around a single-server deployment to keep operations simple while still following a scalable structure:

- Laravel handles authentication, CRUD modules, CSV upload, queue jobs, reports, and the main web app.
- React powers the UI through Inertia so you keep Laravel routing without splitting into a separate frontend deployment.
- Python handles analytics and statistical modeling in its own service folder so ML logic stays isolated and easier to evolve.
- MySQL remains the system of record for teams, players, games, imports, and predictions.

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

### Laravel app

```bash
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run dev
php artisan serve
```

### MySQL

Update `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=basketball_analytics
DB_USERNAME=root
DB_PASSWORD=
```

### Python analytics service

```bash
cd python-analytics
python3 -m venv .venv
source .venv/bin/activate
pip install -e .
uvicorn app.main:app --reload --port 8001
```

## One-server deployment

Use a single VPS with:

- Nginx
- PHP-FPM
- MySQL
- Supervisor or systemd for Laravel queue workers
- Supervisor or systemd for the Python analytics API

Recommended runtime topology:

- `Nginx -> Laravel public/index.php`
- `Laravel -> local Python service at http://127.0.0.1:8001`
- `Laravel queue worker -> heavy CSV processing and prediction jobs`

This keeps operations simple while still letting you separate concerns in code.

## Immediate next build order

1. Create the database schema for teams, players, games, and imported stats.
2. Build CSV upload with validation rules and import logs.
3. Compute core player metrics from historical records.
4. Add Python endpoints for lineup recommendation and win-rate prediction.
5. Surface the results in the dashboard.

## Notes on best practice

- Keep CSV ingestion asynchronous with Laravel jobs.
- Store raw import files and parsed summaries separately.
- Version prediction runs so thesis results are reproducible.
- Treat the Python service as stateless and deterministic where possible.
- Start with interpretable scoring before jumping into complex AI models.
