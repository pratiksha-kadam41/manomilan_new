# Manomilan

Manomilan is a matchmaking website built with React and Vite. It includes public pages for learning about the service, browsing success stories, and contacting the team, along with separate candidate and admin areas.

## Requirements

- Node.js and npm

## Getting started

```bash
npm install
npm run dev
```

Vite prints the local development URL after the server starts.

## Available scripts

| Command           | Description                          |
| ----------------- | ------------------------------------ |
| `npm run dev`     | Start the development server         |
| `npm run build`   | Create a production build in `dist/` |
| `npm run preview` | Preview the production build locally |
| `npm run lint`    | Run ESLint                           |

## Application pages

The app uses clean path-based routes. Public pages include:

- `/` - Home
- `/aboutus` - About
- `/how-it-works` - How it works
- `/success-stories` - Success stories
- `/our-services` - Services
- `/contactus` - Contact

Login and dashboard routes include `/login`, `/candidate`, `/admin`, and `/admin/profiles`. Candidate and admin pages require a session. Production web servers must rewrite unknown application paths to `index.html` so direct links and browser refreshes work.

## Backend configuration

Set `VITE_API_URL` in a local `.env` file to the base URL of the backend API. The frontend sends login requests to `${VITE_API_URL}/login` and logout requests to `${VITE_API_URL}/logout`.

When `VITE_API_URL` is not set, development builds provide temporary test logins from `src/auth.js`; this fallback is disabled outside development. Configure a real backend for production use.

## Tech stack

- React
- Vite
- React Router, Bootstrap, and Bootstrap Icons
- Chart.js
