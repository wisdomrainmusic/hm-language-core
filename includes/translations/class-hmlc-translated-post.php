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

    private HMLC_Translated_Object $translated_object;

    public function __construct(HMLC_Translated_Object $translated_object)
    {
        $this->translated_object = $translated_object;
    }

    public function init(): void
    {
        add_action('init', [$this, 'register_taxonomy'], 5);
        add_action('save_post', [$this, 'maybe_assign_default_language'], 20, 3);
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

    /**
     * @return array<string, int>
     */
    public function get_post_translations(int $post_id): array
    {
        return $this->translated_object->get_translations($post_id);
    }

    /**
     * @param array<string, int> $map
     */
    public function save_post_translations(int $post_id, array $map): void
    {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post) {
            return;
        }

        $this->translated_object->set_translations($post_id, $map, $post->post_type);
    }

    public function get_translation(int $post_id, string $lang_slug): int
    {
        $lang_slug = sanitize_key($lang_slug);
        if ($lang_slug === '') {
            return 0;
        }

        $translations = $this->translated_object->get_translations($post_id);
        return isset($translations[$lang_slug]) ? (int) $translations[$lang_slug] : 0;
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

    public function maybe_assign_default_language(int $post_id, ?WP_Post $post = null, bool $update = false): void
    {
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        if (!$post instanceof WP_Post) {
            $post = get_post($post_id);
            if (!$post instanceof WP_Post) {
                return;
            }
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
