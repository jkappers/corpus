---
target: sidebar IA and create flow
total_score: 23
max_score: 40
na_heuristics:
p0_count: 1
p1_count: 2
target_identity: 'file:/Users/jkappers/Projects/corpus/resources/js/layouts/app-layout.tsx'
target_fingerprint: 'sha256:54c114900fd1da8eb6537bac8eac48c2937ee66ac261099691396dde4823ee3e'
target_path: /Users/jkappers/Projects/corpus/resources/js/layouts/app-layout.tsx
timestamp: 2026-10-28T16-00-57Z
slug: resources-js-layouts-app-layout-tsx
---

Method: dual-agent (A: design review · B: detector + browser)

Focus: sidebar IA (Pages / Boards / Files vs Spaces) and the create flow.

## Design Health Score

| #         | Heuristic                       | Score     | Key Issue                                                                                       |
| --------- | ------------------------------- | --------- | ----------------------------------------------------------------------------------------------- |
| 1         | Visibility of System Status     | 2         | Board header shows no space; page shows it only as an unlabeled select                          |
| 2         | Match System / Real World       | 2         | One space: "Private" (sidebar), "Test User's space" (rows), "Private (all users)" (admin group) |
| 3         | User Control and Freedom        | 3         | Undo only on N shortcut; click-create has none                                                  |
| 4         | Consistency and Standards       | 2         | Two "New page" buttons on /pages; boards can't move, pages can; Files container width differs   |
| 5         | Error Prevention                | 2         | Create from global views silently lands in Private                                              |
| 6         | Recognition Rather Than Recall  | 2         | User must remember global lists span all spaces and new items default to Private                |
| 7         | Flexibility and Efficiency      | 3         | Good shortcuts; Cmd+K lacks space navigation and "New page in…"                                 |
| 8         | Aesthetic and Minimalist Design | 3         | Calm visuals; three near-duplicate list views plus Spaces                                       |
| 9         | Error Recovery                  | 3         | Retry toast on create failure                                                                   |
| 10        | Help and Documentation          | 1         | Nothing explains spaces vs global lists                                                         |
| **Total** |                                 | **23/40** | **Acceptable**                                                                                  |

## Design Specificity Verdict

Visual layer is authored (ink/rail/rule tokens, space hue dots, row "+", N/Shift+N). The IA is a generic type-first SaaS sidebar in a product organized by space. That mismatch is the problem.

Detector: CLI 0 findings. Browser overlay: undersized-ui-text x5 on /spaces (.tag 10px, resources/css/app.css:233, used at spaces/index.tsx:132,136) — real. overused-font (Inter) — taste flag. layout-transition — false positive (vendor/unused shadcn CSS).

## Priority Issues

1. [P0] Create destination invisible; defaults silently to Private. CreatePage.php:27, app-layout.tsx:288-323, pages/index.tsx:182, Cmd+K on global views. Fix: one "New" split button by Search, "New page in [Space]", default current space → last used → Private; destination shown in button, Cmd+K, and toast ("Created in General · Move · Undo"). /impeccable shape
2. [P1] Pages / Boards / Files compete with Spaces. app-layout.tsx:268-332. Fix: replace with Home (recent across spaces) + Inbox + Spaces; type filter (All · Pages · Boards · Files) inside each space; redirect /pages to Home. /impeccable distill
3. [P1] Boards hide their space and can't move; double highlight (Boards + Private). board.tsx:46-75, app-layout.tsx:243,317. Fix: shared breadcrumb "General / Title" with Move to… on pages and boards; drop unlabeled select (page.tsx:184); highlight only the space. /impeccable clarify
4. [P2] Instant-create leaves Untitled debris; undo inconsistent. use-create-page.ts:12. Fix: always toast with Undo/Move; focus title on arrival; auto-trash empty Untitled on leave. /impeccable harden
5. [P3] Personal space naming drift + 10px tags. CreatePersonalSpace.php:21, app.css:233. Fix: "Private" everywhere; move admin group under Admin as "Members' private spaces"; tags to 11px+. /impeccable clarify

## Persona Red Flags

- Alex: N on global views silently creates in Private; no keystroke to pick a space; Cmd+K can't jump to spaces/Inbox/Files; can't move a board.
- Sam: two identical "New page" accessible names on /pages; space select has no visible label; double aria-current on boards; row "+" invisible until focus.
- Jordan: lands on a cross-space list with no explanation of spaces; "Private (all users)" contradicts itself; Files has no upload; empty states have no CTA; "+" hover-only.

## Minor Observations

- Files header x=382 vs Pages x=320 — layout jumps between nav items.
- Mobile header New collapses to icon-only "+" with no destination.
- "Manage" next to Spaces reads as "manage this space".
- Space page header lacks visibility/member context.

## Questions to Consider

- Who needs "all boards across every space" more than search already gives them?
- Should New ever create without a visible destination?
- Is Private a space, or a drafts stage that sits apart from shared spaces?
