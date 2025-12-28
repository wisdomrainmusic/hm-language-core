<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class HMLC_Admin
{
    private HMLC_Options $options;
    private HMLC_Model $model;
    private HMLC_Translated_Post $translated_post;
    private HMLC_Admin_Filters_Post $filters_post;
    private HMLC_Admin_Links $admin_links;

    public function init(): void
    {
        $hmlc = hmlc();
        $this->options = $hmlc->options;
        $this->model = $hmlc->model;
        $this->translated_post = $hmlc->translated_post;

        require_once HMLC_PLUGIN_DIR . '/includes/admin/class-hmlc-admin-links.php';
        $this->admin_links = new HMLC_Admin_Links();

        require_once HMLC_PLUGIN_DIR . '/includes/admin/class-hmlc-admin-filters-post.php';
        $this->filters_post = new HMLC_Admin_Filters_Post($this->model, $this->translated_post, $this->admin_links);
        $this->filters_post->init();

        add_action('admin_menu', [$this, 'register_menu']);
    }

    public function register_menu(): void
    {
        add_menu_page(
            'HM Language Core',
            'HM Language Core',
            'manage_options',
            'hmlc',
            [$this, 'render_main_page'],
            'dashicons-translation',
            58
        );

        add_submenu_page(
            'hmlc',
            'Languages',
            'Languages',
            'manage_options',
            'hmlc-languages',
            [$this, 'render_languages_page']
        );
    }

    public function render_main_page(): void
    {
        echo '<div class="wrap"><h1>HM Language Core</h1><p>Scaffold active. Admin UI placeholder.</p></div>';
    }

    public function render_languages_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $this->handle_languages_actions();
        $this->model->ensure_default_language();

        $languages = $this->model->get_languages();
        $default_slug = $this->options->get_default_language();

        echo '<div class="wrap">';
        echo '<h1>Languages</h1>';

        $this->render_notices();

        echo '<h2>Default language</h2>';
        echo '<form method="post" action="">';
        wp_nonce_field('hmlc_set_default_language');
        echo '<select name="hmlc_default_language">';
        echo '<option value="">Select a language</option>';
        foreach ($languages as $language) {
            $selected = selected($default_slug, $language->slug, false);
            echo '<option value="' . esc_attr($language->slug) . '"' . $selected . '>' . esc_html($language->name ?: $language->slug) . '</option>';
        }
        echo '</select> ';
        submit_button('Save default language', 'primary', 'hmlc_set_default', false);
        echo '</form>';

        echo '<h2>Add language</h2>';
        echo '<form method="post" action="">';
        wp_nonce_field('hmlc_add_language');
        echo '<table class="form-table" role="presentation">';
        echo '<tr><th scope="row"><label for="hmlc_slug">Slug</label></th><td><input name="hmlc_slug" id="hmlc_slug" type="text" class="regular-text" /></td></tr>';
        echo '<tr><th scope="row"><label for="hmlc_name">Name</label></th><td><input name="hmlc_name" id="hmlc_name" type="text" class="regular-text" /></td></tr>';
        echo '<tr><th scope="row"><label for="hmlc_locale">Locale</label></th><td><input name="hmlc_locale" id="hmlc_locale" type="text" class="regular-text" /></td></tr>';
        echo '<tr><th scope="row">RTL</th><td><label><input name="hmlc_rtl" type="checkbox" value="1" /> Right-to-left</label></td></tr>';
        echo '</table>';
        submit_button('Save language', 'primary', 'hmlc_add_language_submit');
        echo '</form>';

        echo '<h2>Quick Add Presets</h2>';
        echo '<form method="post" action="" style="display:inline-block; margin-right:12px;">';
        wp_nonce_field('hmlc_preset_neighbors');
        submit_button('Add Türkiye Neighbors + Kurdish + Persian', 'secondary', 'hmlc_preset_neighbors_submit', false);
        echo '</form>';
        echo '<form method="post" action="" style="display:inline-block;">';
        wp_nonce_field('hmlc_preset_europe');
        submit_button('Add Europe Set', 'secondary', 'hmlc_preset_europe_submit', false);
        echo '</form>';

        echo '<h2>Installed languages</h2>';
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr><th>Slug</th><th>Name</th><th>Locale</th><th>RTL</th><th>Actions</th></tr></thead>';
        echo '<tbody>';
        if ($languages === []) {
            echo '<tr><td colspan="5">No languages configured.</td></tr>';
        } else {
            foreach ($languages as $language) {
                echo '<tr>';
                echo '<td>' . esc_html($language->slug) . '</td>';
                echo '<td>' . esc_html($language->name) . '</td>';
                echo '<td>' . esc_html($language->locale) . '</td>';
                echo '<td>' . ($language->rtl ? 'Yes' : 'No') . '</td>';
                echo '<td>';
                if ($language->slug === $default_slug) {
                    echo '<em>Default language</em>';
                } else {
                    echo '<form method="post" action="" style="display:inline;">';
                    wp_nonce_field('hmlc_delete_language_' . $language->slug);
                    echo '<input type="hidden" name="hmlc_delete_slug" value="' . esc_attr($language->slug) . '" />';
                    submit_button('Delete', 'link-delete', 'hmlc_delete_language_submit', false);
                    echo '</form>';
                }
                echo '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody>';
        echo '</table>';

        echo '</div>';
    }

    private function handle_languages_actions(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        if (isset($_POST['hmlc_set_default'])) {
            check_admin_referer('hmlc_set_default_language');
            $slug = isset($_POST['hmlc_default_language']) ? sanitize_text_field(wp_unslash((string) $_POST['hmlc_default_language'])) : '';
            if ($slug === '') {
                $this->redirect_with_notice('default_missing');
            }

            $languages = $this->model->get_languages();
            if (!isset($languages[$slug])) {
                $this->redirect_with_notice('default_missing');
            }

            $this->options->set_default_language($slug);
            $this->redirect_with_notice('default_saved');
        }

        if (isset($_POST['hmlc_add_language_submit'])) {
            check_admin_referer('hmlc_add_language');
            $data = [
                'slug' => isset($_POST['hmlc_slug']) ? sanitize_text_field(wp_unslash((string) $_POST['hmlc_slug'])) : '',
                'name' => isset($_POST['hmlc_name']) ? sanitize_text_field(wp_unslash((string) $_POST['hmlc_name'])) : '',
                'locale' => isset($_POST['hmlc_locale']) ? sanitize_text_field(wp_unslash((string) $_POST['hmlc_locale'])) : '',
                'rtl' => !empty($_POST['hmlc_rtl']),
            ];

            try {
                $language = HMLC_Language::from_array($data);
            } catch (InvalidArgumentException $exception) {
                $this->redirect_with_notice('language_invalid');
            }

            $this->options->upsert_language($language);
            $this->redirect_with_notice('language_saved');
        }

        if (isset($_POST['hmlc_delete_language_submit'])) {
            $slug = isset($_POST['hmlc_delete_slug']) ? sanitize_text_field(wp_unslash((string) $_POST['hmlc_delete_slug'])) : '';
            check_admin_referer('hmlc_delete_language_' . $slug);

            if ($slug === '') {
                $this->redirect_with_notice('language_invalid');
            }

            if ($slug === $this->options->get_default_language()) {
                $this->redirect_with_notice('default_protected');
            }

            $this->options->delete_language($slug);
            $this->redirect_with_notice('language_deleted');
        }

        if (isset($_POST['hmlc_preset_neighbors_submit'])) {
            check_admin_referer('hmlc_preset_neighbors');
            $this->apply_presets($this->get_preset_neighbors());
            $this->redirect_with_notice('preset_added');
        }

        if (isset($_POST['hmlc_preset_europe_submit'])) {
            check_admin_referer('hmlc_preset_europe');
            $this->apply_presets($this->get_preset_europe());
            $this->redirect_with_notice('preset_added');
        }
    }

    /**
     * @param HMLC_Language[] $languages
     */
    private function apply_presets(array $languages): void
    {
        foreach ($languages as $language) {
            $this->options->upsert_language($language);
        }
    }

    /**
     * @return HMLC_Language[]
     */
    private function get_preset_neighbors(): array
    {
        return [
            HMLC_Language::from_array(['slug' => 'tr', 'name' => 'Turkish', 'locale' => 'tr_TR']),
            HMLC_Language::from_array(['slug' => 'en', 'name' => 'English', 'locale' => 'en_US']),
            HMLC_Language::from_array(['slug' => 'el', 'name' => 'Greek', 'locale' => 'el_GR']),
            HMLC_Language::from_array(['slug' => 'bg', 'name' => 'Bulgarian', 'locale' => 'bg_BG']),
            HMLC_Language::from_array(['slug' => 'ro', 'name' => 'Romanian', 'locale' => 'ro_RO']),
            HMLC_Language::from_array(['slug' => 'ka', 'name' => 'Georgian', 'locale' => 'ka_GE']),
            HMLC_Language::from_array(['slug' => 'hy', 'name' => 'Armenian', 'locale' => 'hy_AM']),
            HMLC_Language::from_array(['slug' => 'az', 'name' => 'Azerbaijani', 'locale' => 'az_AZ']),
            HMLC_Language::from_array(['slug' => 'ar', 'name' => 'Arabic', 'locale' => 'ar', 'rtl' => true]),
            HMLC_Language::from_array(['slug' => 'fa', 'name' => 'Persian', 'locale' => 'fa_IR', 'rtl' => true]),
            HMLC_Language::from_array(['slug' => 'ku', 'name' => 'Kurdish', 'locale' => 'ku']),
            HMLC_Language::from_array(['slug' => 'ru', 'name' => 'Russian', 'locale' => 'ru_RU']),
        ];
    }

    /**
     * @return HMLC_Language[]
     */
    private function get_preset_europe(): array
    {
        return [
            HMLC_Language::from_array(['slug' => 'de', 'name' => 'German', 'locale' => 'de_DE']),
            HMLC_Language::from_array(['slug' => 'fr', 'name' => 'French', 'locale' => 'fr_FR']),
            HMLC_Language::from_array(['slug' => 'it', 'name' => 'Italian', 'locale' => 'it_IT']),
            HMLC_Language::from_array(['slug' => 'es', 'name' => 'Spanish', 'locale' => 'es_ES']),
            HMLC_Language::from_array(['slug' => 'pt', 'name' => 'Portuguese', 'locale' => 'pt_PT']),
            HMLC_Language::from_array(['slug' => 'nl', 'name' => 'Dutch', 'locale' => 'nl_NL']),
            HMLC_Language::from_array(['slug' => 'sv', 'name' => 'Swedish', 'locale' => 'sv_SE']),
            HMLC_Language::from_array(['slug' => 'no', 'name' => 'Norwegian', 'locale' => 'nb_NO']),
            HMLC_Language::from_array(['slug' => 'da', 'name' => 'Danish', 'locale' => 'da_DK']),
            HMLC_Language::from_array(['slug' => 'fi', 'name' => 'Finnish', 'locale' => 'fi_FI']),
            HMLC_Language::from_array(['slug' => 'pl', 'name' => 'Polish', 'locale' => 'pl_PL']),
            HMLC_Language::from_array(['slug' => 'cs', 'name' => 'Czech', 'locale' => 'cs_CZ']),
            HMLC_Language::from_array(['slug' => 'sk', 'name' => 'Slovak', 'locale' => 'sk_SK']),
            HMLC_Language::from_array(['slug' => 'hu', 'name' => 'Hungarian', 'locale' => 'hu_HU']),
            HMLC_Language::from_array(['slug' => 'sl', 'name' => 'Slovenian', 'locale' => 'sl_SI']),
            HMLC_Language::from_array(['slug' => 'hr', 'name' => 'Croatian', 'locale' => 'hr_HR']),
            HMLC_Language::from_array(['slug' => 'sr', 'name' => 'Serbian', 'locale' => 'sr_RS']),
            HMLC_Language::from_array(['slug' => 'bs', 'name' => 'Bosnian', 'locale' => 'bs_BA']),
            HMLC_Language::from_array(['slug' => 'sq', 'name' => 'Albanian', 'locale' => 'sq_AL']),
            HMLC_Language::from_array(['slug' => 'mk', 'name' => 'Macedonian', 'locale' => 'mk_MK']),
            HMLC_Language::from_array(['slug' => 'uk', 'name' => 'Ukrainian', 'locale' => 'uk']),
            HMLC_Language::from_array(['slug' => 'lt', 'name' => 'Lithuanian', 'locale' => 'lt_LT']),
            HMLC_Language::from_array(['slug' => 'lv', 'name' => 'Latvian', 'locale' => 'lv_LV']),
            HMLC_Language::from_array(['slug' => 'et', 'name' => 'Estonian', 'locale' => 'et']),
            HMLC_Language::from_array(['slug' => 'is', 'name' => 'Icelandic', 'locale' => 'is_IS']),
        ];
    }

    private function render_notices(): void
    {
        $notice = isset($_GET['hmlc_notice']) ? sanitize_text_field(wp_unslash((string) $_GET['hmlc_notice'])) : '';
        if ($notice === '') {
            return;
        }

        $notices = [
            'default_saved' => ['type' => 'success', 'message' => 'Default language saved.'],
            'default_missing' => ['type' => 'error', 'message' => 'Select a valid default language.'],
            'language_saved' => ['type' => 'success', 'message' => 'Language saved.'],
            'language_deleted' => ['type' => 'success', 'message' => 'Language deleted.'],
            'language_invalid' => ['type' => 'error', 'message' => 'Invalid language data.'],
            'default_protected' => ['type' => 'error', 'message' => 'Default language cannot be deleted.'],
            'preset_added' => ['type' => 'success', 'message' => 'Preset languages added.'],
        ];

        if (!isset($notices[$notice])) {
            return;
        }

        $data = $notices[$notice];
        $class = $data['type'] === 'error' ? 'notice notice-error' : 'notice notice-success';
        echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($data['message']) . '</p></div>';
    }

    private function redirect_with_notice(string $notice): void
    {
        $url = add_query_arg('hmlc_notice', $notice, admin_url('admin.php?page=hmlc-languages'));
        wp_safe_redirect($url);
        exit;
    }
}
