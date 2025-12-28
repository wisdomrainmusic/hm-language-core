<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class HMLC_Sync_Post_Metas
{
    private const DENYLIST = [
        '_edit_lock',
        '_edit_last',
        '_sku',
        '_wc_average_rating',
        '_wc_review_count',
        'total_sales',
    ];

    private const DENYLIST_PREFIXES = [
        '_transient_',
        '_oembed_',
        '_wp_old_slug',
    ];

    public function copy_post_metas(int $from_id, int $to_id): void
    {
        if ($from_id <= 0 || $to_id <= 0) {
            return;
        }

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

        delete_post_meta($to_id, '_sku');
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
