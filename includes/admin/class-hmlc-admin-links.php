<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class HMLC_Admin_Links
{
    public function get_edit_post_link_for_translation(int $post_id): string
    {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post || $post->post_status === 'trash') {
            return '';
        }

        $url = get_edit_post_link($post_id, 'raw');
        return is_string($url) ? $url : '';
    }

    public function get_create_translation_link(int $post_id, string $target_lang): string
    {
        $target_lang = sanitize_key($target_lang);
        if ($target_lang === '') {
            return '';
        }

        $url = add_query_arg(
            [
                'post' => $post_id,
                'action' => 'edit',
                'hmlc_create_translation' => $target_lang,
            ],
            admin_url('post.php')
        );

        return wp_nonce_url($url, 'hmlc_create_translation_' . $post_id, 'hmlc_nonce');
    }
}
