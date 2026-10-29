---
target: how new pages and boards are added
total_score: 23
max_score: 40
na_heuristics:
p0_count: 0
p1_count: 3
target_identity: 'file:/Users/jkappers/Projects/corpus/resources/js/pages/pages/index.tsx'
target_fingerprint: 'sha256:0db5f587b068fffd1a10730c3aef915b6797a6835e49c8811d2b7071974323a5'
target_path: /Users/jkappers/Projects/corpus/resources/js/pages/pages/index.tsx
timestamp: 2026-10-28T15-40-18Z
slug: resources-js-pages-pages-index-tsx
---

# Critique: creating pages and boards (resources/js/pages/pages/index.tsx)

Method: dual-agent (A: design review · B: detector + browser)

## Heuristics: 23/40 (Acceptable)

1 Visibility of status 2: create buttons grey out with no spinner or aria-busy
2 Match real world 3: on /pages and /boards nothing says which space the new item goes into
3 User control 3: a stray N press leaves an untitled item with no undo
4 Consistency 2: in a space, filled-button position flips between header and empty state; N hint on one button only
5 Error prevention 3
6 Recognition over recall 1: N only in a title tooltip; no create option in sidebar or ⌘K
7 Flexibility 2: N only on list pages; no board shortcut; no create from inside a document
8 Minimalist design 2: empty state repeats the create buttons; Settings sits with the create actions
9 Error recovery 3: error toast has no Retry
10 Help 2

## Design specificity

Tokens are made for this product; the create controls are a stock row of text-only "New X" buttons. Detector: CLI 0 findings. Browser: overused-font (advisory, app-wide) and layout-transition (false positive: toast library CSS and sidebar.tsx:470).

## Priority issues

- [P1] Empty state repeats the header create buttons; in a space the order flips (index.tsx:255-284). Fix: one place to create; copy per type. /impeccable distill
- [P1] Space header puts Settings, New board and New page side by side as peers; space name cut to ~9 chars at 390px (L203-238). Fix: gear icon for Settings; split button "New page ▾ New board"; Plus icon. /impeccable layout
- [P1] Create only possible from list pages; none in sidebar or ⌘K palette. Fix: "+" on hover on nav and space rows; palette commands. /impeccable shape
- [P2] N shortcut invisible; no board shortcut; WCAG 2.1.4 risk. Fix: <kbd> hint, aria-keyshortcuts, Shift+N for boards, Undo on the created toast. /impeccable clarify
- [P2] No feedback while creating. Fix: spinner on the clicked button only, aria-busy, Retry on the error toast. /impeccable polish

## Persona red flags

- Alex: no keyboard path to a board in a space; no palette command; no create from inside a document.
- Sam: duplicate buttons in the tab order; N shortcut not announced; silent pending state.
- Casey: 3 touch buttons squeeze the space name; duplicate empty-state buttons take the first screen.

## Minor observations

- "Nothing here yet" is generic; read-only spaces hide create silently; useEffect has no dependency array (L136-156).

## Questions to consider

- Does the user need to pick page vs board up front?
- Why does creation live only on list pages?
- Does a space's Settings belong on its content view?
