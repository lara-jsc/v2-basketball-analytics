# HoopSense+
An Intelligent Basketball Game Decision Support System Using Plus-Minus Analytics for Winning Probability Optimization.

---

## Project Overview
A pre-game preparation tool for basketball coaching staff that:
- Accepts team roster data via CSV upload (per team)
- Allows manual creation, editing, and deletion of player records post-upload
- Compares two teams side-by-side with AI-generated win probability and win rate insights
- Recommends effective lineups against a selected opponent using plus-minus analytics
- Supports head-to-head player matchup comparison with win probability prediction per player

**Primary Device:** Tablet (768px–1024px) — tablet-first responsive UI is non-negotiable.

---

## Deadline & Scope

**Deadline: April 16**

### In Scope (Build Now)
- **Phase 1:** Foundation — migrations, CSV upload/download templates, file storage
- **Phase 2:** Player & Team Management — CRUD, active/inactive toggle, profile pictures
- **Phase 3:** Pre-Game Tools:
  - Team vs Team Statistics (AI Insights, Win Probability, Win Rate)
  - Lineup Recommendation (modal trigger from Team Comparison page)
  - Player vs Player Comparison (matchup prediction)
- **Phase 5:** Polish — dark/light mode, tablet UI, loading/error/empty states, edge cases

### Out of Scope — Parked (Post-April 16)
> **Do NOT build, scaffold, or reference anything below until after April 16.**

- **Phase 4 — Real-Time Possession-by-Possession Game Stats**
  - Live game session recording
  - Debounced real-time stat input
  - Real-time lineup suggestion panel

- **Future: User Authentication & Team Assignment**
  - Login-bound team management
  - Dashboard: previous & upcoming games
  - AI insights from game history
  - Quarter-by-quarter statistics

---

## Tech Stack
- **Backend:** Laravel (PHP) + Python (analytics/ML engine)
- **Frontend:** React + TypeScript via Inertia.js (single deployment, no separate API)
- **UI Library:** shadcn/ui
- **Database:** MySQL
- **Bridge:** Inertia.js — all data flows through Inertia props, never raw JSON API endpoints

---

## Commands
```bash
# Install dependencies
composer install
npm install

# Dev server (runs everything)
composer run dev

# Database
php artisan migrate
php artisan migrate:fresh --seed

# Build frontend
npm run build

# Python analytics engine
pip install -r requirements.txt
python analytics/engine.py

# Tests
php artisan test
npm run test
```

---

## Architecture & Separation of Concerns

### Laravel (Backend)
```
app/
├── Http/
│   ├── Controllers/        # Thin — delegate to Services, return Inertia::render() only
│   ├── Requests/           # All validation via FormRequest classes
│   └── Middleware/
├── Services/               # All business logic (LineupService, WinProbabilityService, etc.)
├── Repositories/           # All DB queries — abstracted from Services
├── Models/                 # Eloquent models only — zero business logic
├── Jobs/                   # Queued jobs: CSV processing, Python analytics calls
└── Actions/                # Single-responsibility action classes
```

### React + TypeScript (Frontend via Inertia)
```
resources/js/
├── Pages/                  # Inertia page components (map 1:1 to Laravel routes)
├── Components/
│   ├── ui/                 # shadcn/ui base components — DO NOT MODIFY
│   ├── shared/             # App-wide reusable components
│   └── features/
│       ├── csv/            # Upload, template download, import status
│       ├── teams/          # Team management, selector
│       ├── players/        # Player CRUD, profile picture, active toggle
│       ├── comparison/     # Team vs Team stats, player vs player
│       ├── lineup/         # Lineup recommendation modal
│       └── analytics/      # Win probability charts, win rate display
├── hooks/                  # Custom hooks (useCamelCase.ts)
├── lib/                    # Utilities, helpers, type guards
├── types/                  # Global TypeScript interfaces (PascalCase.types.ts)
└── stores/                 # Zustand or Context for UI state
```

### Python (Analytics Engine)
```
analytics/
├── engine.py               # Entry point called by Laravel Jobs only
├── plus_minus/             # Plus-minus calculation logic
├── lineup_optimizer/       # Lineup ranking algorithms
├── win_probability/        # Win probability & win rate models
└── utils/                  # Data parsing, stat normalization
```

> Python never accesses the DB directly. It receives serialized data from Laravel Jobs and returns JSON-serializable results back to Laravel.

---

## Database Schema

### `teams`
| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `code` | varchar(10) | Unique team code (e.g. "LAL") |
| `name` | varchar(100) | Team full name |
| `logo_path` | varchar(255) | Stored in Laravel storage, served via signed URL |
| `is_active` | boolean | Default: true |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### `players`
| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `team_id` | bigint FK → teams.id | Currently active team |
| `first_name` | varchar(100) | |
| `last_name` | varchar(100) | |
| `jersey_number` | tinyint unsigned | |
| `role` | varchar(50) | e.g. "Point Guard" — maps from `pc` field in CSV |
| `height_feet` | decimal(4,2) | |
| `weight_kg` | decimal(5,2) | |
| `profile_picture_path` | varchar(255) | Stored in Laravel storage, served via signed URL |
| `is_active` | boolean | Default: true; gates lineup & comparison eligibility |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### `player_stats` (History Per Game)
| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `player_id` | bigint FK → players.id | |
| `pc` | varchar(50) | Position on Court |
| `sd` | varchar(50) | Spatial Data |
| `three_p_pct` | decimal(5,2) | 3-Point FG Percentage |
| `three_pt` | varchar(20) | Made-Attempted (e.g. "3-7") |
| `ast` | decimal(5,2) | Assists Per Game |
| `ast_to` | decimal(5,2) | Assist To Turnover Ratio |
| `blk` | decimal(5,2) | Blocks Per Game |
| `dd2` | tinyint | Double Double |
| `dq` | tinyint | Disqualifications |
| `dr` | decimal(5,2) | Defensive Rebounds Per Game |
| `eject` | tinyint | Ejections |
| `fg` | varchar(20) | Field Goals Made-Attempted |
| `fg_pct` | decimal(5,2) | Field Goal Percentage |
| `flag` | tinyint | Flagrant Fouls |
| `ft` | varchar(20) | Free Throws Made-Attempted |
| `ft_pct` | decimal(5,2) | Free Throw Percentage |
| `gp` | tinyint | Games Played |
| `gs` | tinyint | Games Started |
| `min` | decimal(5,2) | Minutes Per Game |
| `offensive_rebounds` | decimal(5,2) | Maps to `OR` in CSV/display — `or` is a MySQL reserved word |
| `pf` | decimal(5,2) | Fouls Per Game |
| `pts` | decimal(5,2) | Points Per Game |
| `reb` | decimal(5,2) | Rebounds Per Game |
| `sc_eff` | decimal(5,2) | Scoring Efficiency |
| `sh_eff` | decimal(5,2) | Shooting Efficiency |
| `stl` | decimal(5,2) | Steals Per Game |
| `stl_to` | decimal(5,2) | Steal To Turnover Ratio |
| `td3` | tinyint | Triple Double |
| `tech` | tinyint | Technical Fouls |
| `to_per_game` | decimal(5,2) | Turnovers Per Game — `to` is also a MySQL reserved word |
| `plus_minus` | decimal(5,2) nullable | Computed by Python engine on CSV import; stored for fast retrieval. Signed value (e.g. `+7.4`, `−1.8`). Null until Python job completes. |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### `csv_imports`
| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `team_id` | bigint FK → teams.id | |
| `filename` | varchar(255) | Original uploaded filename |
| `status` | enum('pending','processing','completed','failed') | |
| `rows_imported` | int | Count of successfully imported rows |
| `error_log` | text nullable | Validation/import errors |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

> **Reserved word reminder:** Always use `offensive_rebounds` in DB (maps to `OR` in CSV display) and `to_per_game` in DB (maps to `TO` in CSV display).

---

## CSV Template (Upload & Download)

The downloadable template header row must exactly match (case-sensitive):

```
first_name,last_name,jersey_number,role,height_feet,weight_kg,is_active,pc,sd,3P%,3PT,AST,AST/TO,BLK,DD2,DQ,DR,EJECT,FG,FG%,FLAG,FT,FT%,GP,GS,MIN,OR,PF,PTS,REB,SC-EFF,SH-EFF,STL,STL/TO,TD3,TECH,TO
```

- Upload validation must enforce exact column match before processing begins
- Mismatched columns must return a clear error — do not attempt partial imports
- Profile pictures and team logo are uploaded separately (not via CSV)

---

## Primary Features

### Feature 1 — Data Build-Up (CSV Upload + Player CRUD)

**Step 1:** User selects a team (existing or creates one), then uploads a CSV file containing that team's player roster and stats.

**Step 2:** After upload processes (via queued Job), display the imported data in a table — all players with their stats visible and editable.

**Step 3:** User can perform full CRUD on individual player records:
- **Create:** Add a player manually (form, same fields as CSV)
- **Read:** View full player stat row in the table
- **Update:** Inline edit or a modal/drawer form
- **Delete:** Soft approach — toggle `is_active` before hard delete; confirm destructive action
- **Profile picture:** Uploadable per player, stored in Laravel storage

### Feature 2 — Team vs Team Statistics (AI Insights)

**Step 1:** User selects two teams from available uploaded team data.

**Step 2:** Two tabs/pages appear:

#### Tab 1 — Team Statistics Comparison
- Side-by-side aggregate stats for both teams
- **Win Probability** (Python-computed) — displayed as percentage per team
- **Win Rate** — based on historical stat trends from uploaded data
- **Team Plus-Minus** — displayed as a signed value per team (e.g. `+5.2` vs `−1.8`). Computed as the minutes-weighted average (`min`) of all `is_active = true` players' individual `plus_minus` scores. A higher value means the team collectively outperforms a baseline opponent per game. Read directly from stored `plus_minus` values in `player_stats` — no re-computation at render time.
- **"Suggest Lineup" button** — opens a modal showing the recommended starting 5 (or optimal lineup) for the home team against the selected opponent, ranked by plus-minus score
  - Lineup data is Python-computed (pass both teams' stats via Laravel Job)
  - Cache result per team matchup; invalidate on new CSV upload
  - Only `is_active = true` players are eligible

#### Tab 2 — Player vs Player Matchup
- **Dropdown 1:** Select from Team A players (only `is_active = true`)
- **Dropdown 2:** Select from Team B players (only `is_active = true`)
- Display side-by-side stat comparison across all tracked fields
- Include **Plus-Minus** as a dedicated comparison row in the table — read from stored `plus_minus` in `player_stats`. Display as a signed value (e.g. `+7.4` vs `+2.1`). Highlight the higher value using accent color `#F9A01B` like all other stat rows.
- Highlight the statistically stronger value per row using accent color `#F9A01B`
- Show a matchup win probability prediction (Python-computed) — which player has the statistical edge
- Layout: comparison card/table optimized for tablet view

---

## Analytics Engine Contracts

### Lineup Recommendation Input (Laravel → Python)
```json
{
  "home_team_players": [
    { "player_id": 1, "name": "John Doe", "pts": 22.4, "ast": 6.1, ... }
  ],
  "opponent_team_players": [
    { "player_id": 42, "name": "Jane Smith", "pts": 18.2, "reb": 9.0, ... }
  ]
}
```

### Lineup Recommendation Output (Python → Laravel)
```json
{
  "recommended_lineup": [
    { "player_id": 1, "name": "John Doe", "plus_minus_score": 12.4 },
    ...
  ],
  "confidence": 0.78
}
```

### Win Probability Input (Laravel → Python)
```json
{
  "team_a_stats": { "avg_pts": 98.4, "avg_reb": 44.2, ... },
  "team_b_stats": { "avg_pts": 94.1, "avg_reb": 41.7, ... }
}
```

### Win Probability Output (Python → Laravel)
```json
{
  "team_a_win_probability": 0.62,
  "team_b_win_probability": 0.38,
  "team_a_win_rate": 0.67,
  "team_b_win_rate": 0.54
}
```

### Player Matchup Output (Python → Laravel)
```json
{
  "player_a_edge_score": 0.58,
  "player_b_edge_score": 0.42,
  "stronger_stats_a": ["pts", "ast", "fg_pct"],
  "stronger_stats_b": ["reb", "blk", "dr"]
}
```

### Box Plus-Minus (BPM) Computation — Per Player Input (Laravel → Python)
```json
{
  "player_id": 1,
  "stats": {
    "pts": 22.4,
    "ast": 6.1,
    "reb": 5.2,
    "fg_pct": 0.51,
    "three_p_pct": 0.38,
    "blk": 0.8,
    "stl": 1.4,
    "to_per_game": 3.1,
    "min": 35.2
  }
}
```

### Box Plus-Minus (BPM) Computation — Per Player Output (Python → Laravel)
```json
{
  "player_id": 1,
  "plus_minus": 7.4
}
```

> **When to compute BPM:** Compute and persist `plus_minus` for each player row immediately after the CSV import Job saves stats to `player_stats`. Laravel dispatches a `ComputePlayerPlusMinus` Job per player (or in batch). Python returns the score; Laravel writes it to `player_stats.plus_minus`. All downstream features (Team Comparison, Player Comparison, Lineup Recommendation) read the stored value — never re-compute on demand.

> All Python functions must use type hints. All return values must be JSON-serializable dicts.

---

## Code Style & Best Practices

### General
- Separation of concerns is non-negotiable
- Prefer explicit over clever — write for the next developer
- Every public method/function: TypeScript return type or PHP docblock
- No magic numbers — use named constants or enums

### PHP / Laravel
- `FormRequest` for all validation — never validate in controllers
- `Service` classes for all business logic
- `Repository` pattern for all DB queries
- Controllers return only `Inertia::render()` or redirects
- CSV processing and Python engine calls must go through Laravel Jobs (async)
- Use DB transactions for multi-step writes
- PSR-12 coding standard

### TypeScript / React
- Strict TypeScript — zero `any` types
- Functional components only
- Custom hooks for all reusable stateful logic
- Co-locate feature types with their feature folder
- Use `inertia-react` `useForm` for all form submissions
- Always handle: loading state, error state, empty state

### Python
- Type hints on all functions
- Return JSON-serializable dicts only
- Never import DB drivers or connect to DB directly
- Receive data from Laravel, return results to Laravel — nothing else

---

## UI / Frontend Standards

### Responsive Breakpoints (Tablet-First)
| Breakpoint | Range | Priority |
|------------|-------|----------|
| Tablet | 768px–1024px | **Primary — design here first** |
| Desktop | 1024px+ | Secondary |
| Mobile | <768px | Tertiary — graceful degradation only |

### Color Palette
| Token | Hex | Usage |
|-------|-----|-------|
| `primary` | `#98002E` | Primary actions, active states, key highlights |
| `accent` | `#F9A01B` | Stronger stat highlights, secondary CTAs, callouts |
| `base` | `#000000` | Background (dark mode), text (light mode) |

### Dark / Light Mode
- Use CSS variables via shadcn/ui theming system
- All components must respect the active theme
- Default: system preference
- Allow manual toggle — persist choice in `localStorage`

### shadcn/ui
- Use shadcn components as the base layer
- Extend via `className` and the feature component layer
- **Never modify files inside `components/ui/`**
- Custom variants belong in `components/features/`

---

## Gotchas & Warnings

| # | Warning |
|---|---------|
| 1 | **Inertia.js is the only bridge.** No REST API. All data flows via Inertia props. |
| 2 | **Never call Python synchronously.** Always via Laravel Jobs. |
| 3 | **CSV headers are case-sensitive.** Reject uploads that don't match the template exactly. |
| 4 | **`is_active = false` players are excluded from all lineup recommendations and comparison dropdowns.** |
| 5 | **`or` is a MySQL reserved word.** Use `offensive_rebounds` in DB; map to `OR` in CSV and display layer. |
| 6 | **`to` is a MySQL reserved word.** Use `to_per_game` in DB; map to `TO` in CSV and display layer. |
| 7 | **Profile pictures and team logos** are stored in Laravel storage, served via signed URLs — never exposed directly. |
| 8 | **Cache lineup recommendations** per team matchup key. Invalidate on new CSV upload for either team. |
| 9 | **Win probability and lineup results are derived from uploaded historical data only** — not live or external data. |
| 10 | **`plus_minus` is nullable on insert.** It is populated after the `ComputePlayerPlusMinus` Job completes. UI must handle null gracefully — display `—` instead of `0` or blank until computed. |
| 11 | **Team Plus-Minus is computed at read time from stored `plus_minus` values** — it is the minutes-weighted average of active players. Do not store a team-level plus-minus column; derive it in the Service layer. |

---

## File Naming Conventions
| Layer | Convention |
|-------|------------|
| Laravel classes | `PascalCase` |
| DB columns & routes | `snake_case` |
| React components | `PascalCase.tsx` |
| Custom hooks | `useCamelCase.ts` |
| TypeScript types | `PascalCase.types.ts` or `types/index.ts` |
| Python modules | `snake_case.py` |

---

## Environment Variables (`.env`)
```env
APP_NAME="HoopSense+"
DB_CONNECTION=mysql

PYTHON_ENGINE_PATH=analytics/engine.py
PYTHON_BIN=python3

QUEUE_CONNECTION=database

FILESYSTEM_DISK=local
```

---

## Future Features (Reference Only — Do Not Implement)

These are documented for architectural awareness — do not scaffold, route, or stub anything for these until explicitly scoped.

1. **Real-Time Possession-by-Possession Game Stats**
   - Live game session recording per possession
   - Debounced stat input with real-time lineup suggestions

2. **User Authentication & Team Assignment**
   - Login-bound team ownership
   - Personal dashboard: previous games, upcoming games
   - AI insights from team game history
   - Quarter-by-quarter breakdown statistics

> When implementing Phase 1–3, do not create DB tables, routes, controllers, or components that anticipate these future features. Build only what is needed now.
