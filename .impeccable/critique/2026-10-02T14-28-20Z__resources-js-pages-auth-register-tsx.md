---
target: coach signup + join request UI
total_score: 26
p0_count: 0
p1_count: 1
timestamp: 2026-10-02T14-28-20Z
slug: resources-js-pages-auth-register-tsx
---
Method: dual-agent (A: design review · B: detector + browser) — follow-up after approval-flow fixes

## Design Health Score
| # | Heuristic | Score | Key Issue |
|---|---|---|---|
| 1 | Visibility of System Status | 3 | Pending state only on Dashboard, not on the requested team's page |
| 2 | Match System / Real World | 3 | "Keep" ambiguous (fixed post-run → "Keep request") |
| 3 | User Control and Freedom | 3 | Cancel request + two-step decline; focus after Keep went to Approve (fixed post-run) |
| 4 | Consistency and Standards | 2 | Register keeps its own hex palette; three loud colours in confirm row (fixed post-run: outline destructive) |
| 5 | Error Prevention | 3 | Decline confirm; Send disabled until team picked |
| 6 | Recognition Rather Than Recall | 3 | Callout deep-links to #join-requests |
| 7 | Flexibility and Efficiency | 2 | No bulk approve |
| 8 | Aesthetic and Minimalist Design | 3 | Team-less assistant dashboard full of sample widgets |
| 9 | Error Recovery | 2 | Register field errors still unlinked |
| 10 | Help and Documentation | 2 | Nothing productive to do while waiting |
| **Total** | | **26/40** | **Acceptable** (up from 23) |

## Anti-Patterns Verdict
CLI detector: 0 findings across 5 files. Live: Register dark-glow x3 / thin-border-wide-shadow x2 / nested-cards x1 (inherited Login style). New banner, callout, nav badge: 0 axe violations, contrast passes, 40px targets. Confirm Decline was 3.78:1 (fixed post-run → red-700 outline, focus returns to trigger, Escape closes, badge no longer read twice).

## Priority Issues
- [P1] Register form a11y unfixed: unlinked errors, aria-pressed instead of radios, no focus ring on choice cards, placeholder 3.89:1, no <main>. Cmd: audit/polish
- [P2] Team-less assistant sees management affordances (New Team, Edit Team, Add Player, CSV import) and sample-data dashboard. Cmd: harden/onboard
- [P3] Admins get no way to bulk approve; callout chips wrap at 390px.

## Persona Red Flags
Jordan: nothing to do while pending; "New Team" invites duplicate team; approval arrives silently.
Sam: Register radio semantics and error association.
Casey: sidebar doesn't collapse on phones (pre-existing).

## Questions
Should team-less assistants see management screens at all? How should an assistant learn they were approved (notification/email)?
