<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class HMLC_Admin
{
    public function init(): void
    {
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
        echo '<div class="wrap"><h1>Languages</h1><p>Coming soon.</p></div>';
    }
}
