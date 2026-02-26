<?php
/**
 * Utility helpers — prompt building, schema validation, defaults.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOAPW_Utils {

	/**
	 * Build the full prompt to send to Ollama.
	 *
	 * @param string $category_name
	 * @param string $category_description
	 * @param array  $options
	 * @return string
	 */
	public static function build_prompt( $category_name, $category_description, array $options, array $recent_titles = array(), $selected_topic = '' ) {
		$min_words   = max( 100, intval( $options['min_words']   ?? 800 ) );
		$max_words   = max( $min_words, intval( $options['max_words'] ?? 1500 ) );
		$tone        = sanitize_text_field( $options['tone']     ?? 'professional' );
		$language    = sanitize_text_field( $options['language'] ?? 'en' );
		$include_faq = ! empty( $options['include_faq'] );
		$selected_topic = sanitize_text_field( $selected_topic );

		$faq_line = $include_faq
			? 'Include a "Frequently Asked Questions" section: wrap it in <section class="faq">, use an <h2>Frequently Asked Questions</h2> heading, then for each item use <h3> for the question and <p> for the answer. Include at least 4 questions.'
			: 'Do not include a FAQ section.';

		if ( ! empty( $recent_titles ) ) {
			$titles_list      = implode( "\n", array_map( static function ( $t ) {
				return '- ' . $t;
			}, $recent_titles ) );
			$existing_content = "=== EXISTING CONTENT (DO NOT DUPLICATE) ===\n\nThe following titles have already been published or drafted in this category. Choose a clearly different topic, angle, and title — do not reuse or closely paraphrase any of these:\n\n{$titles_list}\n";
		} else {
			$existing_content = '';
		}

		$schema = '{
  "title": "string",
  "meta_title": "string (max 60 characters, includes primary keyword)",
  "slug": "string (URL-friendly, lowercase, hyphens only)",
  "excerpt": "string (1-2 sentences, compelling and click-focused)",
  "content_html": "string (full HTML article body)",
  "meta_description": "string (exactly 150-160 characters)",
  "primary_keyword": "string",
  "semantic_keywords": ["string"],
  "keywords": ["string"],
  "tags": ["string"],
  "faq_schema": [
    {
      "question": "string",
      "answer": "string"
    }
  ],
  "internal_link_suggestions": [
    {
      "anchor": "string",
      "topic": "string",
      "placement": "string (recommended section or context)"
    }
  ],
  "external_link_suggestions": [
    {
      "anchor": "string",
      "url": "string",
      "reason": "string (authority relevance)"
    }
  ],
  "backlink_brief": {
    "linkable_angles": ["string"]
  },
  "featured_image_prompt": "string"
}';

		// If the user has provided a custom prompt template, use it with placeholder substitution.
		if ( ! empty( $options['custom_prompt'] ) ) {
			$custom_prompt = str_replace(
				array(
					'{category_name}',
					'{category_description}',
					'{selected_topic}',
					'{category_topic_focus}',
					'{min_words}',
					'{max_words}',
					'{tone}',
					'{language}',
					'{faq_line}',
					'{schema}',
					'{existing_content}',
				),
				array(
					$category_name,
					$category_description,
					$selected_topic,
					$selected_topic,
					$min_words,
					$max_words,
					$tone,
					$language,
					$faq_line,
					$schema,
					$existing_content,
				),
				$options['custom_prompt']
			);

			$custom_prompt .= "\n\n=== HARD REQUIREMENTS (SYSTEM ENFORCED) ===\n";
			$custom_prompt .= "- Topic must be specific and concrete.\n";
			$custom_prompt .= "- Use this selected topic as the article core: {$selected_topic}\n";
			$custom_prompt .= "- Do NOT include an <h1> inside content_html.\n";
			$custom_prompt .= "- Avoid generic category-wide articles.\n";

			return $custom_prompt;
		}

		return "You are an elite SEO content strategist, conversion-focused blog writer, and authority backlink specialist.

Your goal is to create a blog post that is structurally, semantically, and strategically optimized to rank #1 on Google for its primary topic while remaining natural, helpful, and authoritative.

Write a high-quality, original, search-intent-aligned blog article for the following category.

Category name: {$category_name}
Category description: {$category_description}
Selected specific topic: {$selected_topic}

{$existing_content}
Before writing, infer:

- Primary search intent (informational, commercial, transactional, navigational)
- Primary keyword (based on category intent)
- 5-10 semantically related keywords (LSI / entity-based terms)
- A concrete and narrow article angle for the selected topic above

Naturally incorporate them throughout headings and body without keyword stuffing.

=== CONTENT REQUIREMENTS ===

Language: {$language}
Tone: {$tone}
Word count of content_html: between {$min_words} and {$max_words} words

- Demonstrate E-E-A-T (Experience, Expertise, Authoritativeness, Trustworthiness)
- Provide accurate, specific, actionable information
- Use examples, data points, frameworks, or step-by-step guidance when relevant
- Optimize for featured snippets where applicable (definitions, bullet steps, concise explanations)
- Write about the selected specific topic: \"{$selected_topic}\"
- Use concrete tools/platform/version details and practical tradeoffs where relevant

=== HTML STRUCTURE & FORMATTING ===

- Do NOT include an <h1> in content_html (WordPress post_title already renders the title)
- Start with an engaging 2-3 sentence introduction wrapped in a <p> tag - no heading before it
- Divide the body into logical sections, each introduced with an <h2> (include related keywords naturally)
- Use <h3> for sub-points within sections
- Wrap every paragraph in <p> tags — never output bare text outside a tag
- Use <ul> or <ol> (with <li> items) for any list of 3 or more related points or steps
- Use <strong> to highlight key terms or important phrases
- Use <em> for titles, technical terms, or light emphasis
- Keep paragraphs concise: 2–4 sentences each
- Include at least one structured list optimized for snippet capture
- End the article with a dedicated <h2>Conclusion</h2> section that summarises key takeaways, reinforces authority, and ends with a strong actionable tip or call-to-action inside a <p>
- Do NOT use inline styles, <div> wrappers, <span> tags, or any attributes other than class on <section> tags

{$faq_line}

=== SEO OPTIMIZATION RULES ===

- Maintain natural keyword density (avoid stuffing)
- Include the primary keyword in: first 100 words, at least one <h2>, and the Conclusion
- Use semantic variations throughout
- Add short, direct definitions where helpful (for snippet eligibility)
- Ensure readability for Grade 6–9 level
- Avoid fluff, filler, and repetition
- Write with clear topical depth to support topical authority

=== META & DISCOVERABILITY ===

- Provide a meta_title (60 characters max, compelling, includes primary keyword)
- Provide a meta_description that is exactly 150–160 characters
- Provide at least 8 keywords (mix of primary + semantic variations)
- Provide at least 8 tags
- Suggest a clean, SEO-friendly URL slug
- Provide 3 internal link suggestions (with anchor text + topic idea)
- Provide 3 high-authority external link suggestions (with anchor text + type of source)
- Provide FAQ schema-ready questions (if {$faq_line} requires FAQs)

=== BACKLINK STRATEGY BRIEF ===

Provide:
- 5 linkable angles (data-driven, guide, comparison, statistics, expert quotes, etc.)

=== FEATURED IMAGE ===

Provide a simple, general image prompt:
- No text in the image
- Briefly describe the subject and setting in one or two sentences

=== OUTPUT FORMAT ===

Return ONLY a single valid JSON object.
No markdown fences.
No explanations.
No text before or after the JSON.

The JSON must exactly match this structure:

{$schema}";
	}

	/**
	 * Build a prompt for selecting one specific, non-generic topic in a category.
	 *
	 * @param string   $category_name
	 * @param string   $category_description
	 * @param string[] $recent_titles
	 * @param string   $language
	 * @return string
	 */
	public static function build_topic_selection_prompt( $category_name, $category_description, array $recent_titles = array(), $language = 'en' ) {
		$language = sanitize_text_field( $language ?: 'en' );

		if ( ! empty( $recent_titles ) ) {
			$titles_list      = implode( "\n", array_map( static function ( $t ) {
				return '- ' . $t;
			}, $recent_titles ) );
			$existing_content = "Existing titles (avoid overlap):\n{$titles_list}\n";
		} else {
			$existing_content = '';
		}

		return "You are a senior content strategist.

Pick one highly specific blog topic for this category.
Category name: {$category_name}
Category description: {$category_description}
Language: {$language}

{$existing_content}
Rules:
- Choose one narrow, concrete topic (not a broad generic category overview).
- Topic must be actionable and have clear search intent.
- Avoid topics that overlap existing titles.
- Prefer specific platform/tool/version use-cases when relevant.

Return ONLY valid JSON:
{
  \"selected_topic\": \"string\",
  \"primary_keyword\": \"string\",
  \"search_intent\": \"informational|commercial|transactional|navigational\",
  \"why_this_topic\": \"string\"
}";
	}

	/**
	 * Build a repair prompt when the first response was invalid JSON.
	 *
	 * @param string $raw_output The raw string the model returned.
	 * @return string
	 */
	public static function repair_prompt( $raw_output ) {
		return "The text below was supposed to be a valid JSON object matching a specific schema but it is malformed or incomplete.

Fix it and return ONLY the corrected, complete, valid JSON object. No markdown, no code fences, no commentary outside the JSON.

--- BEGIN BROKEN OUTPUT ---
{$raw_output}
--- END BROKEN OUTPUT ---";
	}

	/**
	 * Validate that an array contains all required schema keys.
	 *
	 * @param array $data
	 * @return true|WP_Error
	 */
	public static function validate_schema( array $data ) {
		$required_top = array(
			'title', 'meta_title', 'slug', 'excerpt', 'content_html',
			'meta_description', 'primary_keyword', 'semantic_keywords',
			'keywords', 'tags',
			'internal_link_suggestions', 'external_link_suggestions',
			'backlink_brief', 'featured_image_prompt',
		);

		foreach ( $required_top as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				return new WP_Error( 'missing_key', "Missing required key: {$key}" );
			}
		}

		if ( ! is_array( $data['backlink_brief'] ) ) {
			return new WP_Error( 'invalid_backlink_brief', 'backlink_brief must be an object/array.' );
		}

		foreach ( array( 'linkable_angles' ) as $k ) {
			if ( ! array_key_exists( $k, $data['backlink_brief'] ) ) {
				return new WP_Error( 'missing_backlink_key', "Missing backlink_brief key: {$k}" );
			}
		}

		return true;
	}

	/**
	 * Default plugin options.
	 *
	 * @return array
	 */
	public static function get_default_options() {
		return array(
			// Ollama API
			'ollama_endpoint'                => 'https://ollama.com/api/generate',
			'ollama_api_key'                 => '',
			'ollama_model'                   => 'gpt-oss:120b',
			'ollama_timeout_seconds'         => 600,
			// General
			'enabled'                        => false,
			'on_invalid_json'                => 'abort',
			// Schedule
			'schedule_frequency'             => 'daily',
			'schedule_custom_minutes'        => 1440,
			'schedule_time'                  => '08:00',
			// Content
			'categories'                     => array(),
			'category_strategy'              => 'rotate',
			'post_status'                    => 'draft',
			'author_id'                      => 1,
			'min_words'                      => 800,
			'max_words'                      => 1500,
			'tone'                           => 'professional',
			'language'                       => 'en',
			'include_faq'                    => false,
			// Links
			'insert_internal_links'          => false,
			'max_internal_links'             => 3,
			'include_backlink_brief_in_post' => false,
			// Image
			'image_mode'                     => 'disabled',
			'image_provider'                 => 'none',
			'image_api_key'                  => '',
			'image_model'                    => 'dall-e-3',
			// Logging
			'logging_level'                  => 'info',
			// Custom prompt
			'custom_prompt'                  => '',
		);
	}
}
