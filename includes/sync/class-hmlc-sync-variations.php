<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class HMLC_Sync_Variations
{
    private const DENIED_KEYS = [
        '_stock',
        '_stock_status',
        '_manage_stock',
        '_backorders',
        '_sku',
        '_edit_lock',
        '_edit_last',
        '_wc_average_rating',
        '_wc_review_count',
        'total_sales',
    ];

    private const DENIED_PREFIXES = [
        '_transient_',
        '_oembed_',
        '_wp_old_slug',
    ];

    public function clone_variations(int $source_product_id, int $new_product_id): int
    {
        if ($source_product_id <= 0 || $new_product_id <= 0) {
            return 0;
        }

        if (!function_exists('wc_get_product')) {
            return 0;
        }

        $source_product = wc_get_product($source_product_id);
        if (!$source_product || !$source_product->is_type('variable')) {
            return 0;
        }

        $variation_ids = $source_product->get_children();
        if ($variation_ids === [] || !is_array($variation_ids)) {
            return 0;
        }

        $count = 0;
        foreach ($variation_ids as $variation_id) {
            $variation_id = (int) $variation_id;
            if ($variation_id <= 0) {
                continue;
            }

            $variation_post = get_post($variation_id);
            if (!$variation_post instanceof WP_Post) {
                continue;
            }

            $new_variation_id = $this->clone_variation_post($variation_post, $new_product_id);
            if ($new_variation_id <= 0) {
                continue;
            }

            $this->copy_meta($variation_id, $new_variation_id);
            $count++;
        }

        if (function_exists('wc_delete_product_transients')) {
            wc_delete_product_transients($new_product_id);
        }

        return $count;
    }

    private function clone_variation_post(WP_Post $variation, int $new_parent_id): int
    {
        $data = [
            'post_type' => 'product_variation',
            'post_status' => $variation->post_status,
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

    private function copy_meta(int $from_id, int $to_id): void
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

            delete_post_meta($to_id, $meta_key);
            foreach ($values as $value) {
                add_post_meta($to_id, $meta_key, $value);
            }
        }
    }

    private function is_denied_key(string $meta_key): bool
    {
        if (in_array($meta_key, self::DENIED_KEYS, true)) {
            return true;
        }

        foreach (self::DENIED_PREFIXES as $prefix) {
            if (strpos($meta_key, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }
}
