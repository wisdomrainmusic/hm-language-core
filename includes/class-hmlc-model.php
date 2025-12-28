<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class HMLC_Model
{
    private HMLC_Options $options;

    public function __construct(HMLC_Options $options)
    {
        $this->options = $options;
    }

    /**
     * @return array<string, HMLC_Language>
     */
    public function get_languages(): array
    {
        $languages = [];
        foreach ($this->options->get_languages() as $slug => $data) {
            if (!is_array($data)) {
                continue;
            }

            if (!isset($data['slug'])) {
                $data['slug'] = $slug;
            }

            try {
                $language = HMLC_Language::from_array($data);
            } catch (InvalidArgumentException $exception) {
                continue;
            }

            $languages[$language->slug] = $language;
        }

        return $languages;
    }

    public function get_default_language(): ?HMLC_Language
    {
        $slug = $this->options->get_default_language();
        if ($slug === '') {
            return null;
        }

        $languages = $this->get_languages();
        return $languages[$slug] ?? null;
    }

    public function ensure_default_language(): void
    {
        $default = $this->options->get_default_language();
        if ($default !== '') {
            return;
        }

        $languages = $this->get_languages();
        if ($languages === []) {
            return;
        }

        $first = array_key_first($languages);
        if ($first !== null) {
            $this->options->set_default_language($first);
        }
    }
}
