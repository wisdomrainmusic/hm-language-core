<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class HMLC_Language
{
    public string $slug;
    public string $name;
    public string $locale;
    public bool $rtl = false;
    public string $flag = '';

    public static function from_array(array $data): self
    {
        $slug = isset($data['slug']) ? strtolower(trim((string) $data['slug'])) : '';
        if (!self::is_valid_slug($slug)) {
            throw new InvalidArgumentException('Invalid language slug.');
        }

        $language = new self();
        $language->slug = $slug;
        $language->name = isset($data['name']) ? sanitize_text_field((string) $data['name']) : '';
        $language->locale = isset($data['locale']) ? sanitize_text_field((string) $data['locale']) : '';
        $language->rtl = !empty($data['rtl']);
        $language->flag = isset($data['flag']) ? sanitize_text_field((string) $data['flag']) : '';

        return $language;
    }

    public function to_array(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'locale' => $this->locale,
            'rtl' => $this->rtl,
            'flag' => $this->flag,
        ];
    }

    private static function is_valid_slug(string $slug): bool
    {
        return (bool) preg_match('/^[a-z-]{2,10}$/', $slug);
    }
}
