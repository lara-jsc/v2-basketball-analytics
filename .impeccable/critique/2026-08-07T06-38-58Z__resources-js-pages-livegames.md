---
target: Live Games light mode
total_score: 21
p0_count: 2
p1_count: 2
timestamp: 2026-08-07T06-38-58Z
slug: resources-js-pages-livegames
---
Method: dual-agent (A: 02bc8d1d-b3c6-4337-a304-05dbb5320f57 · B: 034d9199-1afd-4501-9322-eec947124114)

#### Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 1 | Status badges, clock status, flash use pale cyan/amber in light |
| 2 | Match System / Real World | 3 | Courtside vocabulary fine |
| 3 | User Control and Freedom | 3 | Sub/secondary controls hard to see in light |
| 4 | Consistency and Standards | 1 | Tokens say navy text; Live Games hardcodes dark-only palette |
| 5 | Error Prevention | 3 | Confirm modals present; confirm buttons pale in light |
| 6 | Recognition Rather Than Recall | 2 | Jerseys/PTS/badges illegible in light |
| 7 | Flexibility and Efficiency | 2 | Unreadable Sub/scoreboard slows courtside use |
| 8 | Aesthetic and Minimalist Design | 2 | Glow accents become noise in light |
| 9 | Error Recovery | 2 | Flash/alerts fail contrast in light |
| 10 | Help and Documentation | 2 | Inline banners use pale cyan |
| **Total** | | **21/40** | **Acceptable → Poor for light mode a11y** |

#### Anti-Patterns Verdict
**LLM**: Dark-mode accent leakage — cyan/amber glow hardcoded without light dual. Not purple SaaS slop.
**Deterministic scan**: 0 detector findings; grep found ~52 cyan/amber text hits (pre-fix).
**Visual overlays**: skipped (no browser MCP).

#### Priority Issues
- [P0] Pale cyan/amber text system-wide in Live Games
- [P0] Scoreboard scores + clock status illegible
- [P1] StatusBadge + flash + live pill
- [P1] Sub + EventPad amber actions
- [P2] Jersey numbers as pale gold
- [P3] Alert severity chips

#### Fix applied (post-critique)
Dual-mode `live-text-*` / `live-badge-*` utilities in app.css; Live Games pages + live-game features updated.
