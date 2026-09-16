# FBO (fbeing.online)

FBO is a simple blogging system for people who want their own place on the web.

It is built around one idea: publish directly, keep ownership, avoid platform noise.

Website: https://fbeing.online

## What FBO Is

FBO gives each blog its own URL and private admin access.

You can post images, video, audio, and text. The interface stays intentionally small and focused: compose, edit, publish.

There is no social feed logic, no ad model, and no engagement mechanics.

## How It Works

FBO uses a shared app core and separate content spaces per blog.

- One app codebase handles rendering, auth, upload flow, and UI.
- Each blog keeps its own content and media.
- Requests are routed by blog path (for example, /blog/name).

This keeps the platform easy to run while still isolating each blog's data.

## Architecture Overview

### Entry + Routing

- The web entry point forwards requests into the core router.
- The router resolves which blog should answer the request.

### Shared Application Layer

- The shared app contains page rendering, admin flows, and media/text posting logic.
- Shared assets (CSS/JS/snippets) keep behavior and design consistent.

### Per-Blog Data Layer

- Every blog has its own backend data files (posts, settings, auth state).
- Every blog has its own media directory for uploads.

### Tenant Registry Layer

- A central registry tracks blog identities and metadata.
- Provision logic creates and wires new blogs into the system.

## Content Model (Plain Language)

- A post is either text or media.
- Media posts can carry optional metadata such as location coordinates.
- Admin mode allows composing, editing settings, and content maintenance.

## Why This Architecture

- Clear separation: shared code vs. per-blog data.
- Practical isolation: each blog can be managed independently.
- Straightforward operations: simple files for content, predictable paths for media.

## Visitor contributions

Visitors leave exactly three symbols from a set of 24 before entering a
blog. None are preselected: visitors must choose their three symbols. Entry lasts for the
browser session, separately for each blog; owners bypass the entry screen.
Each blog starts with three persistent random symbols. Each contribution replaces
all three header symbols. The owner's footprints tab shows the last ten
changes, newest first, with timestamps and the contributor's blog name when logged
in. Anonymous changes show only their timestamp. Hover animates header symbols;
click shows a small, absolutely positioned preview at the top center for 3 seconds.
Symbols are smooth SVG silhouettes. The entry selection fills them with images
from the current blog (black fallback when it has no images); the header uses
black silhouettes. Motif animations play on selection, hover and the tap preview.
The footprints tab contains private notes and silhouette changes, separate from compose and edit.
Note login links use the remembered blog cookie or session when available.
Otherwise they lead to the root page. Entry selection allows exactly three symbols,
including repeats. Tap a symbol again to increase its count; when all three slots
are filled, tap a selected symbol to clear its count and choose again.
Shuffleboard displays all 24 silhouettes beside FBO; tapping them identifies the
FBO obolus. The booklet back page uses three silhouettes filled with randomly
chosen image posts (repeated when fewer than three images are available).

Visitors logged in to an existing FBO blog can send private notes on individual
posts after entering. The receiving owner reads them with a link to the post in
the private notes panel. Notes and contributor identities are never rendered for other visitors.
The session's most recently managed, still-authenticated blog supplies identity.

Data is stored separately in each blog's `backend/visitor-interactions.php`, with
locked writes and a PHP guard against direct HTTP reads, and included in owner
ZIP backups. Forms validate session CSRF tokens. Notes are limited to 2,000
characters and one submission per 30 seconds per session per blog.

The entry screen gates blog pages; existing direct media URLs and Shuffleboard
publication remain available as before.
