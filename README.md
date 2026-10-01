# Sharaka Online Newspaper

[![WordPress](https://img.shields.io/badge/WordPress-1172B7?style=flat-square&logo=wordpress&logoColor=white)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-38B2AC?style=flat-square&logo=tailwind-css&logoColor=white)](https://tailwindcss.com/)
[![Elementor](https://img.shields.io/badge/Elementor-92003B?style=flat-square&logo=elementor&logoColor=white)](https://elementor.com/)
[![ACF Pro](https://img.shields.io/badge/ACF_Pro-00E6A1?style=flat-square&logo=wordpress&logoColor=black)](https://www.advancedcustomfields.com/)
[![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Node.js](https://img.shields.io/badge/Node.js-339933?style=flat-square&logo=nodedotjs&logoColor=white)](https://nodejs.org/)
[![WP-CLI](https://img.shields.io/badge/WP_CLI-000000?style=flat-square&logo=wordpress&logoColor=white)](https://wp-cli.org/)

Child theme developed for Sharaka Online Newspaper on top of the `hello-elementor` base theme. The codebase provides a flexible layout engine driven by Advanced Custom Fields (ACF), custom multi-media post types, taxonomy systems, and dynamic RTL styling.

---

## Technical Stack

| Component            | Technology                                                     |
| :------------------- | :------------------------------------------------------------- |
| **Core CMS**         | WordPress Core, Hello Elementor Parent Theme                   |
| **Backend**          | PHP 8.x, Advanced Custom Fields Pro, WP-CLI                    |
| **Frontend Styling** | Tailwind CSS, PostCSS, Custom Utility Engine (`src/input.css`) |
| **Typography**       | Noto Naskh Arabic, SF Pro AR Display                           |
| **Page Building**    | Elementor Integration, Native PHP Shortcodes                   |

---

## Custom Post Types

- **Podcasts (`podcasts`)**: Single episode rendering and card components (`single-podcast.php`, `card-podcast.php`).
- **Reels (`reels`)**: Vertical short-form media templates (`single-reel.php`, `card-reel.php`).
- **Videos (`videos`)**: Video archiving and dedicated section templates (`videos_section.php`, `card-video.php`).
- **Programs (`programs`)**: Show listing, series tracking, and episode structure (`program_series_section.php`, `card-program.php`).
- **Infographics (`infographics`)**: Visual content cards with dynamic aspect ratio handling (`infographics_section.php`, `card-infographic.php`).
- **Interviews (`interviews`)**: Editorial profile and Q&A layouts (`interviews_section.php`, `card-interview.php`).
- **Standard Articles (`posts`)**: Main editorial content supporting hero lead, featured, and trending layouts.

---

## Taxonomy System

- **Departments (`department`)**: Contextual department engine handling theme color injection, custom icons, dynamic footers, and isolated CPT views.
- **Geographic (`geographic`)**: Hierarchical grouping for regions, countries, and cities.
- **Government Entities (`government_entity`)**: Profiles and sector-specific metadata repeaters.
- **Private Entities (`private_entity`)**: Corporate entity archives and metadata cards.
- **Speakers & Influencers (`speaker_influencer`)**: Public figure profiles and commentary archives.
- **Editors (`editors`)**: Custom taxonomy managing multi-editor publication attribution.
- **Program Series (`program_series`)**: Term mapping for grouping episodic content across post types.

---

## Core System Architecture

### ACF Flexible Content Layout Engine

Sections are modularly rendered across front page (`front-page.php`) and department (`taxonomy-department.php`) views:

- **Hero Lead Section**: Editorial split view for top stories, breaking badges, and trending queues.
- **Ad Banner & Code Sections**: Ad handling supporting both raw code insertion and responsive image banners.
- **Media Sections**: Grid views supporting up to 4 columns on desktop viewports for Podcasts, Reels, Videos, Infographics, and Program Series.
- **Social Section**: Department-specific follower counts, descriptions, and social media links.

### Dynamic Label Resolution

- Taxonomy labels are retrieved dynamically from homepage ACF configurations to eliminate hardcoded UI strings.
- Centralized Arabic post-type mapping array provides translated UI badges across loop cards and archive templates.

### Contextual Department Theming

- Theme colors are injected programmatically via `inc/department-context.php` to customize border accents and primary UI colors per department.
- Elementor shortcode integration via `[department_footer]`.

---

## Repository Structure

```text
hello-elementor-child/
├── acf-json/                     # Synced ACF field group JSON files
├── assets/
│   ├── css/                      # Compiled CSS files (tailwind.css)
│   ├── fonts/                    # Font assets (SFProARDisplay, Noto Naskh)
│   ├── images/                   # Fallback assets and images
│   └── js/                       # Theme JavaScript scripts
├── inc/
│   ├── shortcodes/               # Shortcode registration (department-footer.php)
│   └── department-context.php    # Department theme color handling
├── src/
│   └── input.css                 # Primary Tailwind input file and root variables
├── template-parts/
│   ├── archive/                  # Shared archive header and loop templates
│   ├── cards/                    # Modular card component files
│   ├── profiles/                 # Taxonomy profile templates
│   └── sections/                 # Flexible content section modules
├── taxonomy-department.php       # Department archive engine
├── taxonomy-geographic.php       # Geographic archive template
├── taxonomy-government_entity.php# Government entity template
├── taxonomy-private_entity.php   # Private sector entity template
├── taxonomy-speaker_influencer.php# Speaker archive template
├── single-podcast.php            # Single Podcast entry point
├── single-reel.php               # Single Reel entry point
├── single.php                    # Universal single template for custom post types
├── functions.php                 # Core theme hooks, taxonomy, and CPT definitions
├── package.json                  # NPM build scripts and dependencies
└── style.css                     # Theme header file
```

## Development Setup & CSS Compilation

### 1. Installation

Clone the repository into the WordPress child themes directory:

```bash
cd wp-content/themes/
git clone <repository-url> hello-elementor-child
cd hello-elementor-child
npm install

```

### 2. Compiling Tailwind CSS

The source styles are defined in `src/input.css` and compiled into `assets/css/tailwind.css`.

- **Watch Mode (Development)**
  To monitor source files and recompile CSS automatically during development:

```bash
npm run watch

```

_Alternative CLI command:_

```bash
npx tailwindcss -i ./src/input.css -o ./assets/css/tailwind.css --watch

```

- **Production Build (Minified)**
  To generate the optimized and minified stylesheet for deployment:

```bash
npm run build

```

_Alternative CLI command:_

```bash
npx tailwindcss -i ./src/input.css -o ./assets/css/tailwind.css --minify

```

### 3. Deployment Post-Steps

After making changes to taxonomies, post types, or stylesheets, clear system caches and flush rewrite rules:

```bash
wp rewrite flush
wp cache flush

```

---

## Got a Project in Mind?

If you're looking for a consultant, or full-stack dev to build, scale, or optimize your next project, let's chat!

- **Email:** [yamenshahin@gmail.com](mailto:yamenshahin@gmail.com)
- **WhatsApp:** [+201097444740](https://wa.me/201097444740)
- **GitHub:** [github.com/yamenshahin](https://github.com/yamenshahin)
