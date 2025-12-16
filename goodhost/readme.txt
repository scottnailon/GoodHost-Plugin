=== GoodHost ===
Contributors: sitesbydesign
Tags: admin, dashboard, utilities, framework, modules
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight plugin framework for hosting-related utilities. Provides a unified admin menu for GoodHost sub-plugins with one-click installation.

== Description ==

GoodHost provides a unified admin menu in WordPress for a collection of simple, focused sub-plugins. Each module handles one task well, without the bloat of large multi-purpose plugins.

**Features:**

* Clean, branded admin dashboard
* One-click install of sub-plugins directly from the dashboard
* Unified menu for all GoodHost modules
* Lightweight framework with minimal footprint
* Easy for developers to extend with new modules
* Automatic updates from GitHub repository

**Available Sub-Plugins:**

* **News Sitemap** - Google News sitemap generator

GoodHost is developed by [Sites By Design](https://sitesbydesign.com.au) for [GoodHost Australian Web Hosting](https://goodhost.com.au).

== Installation ==

1. Upload the `goodhost` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Find the GoodHost menu in your admin sidebar
4. Browse available sub-plugins and click Install

== Frequently Asked Questions ==

= Do I need this plugin? =

GoodHost is the base framework required for all GoodHost sub-plugins. Install it first, then add the specific modules you need from within the dashboard.

= Where can I find sub-plugins? =

Sub-plugins are listed directly in the GoodHost dashboard. Just click Install next to any module you want.

= Can I develop my own sub-plugins? =

Yes! Sub-plugins can register with the GoodHost dashboard using the `goodhost_register_modules` filter. See the documentation on GitHub for details.

== Screenshots ==

1. GoodHost dashboard showing available modules with one-click install

== Changelog ==

= 1.1.0 =
* Added one-click sub-plugin installation from dashboard
* Plugin now fetches available modules from GitHub
* Added refresh button to update module list
* Improved UI with install/activate/settings buttons

= 1.0.0 =
* Initial release
* Admin dashboard with module management
* Framework for sub-plugin registration

== Upgrade Notice ==

= 1.1.0 =
New feature: Install sub-plugins directly from the GoodHost dashboard with one click!

= 1.0.0 =
Initial release.
