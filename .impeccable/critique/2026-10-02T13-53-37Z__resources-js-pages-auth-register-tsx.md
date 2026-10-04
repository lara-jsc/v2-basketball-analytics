---
target: coach signup + join request UI
total_score: 23
p0_count: 0
p1_count: 3
timestamp: 2026-10-02T13-53-37Z
slug: resources-js-pages-auth-register-tsx
---
Method: dual-agent (A: design review · B: detector + browser)

## Design Health Score
| # | Heuristic | Score | Key Issue |
|---|---|---|---|
| 1 | Visibility of System Status | 2 | Pending banner is a quiet one-liner; main coaches get no signal that requests exist; no per-button loading on Approve/Decline |
| 2 | Match System / Real World | 3 | "Claim existing team" is system-speak; "Demo Hawks's main coach" possessive |
| 3 | User Control and Freedom | 1 | One-click irreversible Decline; assistant can't cancel/switch request |
| 4 | Consistency and Standards | 3 | Matches Login; but browser-blue focus ring on choice buttons vs orange input focus; no show-password toggle |
| 5 | Error Prevention | 2 | Zero-teams state leaves submit enabled; no password rules shown upfront |
| 6 | Recognition Rather Than Recall | 3 | Code + name in options, mode-aware helper text |
| 7 | Flexibility and Efficiency | 2 | Native select without search for team pick |
| 8 | Aesthetic and Minimalist Design | 3 | Good progressive disclosure; duplicate TEAM MANAGEMENT eyebrow; permanent empty card |
| 9 | Error Recovery | 2 | Errors not linked (aria-describedby/invalid), no error border, no focus-to-first-error; declined = dead end |
| 10 | Help and Documentation | 2 | Nothing explains what a pending assistant can do |
| **Total** | | **23/40** | **Acceptable** |

## Anti-Patterns Verdict
LLM: mostly clean; inherits Login's glow/glass card styling (consistent, not new slop). Real tell: duplicated TEAM MANAGEMENT eyebrow on stacked cards.
Detector CLI: 0 findings (exit 0). Live detector: /register dark-glow x2-3, gpt-thin-border-wide-shadow x2, nested-cards x1-2 (style inherited from Login; taste-level). /teams/4 and /dashboard hits all trace to pre-existing sections; new card and banner are clean (axe 0 violations scoped). axe on /register: region (no <main> landmark).

## Priority Issues
- [P1] Pending/declined assistant has weak status and no path forward (banner weight, cancel/change request, declined dead end, fake dashboard data). Cmd: onboard / clarify
- [P1] Main coaches can't discover pending requests (no badge/count outside team page). Cmd: onboard
- [P1] Decline irreversible without confirm/undo; 30px buttons below touch target on tablet; no per-button spinner. Cmd: harden / adapt
- [P2] Register form a11y: errors not linked, no error border, choice/toggle as aria-pressed not radiogroup, off-brand focus ring, no <main>, placeholder 3.89:1. Cmd: audit / polish
- [P2] Zero-teams state dead end (submit enabled, wrong helper copy). Cmd: harden

## Persona Red Flags
Jordan: Claim vs Create unclear; team code purpose unexplained; sample charts after pending signup.
Sam: aria-pressed instead of radios; unlinked errors; default blue ring.
Casey: authenticated layout sidebar doesn't collapse at 390px (pre-existing) — banner/card unusable on phone.

## Minor Observations
Team name input misaligned (no icon); ambiguous toLocaleDateString; moveBlocked copy if team null + Save not disabled; CTA white-on-orange 2.3–2.9:1 (inherited); public team list on /register.

## Questions
Should claiming an existing team need admin approval or an invite code? Should pending assistants get a focused waiting screen instead of the full app? Would coach-initiated invites beat join requests?
