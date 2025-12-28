<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class HMLC_Options
{
    public const OPT_LANGUAGES = 'hmlc_languages';
    public const OPT_DEFAULT_LANGUAGE = 'hmlc_default_language';

    public function get_languages(): array
    {
        $languages = get_option(self::OPT_LANGUAGES, []);
        if (!is_array($languages)) {
            return [];
        }

        return $languages;
    }

    public function set_languages(array $languages): void
    {
        update_option(self::OPT_LANGUAGES, $languages, true);
    }

    public function upsert_language(HMLC_Language $lang): void
    {
        $languages = $this->get_languages();
        $languages[$lang->slug] = $lang->to_array();
        $this->set_languages($languages);
    }

    public function delete_language(string $slug): void
    {
        $languages = $this->get_languages();
        if (isset($languages[$slug])) {
            unset($languages[$slug]);
            $this->set_languages($languages);
        }
    }

    public function get_default_language(): string
    {
        $default = get_option(self::OPT_DEFAULT_LANGUAGE, '');
        return is_string($default) ? $default : '';
    }

    public function set_default_language(string $slug): void
    {
        update_option(self::OPT_DEFAULT_LANGUAGE, $slug, true);
    }
}
