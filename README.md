# Corpus

Minimal docs-and-boards app. Two document types:

- **Doc**: rich text, stored as markdown (Tiptap + `@tiptap/markdown`).
- **Board**: shapes, connectors and text on a canvas, stored as JSON (React Flow).

Laravel 13 · Inertia 3 · React 19 · TypeScript · Tailwind 4 · Fortify auth · Sail · Boost.

## Run

```sh
cp .env.example .env        # first time only
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate   # first time only
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev            # or: npm run build
```

App: http://localhost. Registration requires email verification; in local dev, mail goes to Mailpit if you add it via `sail:add`, or verify directly:

```sh
./vendor/bin/sail artisan tinker --execute="App\Models\User::where('email','you@example.com')->update(['email_verified_at'=>now()]);"
```

## Check

```sh
./vendor/bin/sail artisan test
./vendor/bin/sail bin pint --test
./vendor/bin/sail npm run types:check
./vendor/bin/sail npm run check
```

## Layout

- `app/Http/Controllers/DocumentController.php`: list, create, show, autosave (`PATCH`), delete.
- `app/Http/Requests/UpdateDocumentRequest.php`: validates `body` (docs) or `data` (boards) by type.
- `resources/js/pages/documents/`: `index`, `doc`, `board`.
- `resources/js/components/{editor,board}/`: Tiptap and React Flow components.
- `resources/js/hooks/use-autosave.ts`: debounced save via Inertia `useHttp`.
- `resources/css/app.css`: design tokens (relay-style), shadcn variables mapped onto them.
