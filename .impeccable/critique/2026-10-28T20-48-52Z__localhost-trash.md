---
target: "http://localhost/trash table is too cluttered"
total_score: 19
max_score: 36
na_heuristics: 9
p0_count: 1
p1_count: 2
target_identity: "url:http://localhost/trash"
timestamp: 2026-10-28T20-48-52Z
slug: localhost-trash
---
# Critique: Trash page (19/36, Acceptable)
Heuristics: 1:2 2:2 3:2 4:2 5:3 6:2 7:1 8:2 9:n/a 10:3

## Priority issues
- [P0] Title collapses to 0px on mobile (fixed column widths total 544px). Stack rows below md; hide .th.
- [P1] Too many equal-weight columns. Reduce to title + subline (Space · Type), one urgency value (days left, amber at <=3), Restore only. Relative dates.
- [P1] Delete forever on every row. Move to overflow menu or hover/focus; add Empty trash with count confirm.
- [P2] No bulk actions/sort; Restore navigates away with no toast.
- [P2] a11y: aria-hidden header, unlabeled cells, identical Delete forever buttons need aria-label with title.
