# Interface Audit

Date: 2026-04-08

## Fixed Now

- Property details command center
  - Reduced sticky-header sprawl so the executive summary no longer blankets the page.
  - Moved heavier actions and signals out of the sticky layer.

- Audit drawer
  - Restored dark-surface readability for before/after JSON panels.
  - Normalized audit summary text from structured diffs instead of stale stored copy.

- City Pipeline
  - Reframed from a generic future-project gallery into a decision-first city need board.
  - Added investor-gap entries so admins can show what San Fernando still needs.
  - Added supply-signal guidance to reduce duplicate-build decisions.

- Admin Showcase Studio
  - Added structured fields for city-pipeline investor guidance:
    - entry type
    - tracked supply signal
    - investor thesis
    - ideal operator
    - duplicate-build caution

## Current Weak Points

- Landing and role dashboards still mix editorial storytelling with dense operational content.
- Some admin surfaces still depend on long forms instead of stronger progressive disclosure.
- The `More` navigation bucket still hides high-value surfaces that may deserve clearer promotion.
- Several pages still need a tighter mobile-specific verification pass after the latest refactors.

## Next Recommended Passes

1. Landing + dashboards
   - Tighten above-the-fold hierarchy.
   - Make role-specific next actions more explicit.

2. Admin workspace
   - Convert large forms into grouped sections with inline help and live preview.

3. Public browsing surfaces
   - Unify card hierarchy between ranking, explorer, voting, and showcase pages.

4. Mobile polish
   - Validate nav, sticky controls, and card grids across narrower breakpoints.
