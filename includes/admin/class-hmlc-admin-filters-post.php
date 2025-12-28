<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class HMLC_Admin_Filters_Post
{
    private HMLC_Model $model;
    private HMLC_Translated_Post $translated_post;

    public function __construct(HMLC_Model $model, HMLC_Translated_Post $translated_post)
    {
        $this->model = $model;
        $this->translated_post = $translated_post;
    }

    public function init(): void
    {
        add_action('add_meta_boxes', [$this, 'register_language_metabox']);
        add_action('save_post', [$this, 'save_post_language'], 10, 2);
    }

    public function register_language_metabox(): void
    {
        foreach ($this->translated_post->get_supported_post_types() as $post_type) {
            add_meta_box(
                'hmlc_post_language',
                'Language',
                [$this, 'render_language_metabox'],
                $post_type,
                'side',
                'default'
            );
        }
    }

    public function render_language_metabox(WP_Post $post): void
    {
        $languages = $this->model->get_languages();
        $current_language = $this->translated_post->get_post_language($post->ID);

        wp_nonce_field('hmlc_save_post_language', 'hmlc_post_language_nonce');

        if ($languages === []) {
            echo '<p>No languages configured.</p>';
            return;
        }

        echo '<p><label class="screen-reader-text" for="hmlc_post_language">Language</label>';
        echo '<select name="hmlc_post_language" id="hmlc_post_language">';
        foreach ($languages as $language) {
            $selected = selected($current_language, $language->slug, false);
            echo '<option value="' . esc_attr($language->slug) . '"' . $selected . '>' . esc_html($language->name ?: $language->slug) . '</option>';
        }
        echo '</select></p>';
    }

    public function save_post_language(int $post_id, WP_Post $post): void
    {
        if (!isset($_POST['hmlc_post_language_nonce'])) {
            return;
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['hmlc_post_language_nonce'])), 'hmlc_save_post_language')) {
            return;
        }

        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (!in_array($post->post_type, $this->translated_post->get_supported_post_types(), true)) {
            return;
        }

        $language = isset($_POST['hmlc_post_language']) ? sanitize_text_field(wp_unslash((string) $_POST['hmlc_post_language'])) : '';
        if ($language === '') {
            return;
        }

        $this->translated_post->set_post_language($post_id, $language);
    }
}
