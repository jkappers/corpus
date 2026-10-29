---
target: sidebar notations
total_score: 25
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 3
target_identity: "file:/Users/jkappers/Projects/corpus/resources/js/components/notations/notation-panel.tsx"
target_fingerprint: "sha256:15b194b1481cf6789ad9c92d2ca554901e7628e1287eba11574c8e80cdfb7215"
target_path: /Users/jkappers/Projects/corpus/resources/js/components/notations/notation-panel.tsx
timestamp: 2026-10-28T19-57-08Z
slug: s-components-notations-notation-panel-tsx-fe4c25c8
---
# Critique: notes sidebar (notation-panel.tsx / notation-thread.tsx)

Method: dual-agent, source-only (app not running, detector 0 findings). Score 25/40 (Acceptable). Scores: 2,3,3,3,3,2,3,1,3,2

P1 No hierarchy inside a thread: quote styled loudest, note quietest (notation-thread.tsx:157-232, app.css:266) -> layout, quieter
P1 Assignment hidden/weak, absent for self-assigned (notation-thread.tsx:48-56,228) -> clarify, colorize
P1 Full controls on every open thread (notation-thread.tsx:249-277) -> distill
P2 Active thread barely distinct (notation-thread.tsx:172) -> bolder
P2 No structure above list: no sort by page position, no Mine filter (notation-panel.tsx:155-162) -> layout
Cognitive load: 6/8 checklist failures (high).
