# GoodHost Plugin Suite

A lightweight WordPress plugin framework for hosting-related utilities by [Sites By Design](https://sitesbydesign.com.au).

## Overview

GoodHost provides a unified admin menu in WordPress for a collection of simple, focused sub-plugins. Each module handles one task well, without the bloat of large SEO or hosting plugins.

## Architecture

The GoodHost suite uses a **main installer + sub-plugins** architecture:

```
wp-content/plugins/
├── goodhost/                      # Main installer plugin
│   ├── goodhost.php
│   └── assets/
│       └── goodhost-logo.png      # Optional local logo
│
└── goodhost-news-sitemap/         # Sub-plugin (example)
    └── goodhost-news-sitemap.php
```

### Design Principles

1. **Separation of Concerns**: Main plugin handles the framework; sub-plugins handle functionality
2. **Single Responsibility**: Each sub-plugin does one thing well
3. **Shared Admin Menu**: All GoodHost modules appear under one admin menu
4. **Independent Operation**: Sub-plugins can be enabled/disabled independently
5. **Minimal Footprint**: No unnecessary database tables or bloated options

## Installation

### Main Plugin (Required)

1. Download the `goodhost` folder
2. Upload to `wp-content/plugins/`
3. Activate "GoodHost" in WordPress admin

### Sub-Plugins

1. Download the desired sub-plugin folder (e.g., `goodhost-news-sitemap`)
2. Upload to `wp-content/plugins/`
3. Activate the sub-plugin in WordPress admin
4. Configure via **GoodHost** menu in admin sidebar

## Available Sub-Plugins

### News Sitemap

Generates a Google News compatible sitemap at `yoursite.com/news-sitemap.xml`

**Features:**
- Follows [Google News Sitemap protocol](https://developers.google.com/search/docs/crawling-indexing/sitemaps/news-sitemap)
- Configurable publication name and language
- Filter by post types and categories
- Adjustable time window (default 48 hours per Google requirements)
- Maximum 1000 articles (Google's limit)

---

## Developing Sub-Plugins

Sub-plugins can register themselves with the GoodHost dashboard using the `goodhost_register_modules` filter:

```php
add_filter('goodhost_register_modules', function($modules) {
    $modules['your-module-slug'] = [
        'name' => 'Your Module Name',
        'description' => 'What it does',
        'active' => true,
        'settings_url' => admin_url('admin.php?page=goodhost-your-module')
    ];
    return $modules;
});
```

To add a submenu under GoodHost:

```php
add_action('admin_menu', function() {
    add_submenu_page(
        'goodhost',                    // Parent slug
        'Your Module',                 // Page title
        'Your Module',                 // Menu title
        'manage_options',              // Capability
        'goodhost-your-module',        // Menu slug
        'your_settings_callback'       // Callback function
    );
}, 20);  // Priority 20 ensures GoodHost menu exists first
```

## Requirements

- WordPress 5.0+
- PHP 7.4+

## Optional: Local Logo

To use a local logo instead of loading from goodhost.com.au:

1. Download the logo from `https://goodhost.com.au/wp-content/uploads/2021/10/goodhost-australian-web-hosting-2.png`
2. Save as `goodhost-logo.png` in the `goodhost/assets/` folder

## License

GPL v2 or later

## Credits

Developed by [Sites By Design](https://sitesbydesign.com.au) for [GoodHost](https://goodhost.com.au)
