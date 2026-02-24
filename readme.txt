=== SEOAutowrite Pro ===
Contributors:      Tamzid Ahmed
Tags:              ai, seo, content, article writer, ollama, scheduled posts, automation
Requires at least: 6.0
Tested up to:      6.9
Requires PHP:      7.4
Stable tag:        1.0.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

AI-powered WordPress article writer with SEO optimisation, scheduled publishing, backlink briefs, FAQ schema, and featured image generation.

== Description ==

SEOAutowrite Pro automatically generates high-quality, SEO-optimised articles for your WordPress site on a schedule using the Ollama API.

Every generated article includes:

* Full HTML article body with proper heading hierarchy (h1 → h2 → h3)
* Meta title (max 60 characters), meta description (150–160 characters), and URL slug
* Primary keyword and 5–10 semantically related keywords (LSI)
* Tags and keywords for discoverability
* FAQ section (optional, with schema-ready structured data)
* Internal link suggestions with anchor text and placement context
* External link suggestions with authority rationale
* Backlink brief — linkable angles, target site types, anchor text ideas, and 2 outreach email drafts
* Featured image prompt (photo-realistic, ready for an image generation API)
* Automatic Yoast SEO and RankMath meta field population

**Duplicate prevention** — before each generation the plugin reads the last 10 post titles in the target category and instructs the model to choose a clearly different topic.

**Fallback model queue** — if the configured model fails, the plugin automatically retries with every other locally available model.

**Supports:**

* Ollama (local or remote endpoint)
* OpenAI DALL-E for featured image generation
* Yoast SEO and RankMath (auto-populated meta fields)

== Installation ==

1. Upload the `ai-scheduled-article-writer` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Navigate to **SEOAutowrite** in the left-hand admin menu.
4. Enter your Ollama endpoint URL and API key.
5. Select the model, choose your target categories, and configure the schedule.
6. Enable the plugin and click **Save Settings**.

== Frequently Asked Questions ==

= Does this plugin work without Ollama? =

No. You need access to an Ollama instance (local or cloud-hosted) with at least one model pulled. The plugin sends prompts to the `/api/generate` endpoint and parses the JSON response.

= Can I use a different AI provider? =

Currently only Ollama is supported for text generation. OpenAI DALL-E is supported for featured image generation. Additional providers may be added in future versions.

= What happens if the model returns invalid JSON? =

The plugin automatically sends a repair prompt to ask the model to fix its own output. If that also fails, you can configure the plugin to either abort the run or create a minimal placeholder draft.

= Will it create duplicate articles? =

The plugin fetches the last 10 post titles from the target category before each run and includes them in the prompt, explicitly instructing the model to choose a different topic and angle.

= Does it support multiple categories? =

Yes. You can select multiple categories and choose between a rotating strategy (cycles through each in order) or a random strategy.

= Is the generated content indexed correctly by SEO plugins? =

Yes. The plugin automatically writes meta title, meta description, and focus keyword to the standard Yoast SEO and RankMath post meta fields.

== Screenshots ==

1. The SEOAutowrite Pro settings page — general, API, schedule, and content configuration.
2. The logs panel showing recent generation runs.
3. An example generated article with full heading structure, FAQ section, and meta data.

== Changelog ==

= 1.0.0 =
* Initial release.
* Ollama text generation with model fallback queue.
* Scheduled article generation via WP-Cron.
* SEO-optimised prompt with E-E-A-T, featured snippets, and backlink brief.
* Duplicate-prevention using last 10 category post titles.
* OpenAI DALL-E featured image generation.
* Yoast SEO and RankMath meta field auto-population.
* FAQ schema support.
* Internal and external link suggestions stored as post meta.

== Upgrade Notice ==

= 1.0.0 =
Initial release — no upgrade steps required.
