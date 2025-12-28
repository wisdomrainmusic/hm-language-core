<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class HMLC_Translated_Object
{
    private const OPT_GROUPS = 'hmlc_translation_groups';
    private const OPT_OBJECT_TO_GROUP = 'hmlc_post_to_group';

    public function get_group_id_for_object(int $object_id): ?string
    {
        $map = $this->get_object_map();
        $group_id = $map[$object_id] ?? null;

        if (!is_string($group_id) || $group_id === '') {
            return null;
        }

        return $group_id;
    }

    /**
     * @return array<string, int>
     */
    public function get_translations(int $object_id): array
    {
        $group_id = $this->get_group_id_for_object($object_id);
        if ($group_id === null) {
            return [];
        }

        $groups = $this->get_groups();
        $group = $groups[$group_id] ?? null;
        if (!is_array($group) || !isset($group['translations']) || !is_array($group['translations'])) {
            return [];
        }

        $translations = [];
        foreach ($group['translations'] as $lang => $translated_id) {
            $lang = sanitize_key((string) $lang);
            $translated_id = (int) $translated_id;
            if ($lang === '' || $translated_id <= 0) {
                continue;
            }

            $translations[$lang] = $translated_id;
        }

        return $translations;
    }

    /**
     * @param array<string, int> $map
     */
    public function set_translations(int $object_id, array $map, string $post_type): void
    {
        $group_id = $this->ensure_group($object_id, $post_type);
        $groups = $this->get_groups();
        $group = $groups[$group_id] ?? ['post_type' => $post_type, 'translations' => []];

        $sanitized = $this->sanitize_translation_map($map);
        $previous_ids = $this->extract_translation_ids($group['translations'] ?? []);

        $group['post_type'] = $post_type;
        $group['translations'] = $sanitized;
        $groups[$group_id] = $group;
        $this->update_groups($groups);

        $object_map = $this->get_object_map();
        $current_ids = array_values($sanitized);
        $all_ids = array_unique(array_merge($previous_ids, $current_ids));

        foreach ($all_ids as $translated_id) {
            if ($translated_id <= 0) {
                continue;
            }

            if (in_array($translated_id, $current_ids, true)) {
                $object_map[$translated_id] = $group_id;
            } elseif (($object_map[$translated_id] ?? null) === $group_id) {
                unset($object_map[$translated_id]);
            }
        }

        $object_map[$object_id] = $group_id;
        $this->update_object_map($object_map);
    }

    public function link_translation(int $object_id, string $lang_slug, int $translated_object_id, string $post_type): void
    {
        $lang_slug = sanitize_key($lang_slug);
        if ($lang_slug === '' || $translated_object_id <= 0) {
            return;
        }

        $group_id = $this->ensure_group($object_id, $post_type);
        $groups = $this->get_groups();
        $group = $groups[$group_id] ?? ['post_type' => $post_type, 'translations' => []];
        $translations = is_array($group['translations'] ?? null) ? $group['translations'] : [];

        $previous_id = isset($translations[$lang_slug]) ? (int) $translations[$lang_slug] : 0;
        $translations[$lang_slug] = $translated_object_id;

        $group['post_type'] = $post_type;
        $group['translations'] = $this->sanitize_translation_map($translations);
        $groups[$group_id] = $group;
        $this->update_groups($groups);

        $object_map = $this->get_object_map();
        $object_map[$object_id] = $group_id;
        $object_map[$translated_object_id] = $group_id;

        if ($previous_id > 0 && $previous_id !== $translated_object_id) {
            $still_linked = in_array($previous_id, array_values($group['translations']), true);
            if (!$still_linked && ($object_map[$previous_id] ?? null) === $group_id) {
                unset($object_map[$previous_id]);
            }
        }

        $this->update_object_map($object_map);
    }

    public function cleanup_missing_posts_in_group(string $group_id): void
    {
        if ($group_id === '') {
            return;
        }

        $groups = $this->get_groups();
        $object_map = $this->get_object_map();
        $group = $groups[$group_id] ?? null;

        if (!is_array($group)) {
            $updated = false;
            foreach ($object_map as $post_id => $mapped_group) {
                if ($mapped_group === $group_id) {
                    unset($object_map[$post_id]);
                    $updated = true;
                }
            }

            if ($updated) {
                $this->update_object_map($object_map);
            }

            return;
        }

        $translations = is_array($group['translations'] ?? null) ? $group['translations'] : [];
        $changed = false;

        foreach ($translations as $lang => $translated_id) {
            $translated_id = (int) $translated_id;
            if ($translated_id <= 0 || $this->is_valid_translation_post_id($translated_id)) {
                continue;
            }

            unset($translations[$lang]);
            if (($object_map[$translated_id] ?? null) === $group_id) {
                unset($object_map[$translated_id]);
            }
            $changed = true;
        }

        if ($changed) {
            $group['translations'] = $this->sanitize_translation_map($translations);
            $groups[$group_id] = $group;
            $this->update_groups($groups);
            $this->update_object_map($object_map);
        }

        if (!in_array($group_id, $object_map, true)) {
            unset($groups[$group_id]);
            $this->update_groups($groups);
        }
    }

    public function cleanup_post_id(int $post_id): void
    {
        if ($post_id <= 0) {
            return;
        }

        $group_id = $this->get_group_id_for_object($post_id);
        if ($group_id === null) {
            return;
        }

        $groups = $this->get_groups();
        $group = $groups[$group_id] ?? null;

        if (is_array($group)) {
            $translations = is_array($group['translations'] ?? null) ? $group['translations'] : [];
            $changed = false;

            foreach ($translations as $lang => $translated_id) {
                if ((int) $translated_id !== $post_id) {
                    continue;
                }

                unset($translations[$lang]);
                $changed = true;
            }

            if ($changed) {
                $group['translations'] = $this->sanitize_translation_map($translations);
                $groups[$group_id] = $group;
                $this->update_groups($groups);
            }
        }

        $object_map = $this->get_object_map();
        if (($object_map[$post_id] ?? null) === $group_id) {
            unset($object_map[$post_id]);
            $this->update_object_map($object_map);
        }

        $this->cleanup_missing_posts_in_group($group_id);
    }

    public function is_valid_translation_post_id(int $post_id): bool
    {
        if ($post_id <= 0) {
            return false;
        }

        $post = get_post($post_id);
        if (!$post instanceof WP_Post) {
            return false;
        }

        return $post->post_status !== 'trash';
    }

    public function ensure_group(int $object_id, string $post_type): string
    {
        $map = $this->get_object_map();
        $group_id = $map[$object_id] ?? null;

        $groups = $this->get_groups();
        if (is_string($group_id) && $group_id !== '' && isset($groups[$group_id])) {
            return $group_id;
        }

        $group_id = wp_generate_uuid4();
        $groups[$group_id] = [
            'post_type' => $post_type,
            'translations' => [],
        ];
        $this->update_groups($groups);

        $map[$object_id] = $group_id;
        $this->update_object_map($map);

        return $group_id;
    }

    private function get_groups(): array
    {
        $data = get_option(self::OPT_GROUPS, ['groups' => []]);
        if (!is_array($data)) {
            return [];
        }

        $groups = $data['groups'] ?? [];
        return is_array($groups) ? $groups : [];
    }

    private function update_groups(array $groups): void
    {
        update_option(self::OPT_GROUPS, ['groups' => $groups], false);
    }

    private function get_object_map(): array
    {
        $map = get_option(self::OPT_OBJECT_TO_GROUP, []);
        return is_array($map) ? $map : [];
    }

    private function update_object_map(array $map): void
    {
        update_option(self::OPT_OBJECT_TO_GROUP, $map, false);
    }

    /**
     * @param array<string, int> $map
     * @return array<string, int>
     */
    private function sanitize_translation_map(array $map): array
    {
        $sanitized = [];
        foreach ($map as $lang => $translated_id) {
            $lang = sanitize_key((string) $lang);
            $translated_id = (int) $translated_id;
            if ($lang === '' || $translated_id <= 0) {
                continue;
            }

            $sanitized[$lang] = $translated_id;
        }

        return $sanitized;
    }

    /**
     * @param array<string, int> $map
     * @return int[]
     */
    private function extract_translation_ids(array $map): array
    {
        $ids = [];
        foreach ($map as $translated_id) {
            $translated_id = (int) $translated_id;
            if ($translated_id > 0) {
                $ids[] = $translated_id;
            }
        }

        return $ids;
    }
}
