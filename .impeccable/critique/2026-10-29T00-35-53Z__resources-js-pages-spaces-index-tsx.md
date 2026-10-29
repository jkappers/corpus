---
target: /spaces consistent colors
total_score: 21
max_score: 36
na_heuristics: 9
p0_count: 0
p1_count: 2
target_identity: "file:/Users/jkappers/Projects/corpus/resources/js/pages/spaces/index.tsx"
target_fingerprint: "sha256:4a93259157c0dd141d376c5777eb768ad1138d64e68672d6f48f07c602f39232"
target_path: /Users/jkappers/Projects/corpus/resources/js/pages/spaces/index.tsx
timestamp: 2026-10-29T00-35-53Z
slug: resources-js-pages-spaces-index-tsx
---
# Critique: /spaces
Score 21/36 (n/a: 9). Method degraded: no browser evidence.

P1 bg-rail + border-y + fake th on empty state (spaces/index.tsx:71-74): remove, bare EmptyState with New space action.
P1 Fixed 6-col grid on mobile (:20-21): hide th below md, 2-row stacked layout.
P2 Redundant Privacy chip + icon; role as plain text.
P2 Settings button always visible; hover-reveal like pages/index.tsx.
