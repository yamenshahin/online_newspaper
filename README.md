# Sharaka Online Newspaper — Hello Elementor Child Theme

[![WordPress](https://img.shields.io/badge/WordPress-1172B7?style=flat-square&logo=wordpress&logoColor=white)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS_v4-38B2AC?style=flat-square&logo=tailwind-css&logoColor=white)](https://tailwindcss.com/)
[![Elementor](https://img.shields.io/badge/Elementor-92003B?style=flat-square&logo=elementor&logoColor=white)](https://elementor.com/)
[![ACF Pro](https://img.shields.io/badge/ACF_Pro-00E6A1?style=flat-square&logo=wordpress&logoColor=black)](https://www.advancedcustomfields.com/)
[![Node.js](https://img.shields.io/badge/Node.js-339933?style=flat-square&logo=nodedotjs&logoColor=white)](https://nodejs.org/)
[![WP-CLI](https://img.shields.io/badge/WP_CLI-000000?style=flat-square&logo=wordpress&logoColor=white)](https://wp-cli.org/)

An Arabic (RTL) news theme for Sharaka Online Newspaper, built as a child of `hello-elementor`. Each page is assembled from ACF Flexible Content sections. Departments brand their own pages with a logo, color and footer. The theme also includes directories for public entities, speakers, events and cars.

---

## Requirements

| Dependency           | Notes                                                                                   |
| :------------------- | :-------------------------------------------------------------------------------------- |
| WordPress + PHP 8.x  | Code uses typed signatures and nullable return types.                                   |
| Hello Elementor      | Parent theme (`Template: hello-elementor`).                                             |
| Elementor            | Footer template, built from the theme's shortcodes. The header is Hello's PHP header, overridden in `template-parts/dynamic-header.php`. |
| ACF Pro              | **Required.** Post types, taxonomies and field groups are all defined in `acf-json/`.   |
| Node.js              | Only needed to rebuild CSS (Tailwind CSS v4 via `@tailwindcss/cli`).                    |
| WP-CLI (optional)    | For flushing rewrites/cache after deploys.                                              |

> Custom post types and taxonomies are **not** registered in PHP. ACF Pro loads them from `acf-json/`. If ACF is inactive, these post types and taxonomies are not registered and their templates have nothing to show.

---

## Content Model

### Post types

| Slug          | Label        | Permalink               | Notes                                                    |
| :------------ | :----------- | :---------------------- | :------------------------------------------------------- |
| `post`        | Articles     | `/news/{id}/`           | Hero lead, featured and trending layouts.                |
| `program`     | Programs     | `/program/{id}/`        | Grouped by `program_series`.                             |
| `infographic` | Infographics | `/infographic/{id}/`    |                                                          |
| `interview`   | Interviews   | `/interview/{id}/`      |                                                          |
| `video`       | Videos       | `/video/{id}/`          |                                                          |
| `podcast`     | Podcasts     | `/podcast/{id}/`        | `podcast_reels` relationship → reels.                    |
| `reel`        | Reels        | `/reel/{id}/`           | `parent_podcast` relationship → podcast.                 |
| `car`         | Cars         | `/car-directory/…`      | Spec fields (power, torque, price in SAR, …) + filters.  |

ID-based permalinks are registered in `functions.php` (rewrite rules + `post_link` / `post_type_link` filters).

### Taxonomies

| Slug                 | Applies to             | Purpose                                                                  |
| :------------------- | :--------------------- | :----------------------------------------------------------------------- |
| `department`         | All content types      | Sections of the paper; each has its own logo, color, icon, footer and layout. |
| `geographic`         | All content types      | Regions → countries → cities.                                            |
| `government_entity`  | All content types      | Saudi government bodies (profile, socials, extra info).                 |
| `private_entity`     | All content types      | Private sector companies.                                               |
| `international_entity` | All content types    | International bodies (same metadata as government). Flat for now: no groups. |
| `speaker_influencer` | All content types      | Public figures.                                                         |
| `event`              | All content types      | Events with organizer and location groups.                              |
| `post_editor`        | All content types      | Editor attribution.                                                     |
| `program_series`     | `program`              | Groups programs into series.                                            |
| `car_maker`, `car_model`, `car_year`, `car_origin`, `car_body_type`, `car_fuel_type` | `car` | Car directory facets. |

The built-in `category` and `post_tag` are unregistered for every post type.

---

## How Pages Are Built

### Layout Engine (ACF Flexible Content)

The `department_sections` field ("Department Layout Engine") is attached to **the front page** and to **every department term**. Each row renders `template-parts/sections/{layout}.php`:

`hero_lead_section` · `posts_section` · `programs_section` · `program_series_section` · `infographics_section` · `interviews_section` · `videos_section` · `podcasts_section` · `speakers_section` · `geographic_section` · `filter_section` · `cars_section` · `social_section` · `ad_banner_section` · `ad_code_section`

- `social_section` is hidden in the editor when you edit a regular page.
- Section titles and subtitles defined on the homepage are reused as labels across the site (`get_dynamic_cpt_labels()`, `get_dynamic_taxonomy_labels()`), so UI strings aren't hardcoded.

### Templates

| Template                         | Behavior                                                                                         |
| :------------------------------- | :----------------------------------------------------------------------------------------------- |
| `front-page.php`                 | Renders the homepage Layout Engine.                                                              |
| `taxonomy-department.php`        | Renders the department's Layout Engine. `?view={post_type}` switches to one post type, paginated. Taxonomy query args (`?geographic=…`) filter the sections. |
| `taxonomy-{entity}.php`          | `geographic`, `government_entity`, `private_entity`, `international_entity`, `speaker_influencer`, `event`: a profile header followed by the **homepage** Layout Engine, filtered to that term. Layouts in `get_term_archive_skip_layouts()` are skipped. |
| `single.php`, `single-podcast.php`, `single-reel.php`, `single-car.php` | Single views.                                          |
| `archive-car.php`                | Cars directory (wraps `cars_section`).                                                           |
| `search.php`                     | Results for every `?s=` request, including `?s=…&post_type=…`. See *Site search* below.          |

### Department context & branding (`inc/department-context.php`)

`get_context_department()` works out which department the current request belongs to: the department archive, the first department of a single post, or the `cars` department on car screens. The site then uses that department for:

- the **custom logo** (`theme_mod_custom_logo` filter),
- the **theme color**, injected as CSS variables in `wp_head`,
- the **footer**, via shortcodes.

If a department leaves a field empty, `get_department_field_with_fallback()` uses the value from the fallback department instead.

### Entity browsing (`inc/taxonomy-helpers.php`)

Tabbed, searchable browser for hierarchical entity taxonomies (`template-parts/entities/entity-browse-section.php`). The server renders only the tabs; each group's entities are fetched from `/hello/v1/entity-nav` the first time its tab is opened (the first visible group is preloaded), and the search box queries `/hello/v1/entity-search` across all four taxonomies (`get_entity_taxonomies()`). Entities are listed alphabetically; no post counts are shown.

Each taxonomy's term map (id, name, parent, link, normalized name) is cached in a transient for a day and cleared when a term is created, edited or deleted. Links are stored in the map, so rendering never calls `get_term_link()` per term. `normalize_entity_text()` in PHP and `normalizeArabic()` in `assets/js/filters.js` must stay identical.

### Cars directory (`inc/cars.php`)

- Taxonomy facets, numeric range filters (`price`, `power`, `torque`, `engine`, `seats`), exact-match text filters (`transmission`, `drive`), plus a text search.
- Filters live in the URL as GET parameters. `assets/js/filters.js` updates the results over AJAX, without a full page reload.
- The `cars` department term (slug `cars`) supplies the logo and accent color for all car screens.

### Site search (`inc/site-search.php`)

- `/?s=` searches posts, programs, interviews, infographics, videos, podcasts and reels (`get_site_search_post_types()`). Pages and cars are left out. A `post_type` in the URL narrows the search to one of those types.
- A term shorter than 2 characters (`SITE_SEARCH_MIN_LENGTH`) skips the database query, and `search.php` shows a prompt instead.
- `search.php` renders each result with `template-parts/cards/card-{type}.php`, or `card-post` when a type has no card. Reel images start at 16:9 and grow to fill their row.
- The form lives in `template-parts/search/search-form.php` and sends `s` to `home_url('/')`.
- Only the front-end main search query changes. Admin searches, feeds, REST and the cars directory's own query (`car_s`) keep core behaviour.

### Related content (`inc/related-content.php`)

`single.php` shows two rows of up to 4 cards under posts, programs, interviews, infographics and videos. Podcasts, reels and cars have no related section. Reels and cars never appear as related items, but podcasts can.

- **Main row (قد يهمك أيضًا):** always full. Editor picks come first, from the optional ACF `related_posts` field (up to 4, any content type). Items of the same post type ranked by shared terms come next. The newest items of the same department, then of the whole type, fill any remaining slots.
- **Strip (تغطيات أخرى حول الموضوع):** other content types, shown only when they share a specific term. Department filler is never used here, and the row is hidden when nothing qualifies.
- **Weights per shared term:** `program_series` 8, `speaker_influencer` / `event` 5, `government_entity` / `private_entity` / `international_entity` 4, `geographic` 2. A shared department, or a place used on 200+ items (`RELATED_BROAD_PLACE_COUNT`, e.g. السعودية), counts 1 and only breaks ties. Ties go to the newest item. There's no age limit.
- **Caching:** the ranked candidates are cached in a transient per post for 12 hours and cleared when the post is saved. The editor picks are read live, so editing them shows up at once.

---

## Shortcodes

| Shortcode                         | Use                                                          |
| :-------------------------------- | :----------------------------------------------------------- |
| `[department_footer_branding]`    | Footer logo + label for the current department.             |
| `[department_footer_social]`      | Social links for the current department.                    |
| `[plain_menu name="…"]` / `[plain_menu location="…"]` | Flat, single-level menu links (optional `class`). |

---

## Endpoints

| Endpoint                                         | Method | Purpose                                     |
| :----------------------------------------------- | :----- | :------------------------------------------ |
| `/wp-json/hello/v1/entity-nav`                   | GET    | `?taxonomy=&parent=` → entities under a group (id, name, link). |
| `/wp-json/hello/v1/entity-search`                | GET    | `?q=&taxonomy=all` → `{items, truncated}`: matching entities across taxonomies, max 100 per taxonomy (min. 2 chars). |
| `/wp-json/newspapersite/v1/track-view/{id}`      | POST   | Page-cache-safe view counter (`post_views_count` meta). |
| `admin-ajax.php?action=cars_filter`              | POST   | Car results + filter chips HTML (nonce-protected). |

View counts are shown in a read-only meta box and a sortable **Views** column in the admin.

---

## Repository Structure

```text
hello-elementor-child/
├── acf-json/                  # ACF Local JSON: post types, taxonomies, field groups (source of truth)
├── assets/
│   ├── css/style.min.css      # Compiled Tailwind output (committed, enqueued)
│   ├── fonts/                 # SF Pro AR Display + Material Symbols subset (woff2)
│   ├── webfonts/              # Font Awesome solid + brands (woff2)
│   ├── images/                # Fallback images and icons
│   └── js/filters.js          # Header search, show-more, entity tabs/search, cars AJAX filters
├── inc/
│   ├── cars.php               # Cars directory: filter state, queries, facet counts, URLs
│   ├── department-context.php # Current department + field fallback + theme color
│   ├── related-content.php    # Related rows under single items: picks, ranking, cache
│   ├── site-search.php        # Searchable post types, short-term skip, result count text
│   ├── taxonomy-helpers.php   # Entity trees, caching, REST routes, term-archive skip list
│   └── shortcodes/
│       └── department-footer.php
├── src/input.css              # Tailwind v4 entry (@theme tokens, fonts, custom CSS)
├── template-parts/
│   ├── archive/               # Shared CPT loop
│   ├── cards/                 # One card per content type
│   ├── cars/                  # Filter chips, taxonomy selects, "more" filters, results
│   ├── entities/              # Entity browse section
│   ├── profiles/              # Term profile headers, organizer, department nav
│   ├── related/               # Related content rows (single.php)
│   ├── search/                # Site search form
│   ├── sections/              # Layout Engine sections (one file per layout)
│   ├── department-single-cpt.php
│   ├── dynamic-header.php     # Hello's header + header search (see Gotchas)
│   └── header-search.php      # Header search toggle + panel
├── front-page.php
├── taxonomy-*.php
├── single*.php / archive*.php
├── search.php                 # Search results
├── functions.php              # Assets, permalinks, admin UI, labels, views, AJAX
└── style.css                  # Theme header only
```

`html/` (design references) and `commits.txt` are git-ignored local files.

---

## Development

### Setup

```bash
cd wp-content/themes/
git clone <repository-url> hello-elementor-child
cd hello-elementor-child
npm install
```

Activate **Hello Elementor** (parent) and this child theme, and make sure **ACF Pro** is active. ACF loads the post types, taxonomies and field groups from `acf-json/` automatically. To copy them into the database or edit them, use **ACF → Field Groups → Sync available**.

### Building CSS

`src/input.css` compiles to `assets/css/style.min.css`, which is the only stylesheet the theme enqueues. Tailwind scans every `.php` file in the theme for class names. `package.json` has no npm scripts yet, so run the CLI directly:

```bash
# Development (watch)
npx @tailwindcss/cli -i ./src/input.css -o ./assets/css/style.min.css --watch

# Production
npx @tailwindcss/cli -i ./src/input.css -o ./assets/css/style.min.css --minify
```

Commit the rebuilt `style.min.css`. The enqueue uses `filemtime()` for its version string, so browsers fetch the new file without manual cache busting.

### After deploying

Flush rewrites after changing post types, taxonomies or permalink rules, then clear caches:

```bash
wp rewrite flush
wp cache flush
```

---

## Gotchas

- **Hardcoded Elementor post IDs.** `remove_elementor_post_stylesheet()` dequeues `elementor-post-173742` and `elementor-post-16`, which are specific to this site. Update both IDs if the footer template or default kit changes.
- **The header template is a copy of Hello's.** `template-parts/dynamic-header.php` is Hello Elementor 3.5.1's file plus the commented "Child theme: header search" blocks. After a Hello update, run `diff -u --strip-trailing-cr ../hello-elementor/template-parts/dynamic-header.php template-parts/dynamic-header.php` in this folder; anything besides those blocks is an upstream change to copy in. The override is only used while Elementor's **Header & Footer** feature (Hello Theme, under Elementor settings → Features) is active and Hello's "Disable theme header and footer" is off. Otherwise Hello renders `template-parts/header.php`, which has no search.
- **Performance tweaks.** The theme dequeues the block library, global styles and Elementor's Google Fonts (Roboto). Icons are self-hosted and compiled into `style.min.css`. Font Awesome 6.5.1 comes from the npm package and includes only the solid and brands sets. Material Symbols is preloaded.
- **Material Symbols is a subset.** `assets/fonts/material-symbols-outlined.woff2` contains only the icons the theme uses: close, corporate_fare, expand_more, graphic_eq, movie, person, play_arrow, podcasts, search, search_off, share, timer, tv, videocam. A new icon renders as plain text until you re-download the font. Open `https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=<comma-separated, alphabetical>&display=block` in Chrome and save the woff2 file it links to. Also, only the FILL 0 style is included, so `font-variation-settings: 'FILL' 1` has no effect.
- **Font Awesome icons need the `fas` or `fab` prefix.** `far` (regular) isn't loaded.
- **Rank Math's virtual `robots.txt` is turned off**, so serve a physical `robots.txt` instead.
- **Cars branding** requires a `department` term with the slug `cars`.

---

## Got a Project in Mind?

If you're looking for a consultant, or full-stack dev to build, scale, or optimize your next project, let's chat!

- **Email:** [yamenshahin@gmail.com](mailto:yamenshahin@gmail.com)
- **WhatsApp:** [+201097444740](https://wa.me/201097444740)
- **GitHub:** [github.com/yamenshahin](https://github.com/yamenshahin)
