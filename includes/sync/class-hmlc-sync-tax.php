<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class HMLC_Sync_Tax
{
    public function copy_taxonomies(int $from_id, int $to_id): void
    {
        if ($from_id <= 0 || $to_id <= 0) {
            return;
        }

        $post_type = get_post_type($from_id);
        if (!is_string($post_type) || $post_type === '') {
            return;
        }

        $taxonomies = get_object_taxonomies($post_type, 'names');
        if (!is_array($taxonomies)) {
            return;
        }

        foreach ($taxonomies as $taxonomy) {
            if (!is_string($taxonomy) || $taxonomy === '' || $taxonomy === HMLC_Translated_Post::TAXONOMY) {
                continue;
            }

            $term_ids = wp_get_object_terms($from_id, $taxonomy, ['fields' => 'ids']);
            if (is_wp_error($term_ids)) {
                continue;
            }

            if ($term_ids === []) {
                continue;
            }

            wp_set_object_terms($to_id, $term_ids, $taxonomy, false);
        }
    }
}
