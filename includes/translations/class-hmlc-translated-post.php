<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class HMLC_Translated_Post
{
    public const TAXONOMY = 'hmlc_language';

    private const POST_TYPES = [
        'post',
        'page',
        'product',
        'product_variation',
    ];

    public function init(): void
    {
        add_action('init', [$this, 'register_taxonomy'], 5);
        add_action('save_post', [$this, 'maybe_assign_default_language'], 20, 2);
    }

    /**
     * @return string[]
     */
    public function get_supported_post_types(): array
    {
        return self::POST_TYPES;
    }

    public function register_taxonomy(): void
    {
        register_taxonomy(
            self::TAXONOMY,
            self::POST_TYPES,
            [
                'label' => 'Language',
                'public' => false,
                'show_ui' => false,
                'show_admin_column' => false,
                'hierarchical' => false,
                'rewrite' => false,
                'query_var' => false,
            ]
        );
    }

    public function set_post_language(int $post_id, string $lang_slug): void
    {
        $lang_slug = sanitize_key($lang_slug);
        if ($lang_slug === '') {
            return;
        }

        $this->ensure_language_term($lang_slug);
        wp_set_object_terms($post_id, [$lang_slug], self::TAXONOMY, false);
    }

    public function get_post_language(int $post_id): ?string
    {
        $terms = wp_get_object_terms($post_id, self::TAXONOMY, ['fields' => 'slugs']);
        if (is_wp_error($terms) || $terms === []) {
            return null;
        }

        $slug = reset($terms);
        return $slug === false ? null : (string) $slug;
    }

    private function maybe_assign_default_language(int $post_id, WP_Post $post): void
    {
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        if (!in_array($post->post_type, self::POST_TYPES, true)) {
            return;
        }

        if ($this->get_post_language($post_id) !== null) {
            return;
        }

        $default = hmlc()->model->get_default_language();
        if ($default === null) {
            return;
        }

        $this->set_post_language($post_id, $default->slug);
    }

    private function ensure_language_term(string $lang_slug): void
    {
        $existing = get_term_by('slug', $lang_slug, self::TAXONOMY);
        if ($existing instanceof WP_Term) {
            return;
        }

        $language = hmlc()->model->get_languages()[$lang_slug] ?? null;
        $name = $language ? $language->name : $lang_slug;

        wp_insert_term($name, self::TAXONOMY, ['slug' => $lang_slug]);
    }
}
