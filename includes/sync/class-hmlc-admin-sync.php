<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class HMLC_Admin_Sync
{
    private HMLC_Model $model;
    private HMLC_Translated_Post $translated_post;
    private HMLC_Translated_Object $translated_object;
    private HMLC_Sync_Tax $sync_tax;
    private HMLC_Sync_Post_Metas $sync_post_metas;
    private HMLC_Sync_Variations $sync_variations;

    public function __construct(
        HMLC_Model $model,
        HMLC_Translated_Post $translated_post,
        HMLC_Translated_Object $translated_object,
        HMLC_Sync_Tax $sync_tax,
        HMLC_Sync_Post_Metas $sync_post_metas,
        HMLC_Sync_Variations $sync_variations
    ) {
        $this->model = $model;
        $this->translated_post = $translated_post;
        $this->translated_object = $translated_object;
        $this->sync_tax = $sync_tax;
        $this->sync_post_metas = $sync_post_metas;
        $this->sync_variations = $sync_variations;
    }

    public function init(): void
    {
        add_action('admin_init', [$this, 'handle_create_translation']);
        add_action('admin_notices', [$this, 'render_notice']);
    }

    public function handle_create_translation(): void
    {
        if (!is_admin()) {
            return;
        }

        if (!isset($_GET['hmlc_create_translation'])) {
            return;
        }

        $post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
        if ($post_id <= 0) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        check_admin_referer('hmlc_create_translation_' . $post_id, 'hmlc_nonce');

        $target_lang = sanitize_key(wp_unslash((string) $_GET['hmlc_create_translation']));
        if ($target_lang === '') {
            return;
        }

        $languages = $this->model->get_languages();
        if (!isset($languages[$target_lang])) {
            return;
        }

        $post = get_post($post_id);
        if (!$post instanceof WP_Post) {
            return;
        }

        if (!in_array($post->post_type, $this->translated_post->get_supported_post_types(), true)) {
            return;
        }

        $current_lang = $this->translated_post->get_post_language($post_id);
        if ($current_lang === $target_lang) {
            return;
        }

        $existing_id = $this->translated_post->get_translation($post_id, $target_lang);
        if ($existing_id > 0) {
            $this->redirect_to_edit($existing_id);
        }

        $new_id = $this->duplicate_post($post);
        if ($new_id <= 0) {
            return;
        }

        $this->sync_tax->copy_taxonomies($post_id, $new_id);
        $this->sync_post_metas->copy_post_metas($post_id, $new_id);
        $this->copy_featured_image($post_id, $new_id);
        $this->sync_woocommerce_product($post_id, $new_id, $post->post_type);
        $this->translated_post->set_post_language($new_id, $target_lang);
        $this->link_translations($post_id, $new_id, $current_lang, $target_lang, $post->post_type);

        $this->redirect_to_edit($new_id, 'hmlc_translation_created');
    }

    public function render_notice(): void
    {
        $notice = isset($_GET['hmlc_notice']) ? sanitize_text_field(wp_unslash((string) $_GET['hmlc_notice'])) : '';
        if ($notice !== 'hmlc_translation_created') {
            return;
        }

        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Translation created.', 'hmlc') . '</p></div>';
    }

    private function duplicate_post(WP_Post $post): int
    {
        $data = [
            'post_type' => $post->post_type,
            'post_status' => 'draft',
            'post_title' => $post->post_title,
            'post_content' => $post->post_content,
            'post_excerpt' => $post->post_excerpt,
            'post_parent' => $post->post_parent,
            'menu_order' => $post->menu_order,
            'post_author' => $post->post_author,
        ];

        $new_id = wp_insert_post(wp_slash($data), true);
        if (is_wp_error($new_id)) {
            return 0;
        }

        return (int) $new_id;
    }

    private function copy_featured_image(int $from_id, int $to_id): void
    {
        $thumbnail_id = get_post_thumbnail_id($from_id);
        if ($thumbnail_id <= 0) {
            return;
        }

        set_post_thumbnail($to_id, $thumbnail_id);
    }

    private function link_translations(int $source_id, int $new_id, ?string $current_lang, string $target_lang, string $post_type): void
    {
        if ($current_lang !== null && $current_lang !== '') {
            $this->translated_object->link_translation($source_id, $current_lang, $source_id, $post_type);
        }

        $this->translated_object->link_translation($source_id, $target_lang, $new_id, $post_type);
    }

    private function redirect_to_edit(int $post_id, string $notice = ''): void
    {
        $url = get_edit_post_link($post_id, 'raw');
        if (!is_string($url) || $url === '') {
            return;
        }

        if ($notice !== '') {
            $url = add_query_arg('hmlc_notice', $notice, $url);
        }

        wp_safe_redirect($url);
        exit;
    }

    private function sync_woocommerce_product(int $source_id, int $new_id, string $post_type): void
    {
        if ($post_type !== 'product') {
            return;
        }

        if (!function_exists('wc_get_product')) {
            return;
        }

        $product = wc_get_product($source_id);
        if (!$product) {
            return;
        }

        if (!$product->is_type('variable')) {
            return;
        }

        $cloned = $this->sync_variations->clone_variations($source_id, $new_id);
        if ($cloned <= 0) {
            return;
        }

        $new_product = wc_get_product($new_id);
        if ($new_product) {
            $new_product->save();
        }

        if (function_exists('wc_delete_product_transients')) {
            wc_delete_product_transients($new_id);
        }
    }
}
