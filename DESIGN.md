---
name: Corpus
description: Minimal docs-and-boards app. Flat neutrals, hairline rules, one blue accent.
colors:
  ground: "#fcfcfd"
  rail: "#f5f6f8"
  raised: "#ffffff"
  hover: "#f1f2f5"
  selected: "#eef2fd"
  ink: "#16181d"
  ink-2: "#4a505b"
  ink-3: "#646a76"
  rule: "#e8e9ed"
  line: "#d7dae0"
  edge: "#858b96"
  signal-blue: "#1d4ed8"
  ok: "#1b8040"
  warn: "#b26a00"
  bad: "#d0313a"
  bad-ink: "#b4232c"
typography:
  body:
    fontFamily: "Inter, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: "1.375rem"
  ui:
    fontFamily: "Inter, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.8125rem"
    fontWeight: 500
    lineHeight: "1.25rem"
  label:
    fontFamily: "Inter, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 500
    lineHeight: "1rem"
  tag:
    fontFamily: "ui-monospace, SF Mono, Menlo, Consolas, monospace"
    fontSize: "11px"
    fontWeight: 400
    letterSpacing: "0.04em"
  prose-body:
    fontFamily: "Inter, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    lineHeight: 1.7
  prose-h1:
    fontFamily: "Inter, ui-sans-serif, system-ui, sans-serif"
    fontSize: "2rem"
    fontWeight: 600
    lineHeight: "2.5rem"
    letterSpacing: "-0.025em"
rounded:
  sm: "2px"
  md: "4px"
spacing:
  cell: "2rem"
  rail: "12.5rem"
components:
  button-default:
    backgroundColor: "{colors.raised}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    height: "28px"
    padding: "0 10px"
  button-default-hover:
    backgroundColor: "{colors.hover}"
  button-primary:
    backgroundColor: "{colors.signal-blue}"
    textColor: "#ffffff"
    rounded: "{rounded.md}"
    height: "28px"
    padding: "0 10px"
  button-quiet:
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    height: "28px"
  input:
    backgroundColor: "{colors.raised}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    height: "28px"
    padding: "0 8px"
  tag:
    textColor: "{colors.ink-3}"
    rounded: "{rounded.sm}"
    height: "20px"
    padding: "0 6px"
  table-row:
    height: "{spacing.cell}"
    padding: "0 8px"
---

# Design System: Corpus

## Overview

**Creative North Star: "The Quiet Ledger"**

Corpus looks like a well-kept record: ruled lines, tight rows, neutral paper, and a single blue used to mark what is active or actionable. Nothing decorates. Structure comes from hairlines and tonal steps between ground, rail, and raised surfaces, not from shadows or color.

Density is deliberate. Controls are 28px tall, body text is 14px, list rows are 32px. The page content itself (rendered markdown) is the one place type grows, to 16px at 1.7 line height, so reading is comfortable while the chrome stays compact.

Light and dark are equal citizens. Every token has a dark twin; neither theme is derived by inversion.

**Key Characteristics:**
- Flat by default; hairlines (`rule`, `line`) do the work of shadows.
- One accent, Signal Blue, never decorative.
- 4px maximum radius; 2px for tags and inline code.
- Dense controls, tabular numerals on, one typeface (Inter) plus mono for tags and code.
- Brand lives in precision, not ornament.

## Colors

Cool, near-neutral grays with a single saturated blue. Semantic green, amber, and red appear only as status.

### Primary
- **Signal Blue** (#1d4ed8 light, #7aa2ff dark): primary buttons, links in prose, focus rings, selection borders, notation highlights, board handles and selected edges. Used on a small fraction of any screen.

### Neutral
- **Ground** (#fcfcfd light, #0e0f12 dark): page background.
- **Rail** (#f5f6f8, #0a0b0d): sidebar and code block backgrounds. Slightly recessed from ground.
- **Raised** (#ffffff, #16181c): cards, inputs, popovers, buttons.
- **Hover** (#f1f2f5, #1c1e23): hover fill, table header fill, secondary and muted surfaces.
- **Selected** (#eef2fd, #161d2e): pressed or current-page fill, paired with a Signal Blue border.
- **Ink** (#16181d, #e6e8ec): primary text.
- **Ink 2** (#4a505b, #a9aeb8): secondary text, blockquotes.
- **Ink 3** (#646a76, #8a909b): placeholders, metadata, muted labels.
- **Rule** (#e8e9ed, #22252a): default border; row and section dividers.
- **Line** (#d7dae0, #30333a): stronger border for cards, buttons, table cells.
- **Edge** (#858b96, #646a75): input borders and board connectors.

### Status
- **OK** (#1b8040, #45c06c), **Warn** (#b26a00, #eda23a), **Bad** (#d0313a, #ff6b66), **Bad Ink** (#b4232c, #ff8a85, danger text).

### Named Rules
**The One Voice Rule.** Signal Blue is the only chromatic accent. Status colors mark state, never emphasis.

**The Twin Rule.** Every color is defined in both themes. A new token ships with its dark value or not at all.

## Typography

**Body/UI Font:** Inter (with ui-sans-serif, system-ui fallback), features `tnum` and `cv05` on globally.
**Mono Font:** ui-monospace stack, for tags and code.

**Character:** One quiet sans, sized small and set tight. Hierarchy comes from weight and ink tone, not size jumps.

### Hierarchy
- **Body** (400, 14px, 22px): default UI text.
- **UI** (500, 13px, 20px): buttons, menu items, dense row text.
- **Label** (500, 12px, 16px, ink-2): field labels, table headers (ink-3).
- **Tag** (mono, 11px, 0.04em, uppercase): status and type chips.
- **Prose** (400, 16px, 1.7): rendered page content. H1 32/40, H2 24/32, H3 19/28, all semibold, tracking tight, balanced wrap, 2rem top margin.

### Named Rules
**The Weight Not Size Rule.** Inside chrome, separate levels with weight (400/500/600) and ink tone before reaching for a larger size.

## Layout

App shell: a 200px (12.5rem) rail beside a content area. Lists are table-like rows at 32px (`cell`) with 8px horizontal padding and rule dividers. Spacing follows the Tailwind 4px grid; 4/8/12px steps dominate. Touch devices (`pointer: coarse`) raise controls to 44px minimum. Reduced-motion disables all transitions.

## Elevation & Depth

Flat. Surfaces step through ground, rail, and raised tones and are separated by 1px rules. The only shadow is on floating layers (popovers, dialogs): `0 8px 24px -12px rgb(10 11 13 / 0.3)`. `shadow-xs` and `shadow-sm` are `none`.

### Named Rules
**The Hairline Rule.** Separate with a 1px border before adding a shadow. Shadows are reserved for things that float.

## Shapes

Small, square-leaning corners: 4px for controls, cards, code blocks, and the board controls; 2px for tags, inline code, and callouts. No pills, no large radii. Borders are always 1px.

## Components

### Buttons
- **Shape:** 4px radius, 1px border, 28px tall (24px small, 28px square icon), 10px horizontal padding, 13px/500 text, 16px icons.
- **Default:** Raised fill, Line border; hover shifts border to Edge and fill to Hover.
- **Primary:** Signal Blue fill, white text (dark ink on dark theme); hover brightens 10%.
- **Quiet:** transparent; hover fills Hover.
- **Danger:** default look with Bad Ink text.
- **Link:** blue text, underline on hover.
- **Pressed/current:** Selected fill with Signal Blue border.
- **Timing:** 100ms color transition.

### Inputs / Fields
- **Style:** 28px tall, Raised fill, Edge border, 4px radius, 8px padding, Ink 3 placeholder.
- **Focus:** border turns Signal Blue; global 2px Signal Blue focus-visible outline with 2px offset.
- **Label:** 12px/500 Ink 2 above the field, 4px gap.

### Tags
- 20px tall, mono 11px uppercase, Ink 3 on transparent, Line border, 2px radius.

### Table rows
- 32px min height, Rule bottom border, Hover fill on hover. Header row 28px, sticky, Ink 3 12px/500.

### Page prose
- Blockquote: 2px Line left rule, Ink 2. Alert callouts (note, tip, important, warning, caution) tint 8% of their status color with a 2px left border and a capitalized 12px/600 label.
- Notation highlight: Signal Blue at 14% fill with a 2px blue underline; active 34%; resolved is struck through in Ink 3.
- Code blocks: Rail fill, Rule border, 4px radius, highlight colors from Signal Blue, OK, Warn, Bad Ink.
- Images: 4px radius, optional 25/50/75% width and left/center/right alignment.

### Attachment card
- Line border, Raised fill, 4px radius; 2rem square quiet action buttons in the header; media separated by a Line top border.

### Board canvas
- Ground background with Line dot grid; Edge connectors at 1.5px, Signal Blue when selected; 8px handles (Raised fill, blue 1.5px border) revealed on hover or selection; controls with Line border and no shadow.

## Do's and Don'ts

### Do:
- **Do** separate regions with 1px Rule or Line borders.
- **Do** use Signal Blue for the single most important action, current state, and focus.
- **Do** define every new color in light and dark.
- **Do** keep controls at 28px (44px on coarse pointers).
- **Do** use the `.input`, `.label`, `.tag`, `.th`, `.tr` component classes before writing new styles.

### Don't:
- **Don't** add shadows to resting surfaces.
- **Don't** exceed a 4px radius or use pill shapes.
- **Don't** introduce a second accent color.
- **Don't** use color alone for status; pair it with text or an icon.
- **Don't** enlarge chrome type to create hierarchy; use weight and ink tone.
