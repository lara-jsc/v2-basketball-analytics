# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Primary users are basketball coaching staff using HoopSense+ before games to prepare lineups, compare teams, and understand player matchups.

Inferred from `docs/superpowers/plans/2026-08-02-live-game-module-mvp.md`: the live-game module also serves coaches or staff recording events courtside during active games, including multi-operator workflows where several authenticated users may record or monitor the same game.

Secondary users include analysts or thesis evaluators reviewing historical stats, player impact, and model outputs after data has been imported.

## Product Purpose

HoopSense+ helps basketball staff turn roster and game-history data into practical coaching decisions: win probability, recommended starting five, player matchup context, and, in the planned live-game module, synchronized in-game event recording with live stats, rule-based alerts, and post-game stat finalization.

Success means a coach can move from team setup and player-history import to credible comparison outputs, then record a live game without losing the authoritative event stream or stat history.

## Positioning

HoopSense+ combines a Laravel/Inertia coaching dashboard, deterministic player-stat rebuilds, Python-backed analytics, and planned server-authoritative live-game event recording in one single-server thesis system. Its differentiator is not generic sports dashboards, but the direct bridge from historical player records to lineup recommendations and then back from live game events into the same player history pipeline.

## Operating Context

Coaches create teams, add or import players, record player game histories, and run team comparisons before games. Current documented workflow:

Create Team -> Add Players -> Add Player Game Histories -> Auto-aggregate Stats -> Compute BPM -> Compare Teams -> Review Win Probability, Lineup Recommendation, and Player Matchups.

Inferred from the live-game MVP plan: during games, staff need a fast, synchronized console for scoreboard state, clock state, active lineup, event entry, substitutions, timeline corrections, and deterministic DSS alerts. After the game, finalized live projections should create player-history rows and trigger existing stat rebuild flows.

## Capabilities and Constraints

Existing capabilities:

- Authenticated Laravel/Inertia web app.
- Team and player management.
- CSV roster import.
- Player game-history entry and import.
- Derived player stat rebuilds from historical rows.
- Team comparison, win probability, recommended lineup, and player matchup views.
- Python analytics engine invoked by Laravel jobs.

Planned live-game constraints from the MVP plan:

- MVP auth is minimal: any verified user can create, join, and record live games.
- Game lifecycle is `setup`, `live`, `finished`.
- Basketball format is four quarters with configurable period length per game.
- Clock is server-authoritative and synchronized.
- Own team is tracked at player level; opponent is tracked at aggregate level.
- Events are append-only; corrections create linked void or reversal events.
- Broadcasts send full server snapshots rather than client-applied deltas.
- Live DSS is deterministic rule-based alerts only.
- Finished live games finalize into existing player-history and stat-rebuild flows.
- Production database target is MySQL, while local development may use SQLite.

Explicitly undecided:

- Detailed role permissions beyond verified-user MVP access.
- Offline sync.
- ML recommendations during live gameplay.
- Opponent player-level live tracking.

## Brand Commitments

The product name is HoopSense+.

Existing interface assets and code use basketball imagery, the HoopSense+ name, and a dashboard identity centered on coaching analytics. Future work should preserve factual product language and avoid inventing unsupported claims, customers, benchmarks, or testimonials.

## Evidence on Hand

- `README.md`: project purpose, stack, phase plan, local setup, and one-server deployment notes.
- `SYSTEM_GUIDE.md`: plain-English product workflow and stat calculation explanations.
- `docs/superpowers/plans/2026-08-02-live-game-module-mvp.md`: live-game MVP requirements and constraints.
- `resources/js/Layouts/AuthenticatedLayout.tsx`: current authenticated app shell and navigation.
- `resources/js/Pages/Dashboard.tsx`: current dashboard surface and product naming.
- Existing Laravel routes and models for teams, players, histories, comparisons, CSV import, jobs, and analytics services.

No confirmed testimonials, external customer logos, production deployment claims, or formal accessibility standard were found.

## Product Principles

- Keep coaching workflows fast and operational: the UI should help users act, compare, record, and recover quickly.
- Preserve an authoritative data trail: live events are append-only, historical stats are derived through rebuild flows, and corrections should remain auditable.
- Favor deterministic, explainable decisions for thesis credibility, especially for stat aggregation, lineup recommendations, and DSS alerts.
- Treat live-game state as server-owned so multiple operators see the same scoreboard, clock, lineup, and event timeline.
- Do not fabricate proof: use only real roster data, game histories, computed stats, and documented system behavior.

## Accessibility & Inclusion

No product-specific accessibility standard has been confirmed. Because the app is a web-based operational coaching tool, future frontend work should preserve keyboard access, visible focus states, sufficient contrast, readable stat tables, and responsive layouts for courtside laptop or tablet use.
