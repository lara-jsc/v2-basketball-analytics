# HoopSense+ UI Revamp — Plan & Assets Checklist

## Assets You Need to Source

### Required (makes a visible difference)
| Asset | Description | Where to find | Save to |
|-------|-------------|---------------|---------|
| `court-bg.jpg` | Dark basketball arena/court photo — overhead or sideline angle, crowd blurred, dramatic lighting | [Unsplash](https://unsplash.com) → search "basketball arena night" or "basketball court dark" | `public/images/court-bg.jpg` |

### Optional (CSS fallback exists if skipped)
| Asset | Description | Where to find | Save to |
|-------|-------------|---------------|---------|
| `court-texture.jpg` | Close-up hardwood floor texture — used as a subtle overlay on the CSV upload header band | [Unsplash](https://unsplash.com) → search "basketball hardwood floor" | `public/images/court-texture.jpg` |

---

## Assets Generated in Code (no file needed)

| Visual Element | Method |
|----------------|--------|
| Basketball logo icon (sidebar) | Inline SVG — orange circle + curved court lines |
| Player photo placeholder | CSS circle + inline SVG silhouette |
| Team logo placeholder | Team initials in crimson rounded box (already exists) |
| Glow effects / arena atmosphere | CSS `box-shadow` + `radial-gradient` |
| Win probability arc/donut | CSS `conic-gradient` |
| Bar charts (Player Impact, Top Players) | Pure CSS bars (Tailwind width utilities) |
| Line chart (Win Probability trend) | `recharts` LineChart component |
| Radar/spider chart (Player Matchup) | `recharts` RadarChart component |

---

## npm Package to Install Before Starting
```bash
npm install recharts
```
Needed for: radar chart (Player vs Player), line chart (Win Probability), bar chart (Dashboard).

---

## Pages & Components Being Revamped

| Step | File(s) | Reference Image | Change |
|------|---------|-----------------|--------|
| 1 | `resources/js/Layouts/AuthenticatedLayout.tsx` | Image 1 | Basketball logo, amber glow on active nav, Logout link |
| 2 | `resources/js/Pages/Dashboard.tsx` | Image 1 | Full redesign — summary cards, bar charts, lineup strip |
| 3 | `resources/js/Pages/Teams/Index.tsx` + `Components/features/teams/TeamCard.tsx` | — | Richer team cards with jersey badge, gradient band |
| 4 | `resources/js/Pages/Teams/Show.tsx` + `CsvUploadForm.tsx` + `PlayersTable.tsx` | Image 2 | Arena header, amber Import button, amber +/- column |
| 5 | `resources/js/Pages/Comparison/Index.tsx` | — | Dark arena selector with VS badge |
| 6 | `resources/js/Pages/Comparison/Show.tsx` + `WinProbabilityBar.tsx` + `TeamStatsPanel.tsx` | Image 4 | Large probability cards, line chart, quarter table, insights |
| 7 | `Components/features/comparison/PlayerMatchupTable.tsx` | Image 5 | Player header cards, radar chart, amber stat rows |
| 8 | `Components/features/lineup/LineupModal.tsx` | Image 3 | 5 side-by-side player cards, Key Factors panel, amber CTA |
| 9 | `resources/js/Pages/Players/Histories/Index.tsx` | — | Arena header, styled filter toolbar |
| 10 | `Teams/Create.tsx`, `Teams/Edit.tsx`, `Histories/Create.tsx`, `Histories/Edit.tsx` | — | Consistent dark card + arena header gradient |

---

## What Does NOT Change
- All PHP backend files (controllers, services, jobs, models)
- All Inertia data flows and props
- All routes
- `resources/js/Components/ui/**` — shadcn base components, never modified
- `resources/css/app.css` — color tokens already correct
- `tailwind.config.js` — fonts and tokens already correct

---

## Design Tokens (already in app.css — for reference)
| Token | Hex | Usage |
|-------|-----|-------|
| `primary` | `#98002E` | Active nav, primary CTAs, key highlights |
| `accent` | `#F9A01B` | Stat highlights, secondary CTAs, plus-minus values, glow |
| `bg-base` | `#080C18` | Page background (dark mode) |
| `bg-surface` | `#0D1525` | Card/panel backgrounds (dark mode) |
| `bg-elevated` | `#162035` | Modals, dropdowns (dark mode) |
| `text-primary` | `#F0F4FF` | Primary text |
| `text-secondary` | `#7A93B8` | Labels, subtitles, muted info |

---

## Reference Images (in `.claude/ui sample/`)
| File | Used for |
|------|----------|
| `image 1 - sidebar and dashboard.png` | Sidebar layout + Dashboard page |
| `Image 2 - manage team,players, upload csv file.png` | Teams Show page + CSV upload |
| `Image 3 - reccomendation line up.png` | LineupModal redesign |
| `Image 4 - Win rate.png` | Comparison Show — Team Stats tab |
| `Image 5 - comparing player vs player.png` | Comparison Show — Player Matchup tab |
