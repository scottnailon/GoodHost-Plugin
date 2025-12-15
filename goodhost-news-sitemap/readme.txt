=== GoodHost - News Sitemap ===
Contributors: sitesbydesign
Tags: sitemap, google news, news sitemap, seo, xml sitemap
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Simple Google News sitemap generator. Creates a compliant news sitemap for submission to Google Search Console.

== Description ==

GoodHost News Sitemap generates a Google News compatible sitemap at `yoursite.com/news-sitemap.xml`. A lightweight alternative to premium SEO plugin features.

**Features:**

* Follows [Google News Sitemap protocol](https://developers.google.com/search/docs/crawling-indexing/sitemaps/news-sitemap)
* Configurable publication name and language
* Filter by post types and categories
* Adjustable time window (default 48 hours per Google requirements)
* Maximum 1000 articles (Google's limit)
* No bloat, no upsells, just works

**Requirements:**

This plugin requires the [GoodHost](https://github.com/scottnailon/GoodHost-Plugin) base plugin to be installed and activated.

**XML Output Format:**

`&lt;urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"&gt;
  &lt;url&gt;
    &lt;loc&gt;https://example.com/article-slug/&lt;/loc&gt;
    &lt;news:news&gt;
      &lt;news:publication&gt;
        &lt;news:name&gt;Your Publication&lt;/news:name&gt;
        &lt;news:language&gt;en&lt;/news:language&gt;
      &lt;/news:publication&gt;
      &lt;news:publication_date&gt;2024-01-15T10:30:00+00:00&lt;/news:publication_date&gt;
      &lt;news:title&gt;Article Title Here&lt;/news:title&gt;
    &lt;/news:news&gt;
  &lt;/url&gt;
&lt;/urlset&gt;`

Developed by [Sites By Design](https://sitesbydesign.com.au) for [GoodHost Australian Web Hosting](https://goodhost.com.au).

== Installation ==

1. Ensure the GoodHost base plugin is installed and activated
2. Upload the `goodhost-news-sitemap` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to GoodHost > News Sitemap to configure settings
5. Submit `yoursite.com/news-sitemap.xml` to Google Search Console

== Frequently Asked Questions ==

= Why do I need the GoodHost base plugin? =

GoodHost provides the admin framework and menu structure. All GoodHost sub-plugins require it.

= How often is the sitemap updated? =

The sitemap is generated dynamically on each request. No caching, always current.

= Why only 48 hours of articles? =

Google News requires articles be published within the last 48 hours. You can adjust this in settings, but Google may ignore older articles.

= Can I include custom post types? =

Yes, the settings page lets you select which post types to include.

== Screenshots ==

1. News Sitemap settings page
2. Example sitemap output

== Changelog ==

= 1.0.0 =
* Initial release
* Google News compliant XML output
* Configurable publication name, language, post types, categories
* Adjustable time window

== Upgrade Notice ==

= 1.0.0 =
Initial release.
