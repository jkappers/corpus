# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users
Teams inside organizations that self-host Corpus. They write docs and sketch boards together in spaces. Admins manage users and review the audit log. Developers and agents integrate through the API.

## Product Purpose
A minimal docs-and-boards app. Two content types: pages (rich text, stored as markdown) and boards (shapes, connectors, text on a canvas, stored as JSON). Content lives in spaces, with search, inbox, file attachments, notations with replies, versions, and trash. Success: an org runs it on its own infrastructure and the team does its writing and diagramming there.

## Positioning
- API-first: REST API with OpenAPI docs, personal access tokens, and OAuth clients make it programmable and agent-friendly.
- Self-hosted and auditable: the org owns the data, with admin controls and an audit log.

## Operating Context
Laravel 13, Inertia 3, React 19, TypeScript, Tailwind 4, Fortify auth, Tiptap editor, React Flow boards. Runs under Laravel Sail. Registration requires email verification.

## Capabilities and Constraints
- Pages and boards are distinct entities with separate resources, plus one mixed global search.
- Spaces (formerly notebooks) hold content; content can be transferred or deleted with the space.
- Auth: Fortify, two-factor, personal access tokens, OAuth clients.
- Admin: user management, audit log.
- Appearance settings exist; light and dark themes must both be first-class.

## Product Principles
- Stay minimal. Resist feature and chrome sprawl.
- Docs and boards are equals, not one nested in the other.
- Everything the UI does, the API can do.
- Admins can always see what happened.
- Light and dark are equal citizens.

## Accessibility & Inclusion
No formal standard required beyond the codebase's current practice.
