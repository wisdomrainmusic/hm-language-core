<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class HMLC_Sync_Variations
{
    private const DENYLIST = [
        '_stock',
        '_stock_status',
        '_manage_stock',
        '_backorders',
        '_edit_lock',
        '_edit_last',
        '_wc_average_rating',
        '_wc_review_count',
        'total_sales',
    ];

    private const DENYLIST_PREFIXES = [
        '_transient_',
        '_oembed_',
        '_wp_old_slug',
    ];

    public function clone_variations(int $source_id, int $new_parent_id): int
    {
        if ($source_id <= 0 || $new_parent_id <= 0) {
            return 0;
        }

        $variations = get_posts([
            'post_type' => 'product_variation',
            'post_parent' => $source_id,
            'numberposts' => -1,
            'post_status' => 'any',
        ]);

        if ($variations === [] || !is_array($variations)) {
            return 0;
        }

        $count = 0;
        foreach ($variations as $variation) {
            if (!$variation instanceof WP_Post) {
                continue;
            }

            $new_variation_id = $this->clone_variation_post($variation, $new_parent_id);
            if ($new_variation_id <= 0) {
                continue;
            }

            $this->copy_variation_meta((int) $variation->ID, $new_variation_id);
            $count++;
        }

        return $count;
    }

    private function clone_variation_post(WP_Post $variation, int $new_parent_id): int
    {
        $data = [
            'post_type' => 'product_variation',
            'post_status' => 'publish',
            'post_parent' => $new_parent_id,
            'menu_order' => $variation->menu_order,
            'post_title' => $variation->post_title,
            'post_content' => $variation->post_content,
            'post_excerpt' => $variation->post_excerpt,
            'post_author' => $variation->post_author,
        ];

        $new_id = wp_insert_post(wp_slash($data), true);
        if (is_wp_error($new_id)) {
            return 0;
        }

        return (int) $new_id;
    }

    private function copy_variation_meta(int $from_id, int $to_id): void
    {
        $meta = get_post_meta($from_id);
        if ($meta === [] || !is_array($meta)) {
            return;
        }

        foreach ($meta as $meta_key => $values) {
            if (!is_string($meta_key) || $meta_key === '') {
                continue;
            }

            if ($this->is_denied_key($meta_key)) {
                continue;
            }

            if (!is_array($values)) {
                $values = [$values];
            }

            foreach ($values as $value) {
                add_post_meta($to_id, $meta_key, $value);
            }
        }
    }

    private function is_denied_key(string $meta_key): bool
    {
        if (in_array($meta_key, self::DENYLIST, true)) {
            return true;
        }

        foreach (self::DENYLIST_PREFIXES as $prefix) {
            if (strpos($meta_key, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }
}
