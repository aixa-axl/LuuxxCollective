# Luux — Custom WordPress Theme

Custom theme, built from scratch. Tailwind CSS v4 · ACF Pro Flexible Content · Cursor + Figma MCP · GitHub Actions → WP Engine.

## Local setup (once)

1. New site in Local (PHP 8.2+, latest WP), e.g. `luux.local`
2. Drop this repo's contents into the site root (or just the `wp-content/themes/luux` folder into themes)
3. `cd wp-content/themes/luux && npm install && npm run dev` (leave watching)
4. Install + activate plugins: **ACF Pro** (license), **Contact Form 7**, **WP Mail SMTP**, **Yoast SEO**
5. Activate the Luux theme — the `page_sections` field group syncs automatically from `acf-json/`
6. Settings → Permalinks → "Post name" → Save
7. Create the 7 pages, set the homepage under Settings → Reading

## Build workflow (per section)

1. Select the section frame in Figma → pull design context via Figma MCP in Cursor
2. Cursor generates the template part + ACF layout JSON per `.cursor/rules/luux-conventions.mdc`
3. ACF admin → Field Groups → sync if prompted; add the section to a page; check in browser
4. Fix responsive/mobile, commit

`template-parts/layouts/hero.php` is the reference layout — point Cursor at it for every new section.

## Tokens

All colours/fonts/widths live in `@theme` in `src/css/main.css`. First job: replace the placeholder values with real ones extracted from Figma variables via MCP. Fonts are self-hosted — add woff2 files to `assets/fonts/` and update the `@font-face` names.

## Deploy

GitHub Actions on push to `main` → builds Tailwind → rsyncs the theme to WP Engine.

Repo secrets needed (Settings → Secrets → Actions):
- `WPE_SSHG_KEY_PRIVATE` — private half of an SSH key added to WP Engine (Profile → SSH Keys)
- `WPE_ENV_NAME` — the WP Engine environment name (e.g. `luuxsite`)

Database/content changes are made directly in WP admin (staging or prod) — the repo owns code only.

## Launch checklist

- [ ] Real enquiry sent through the contact form **on production**, lands in inbox
- [ ] WhatsApp + tel links tested on a phone
- [ ] Lighthouse pass (images sized, lazy-loading below fold, fonts swap)
- [ ] Yoast titles/metas, XML sitemap, one h1 per page, alt text
- [ ] SSL forced, permalinks saved, caches cleared, WP Engine backups on
- [ ] `WP_DEBUG` off, no stray admin users
