# AGENTS.md

Child theme of Hello Elementor for tafaol.sa. See README.md for the content model, structure and gotchas.

## Scope (read first)

When working from this folder, the work is **only this theme** and its two sites: https://tafaol.sa (production)
and http://tafaol-sa-dev.local/ (local). Everything in the theme is in scope, including its car directory
(`inc/cars/`, car templates). Off limits: the staging side described in the parent folder's `AGENTS.md`
(the `tafaol-sa-car-clean-dev` site, `almuraba-core`, `almuraba-theme`, scraping output). Never touch it from here.

## Local site

- URL: http://tafaol-sa-dev.local/
- Runs in LocalWP. Start the site in Local before testing; requests fail when it's stopped.
- Production: https://tafaol.sa

## Commands

```bash
# Syntax check
php -l functions.php
```

Don't run the Tailwind CLI (with or without `--watch`). The developer keeps it running in watch mode, so `assets/css/style.min.css` rebuilds automatically when `src/input.css` or Tailwind classes in `.php`/`.js` files change. Commit the rebuilt `style.min.css`. It's the only stylesheet the theme enqueues.

## Conventions

- ACF field groups, post types and taxonomies live in `acf-json/`. Edit them through ACF and sync, not by hand.
- Icons are self-hosted and compiled into `style.min.css`. Don't add CDN stylesheets.
  - Font Awesome: only `fas` (solid) and `fab` (brands) are loaded.
  - Material Symbols is a subset font. Using a new icon name means re-downloading `assets/fonts/material-symbols-outlined.woff2` (steps in README → Gotchas).
- Function names: don't prefix with `hello_` or `tafaol_`. Use plain descriptive names like `render_post_dates()` or `get_dynamic_cpt_labels()`, and grep first to avoid collisions.
- Text domain: `hello-elementor-child`. The site is Arabic (RTL), but source strings are English: wrap every UI string
  in `__()` / `esc_html__()` / `_n()` / `_x()` and put the Arabic in `languages/ar.po`. JS gets its strings from PHP
  via `wp_localize_script`. Counts use `_n()`; the ar plural forms (0, 1, 2, 3-10, 11-99, 100+) handle Arabic number agreement.
  After adding or changing strings: regenerate `languages/hello-elementor-child.pot` (`wp i18n make-pot . languages/hello-elementor-child.pot
  --domain=hello-elementor-child --exclude=node_modules,acf-json,src,assets/css,languages --skip-js`), add the Arabic to `languages/ar.po`
  (Poedit / Loco, or by hand), then `wp i18n make-mo languages/ar.po languages`. Upload `ar.mo` with the code, or the site shows English.
- ACF labels (field groups, post types, taxonomies in `acf-json/`) translate through the theme domain too. `make-pot` can't read
  JSON, so their English texts are listed in `inc/acf-json-strings.php` (never loaded): add a line there when a label is added in ACF.
- Entity browse section renders tabs only; entities load on demand through `/hello/v1/entity-nav`, search through `/hello/v1/entity-search`. No post counts. Keep `normalize_entity_text()` (PHP) and `normalizeArabic()` (JS) identical.
- Tailwind also scans `assets/js/*.js`, so classes used only in JS still get compiled.
