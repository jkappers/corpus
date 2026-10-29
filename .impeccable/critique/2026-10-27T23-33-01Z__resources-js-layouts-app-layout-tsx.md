---
target: sidebar
total_score: 22
max_score: 40
na_heuristics:
p0_count: 0
p1_count: 2
target_identity: 'file:/Users/jkappers/Projects/corpus/resources/js/layouts/app-layout.tsx'
target_fingerprint: 'sha256:a53ab52017e0009ad37a48c8d75f9b3e444f6e52dc53ff19cb48eac15d2ce962'
target_path: /Users/jkappers/Projects/corpus/resources/js/layouts/app-layout.tsx
timestamp: 2026-10-27T23-33-01Z
slug: resources-js-layouts-app-layout-tsx
---

# Critique: sidebar (Rail) — 22/40 Acceptable

P1 Active-state detection brittle (L47-54): exact matches miss detail routes; extract NavLink + isActive helper. /impeccable harden
P1 Mobile drawer + rail overflow (L57, L264-268): no trap/Esc/backdrop; footer clipped by long space list. /impeccable harden
P2 Footer overloaded, weak identity (L165-212): account menu + Admin group. /impeccable distill
P2 28px touch targets, no shortcuts/search (L35, L252). /impeccable adapt
P3 IA: Trash prominence, no space create/empty state (L110-163). /impeccable layout
Detector: CLI 0 findings; browser overlay 3 page-level items (text-overflow likely FP, overused-font Inter 96%, layout-transition body).
Scored heuristics: all 10. Assessment A was code-only (no browser).
