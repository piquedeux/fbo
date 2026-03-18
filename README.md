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

In short, FBO is a focused publishing platform, not a theme or template package.