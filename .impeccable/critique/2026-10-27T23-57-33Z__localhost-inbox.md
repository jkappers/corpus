---
target: 'http://localhost/inbox'
total_score: 22
max_score: 40
na_heuristics:
p0_count: 0
p1_count: 3
target_identity: 'url:http://localhost/inbox'
timestamp: 2026-10-27T23-57-33Z
slug: localhost-inbox
---

# Critique: /inbox

Score 22/40 (Acceptable). Detector: 0 findings; in-browser run inconclusive.

Priority issues

- [P1] Row hierarchy hides the ask (layout): lead with author and time, body as primary text, quote as context line.
- [P1] Weak targets, esp. mobile (adapt): stretched row link, 44px coarse-pointer buttons.
- [P1] No feedback/undo/error on Done and Reopen (harden): pending state, undo toast, onError, optimistic rollback.
- [P2] No time, grouping, pagination, body clamp (clarify).
- [P3] Generic empty states (onboard).

A11y: ul/li instead of role=list, aria-current instead of aria-pressed, per-row Done aria-label, announce success, keep focus.
