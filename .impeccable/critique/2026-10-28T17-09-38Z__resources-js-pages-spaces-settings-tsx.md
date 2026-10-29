---
target: Space settings components
total_score: 18
max_score: 40
na_heuristics:
p0_count: 0
p1_count: 3
target_identity: 'file:/Users/jkappers/Projects/corpus/resources/js/pages/spaces/settings.tsx'
target_fingerprint: 'sha256:d801e9cfe67cb0d6c45db191ec7012220718ef106ab1de118cd529bf3d00f745'
target_path: /Users/jkappers/Projects/corpus/resources/js/pages/spaces/settings.tsx
timestamp: 2026-10-28T17-09-38Z
slug: resources-js-pages-spaces-settings-tsx
---

Method: dual-agent (A: design review · B: detector). Browser skipped (login redirect).

Score 18/40 (Poor). Heuristics: 1=1, 2=3, 3=2, 4=1, 5=2, 6=2, 7=2, 8=2, 9=1, 10=2.

Verdict: generic; three section idioms on one page. Detector: 0 findings.

Priority issues:

- [P1] Sections lack shared structure (settings.tsx:67,208,222,316) -> layout
- [P1] Instant role saves with no feedback; errors in three places (:240-258,:174,:290) -> harden
- [P1] Delete unhandled, no destructive-zone treatment (:315-338) -> harden
- [P2] Bypasses shared form primitives (:69-106,:153-170) -> polish
- [P2] Member rows cramped (:230-289) -> clarify
