---
target: page editing experience /pages/17
total_score: 21
max_score: 40
na_heuristics:
p0_count: 0
p1_count: 3
target_identity: 'file:/Users/jkappers/Projects/corpus/resources/js/pages/pages/page.tsx'
target_fingerprint: 'sha256:90224792bfb60a0ce43458127861d66ff0b3e8f236d5c7fe8c6f0aa924e0c455'
target_path: /Users/jkappers/Projects/corpus/resources/js/pages/pages/page.tsx
timestamp: 2026-10-28T18-04-06Z
slug: resources-js-pages-pages-page-tsx
---

Method: dual-agent (A: design review · B: detector + browser). Browser overlay failed (chrome-devtools profile locked).

| #     | Heuristic            | Score | Key issue                                                                                                |
| ----- | -------------------- | ----- | -------------------------------------------------------------------------------------------------------- |
| 1     | Visibility of status | 2     | "Saved" shows on load and stays during the 800ms debounce while edits are unsaved                        |
| 2     | Match real world     | 3     | History shows raw "autosave" reason                                                                      |
| 3     | User control         | 2     | Esc in note form drops focus to body; restore does full reload                                           |
| 4     | Consistency          | 2     | Two "Add note" buttons; Read/Edit toggle plus separate "Read only" label; native select among icon tools |
| 5     | Error prevention     | 3     | beforeunload guard, conflict detection, move lock                                                        |
| 6     | Recognition          | 2     | No slash menu; markdown rules undiscoverable; Ctrl+Alt+M only                                            |
| 7     | Flexibility          | 1     | No Cmd+S, no focus mode, Enter in title doesn't reach body, ~12 tab stops title→body                     |
| 8     | Minimalist           | 2     | 4 chrome bands, 17 controls above first line; always-on empty Notes column                               |
| 9     | Error recovery       | 3     | Clear save/conflict errors with Retry/Reload                                                             |
| 10    | Help                 | 1     | Shortcuts only in title tooltips                                                                         |
| Total |                      | 21/40 | Acceptable                                                                                               |

Specificity: generic form-editing-a-document. Bordered body box (page-editor.tsx:716), full-width toolbar, native select. Product-specific parts: anchored Notes, Space breadcrumb.
Detector: 0 findings across page.tsx, editor/, notations/, version-history, item-location, app-layout.

Priority issues:

- [P1] Body reads as a form field: drop border, add placeholder, move formatting to selection bubble + "/" menu, toolbar optional. (/impeccable distill)
- [P1] Metadata spread over 3 rows: one quiet top bar (breadcrumb left; status, History, overflow right), title on own line, Read/Edit/Move into overflow. (/impeccable layout)
- [P1] Save status misleads: dirty state "Unsaved/Editing…", no "Saved" on load, relative time, Cmd+S flush. (/impeccable harden)
- [P2] Notes column always on (18rem, page.tsx:120): collapse when empty, margin markers, toggle with count. (/impeccable distill)
- [P2] Keyboard flow: Esc returns focus to editor, toolbar as single tab stop (roving), Enter/↓ in title → body, "/" menu, editor accessible name. (/impeccable harden)

Personas: Alex — no Cmd+S, no slash, 12 tab stops. Sam — focus lost on Esc, unnamed editor textbox, chatty status region, no skip link, duplicate Add note. Long-form writer — status/History scroll away, narrow column + permanent Notes, no outline/word count/focus mode, history inline at top, restore reloads.

Minor: title shares row with breadcrumb; mobile toolbar clipped, ~190px chrome, hint says Ctrl+Alt+M; history rows lack diff/grouping; Attach file buried in More; Link button on link removes it instantly; Read/Edit stored per page in localStorage; disabled Add note explained only by tooltip.

Questions: Does a page need Read/Edit at all? Are Notes writing or reviewing? If the toolbar vanished, leaving bubble + "/", what would anyone miss?
