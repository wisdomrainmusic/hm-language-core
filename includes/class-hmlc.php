<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class HMLC
{
    private static ?self $instance = null;

    public HMLC_Options $options;
    public HMLC_Model $model;
    public HMLC_Translated_Object $translated_object;
    public HMLC_Translated_Post $translated_post;

    private function __construct()
    {
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->init();
        }

        return self::$instance;
    }

    public function init(): void
    {
        require_once HMLC_PLUGIN_DIR . '/includes/class-hmlc-language.php';
        require_once HMLC_PLUGIN_DIR . '/includes/class-hmlc-options.php';
        require_once HMLC_PLUGIN_DIR . '/includes/class-hmlc-model.php';
        require_once HMLC_PLUGIN_DIR . '/includes/translations/class-hmlc-translated-object.php';
        require_once HMLC_PLUGIN_DIR . '/includes/translations/class-hmlc-translated-post.php';

        $this->options = new HMLC_Options();
        $this->model = new HMLC_Model($this->options);
        $this->translated_object = new HMLC_Translated_Object();
        $this->translated_post = new HMLC_Translated_Post($this->translated_object);
        $this->translated_post->init();

        if (is_admin()) {
            require_once HMLC_PLUGIN_DIR . '/includes/admin/class-hmlc-admin-base.php';
            require_once HMLC_PLUGIN_DIR . '/includes/admin/class-hmlc-admin.php';

            $admin = new HMLC_Admin();
            $admin->init();
            return;
        }

        require_once HMLC_PLUGIN_DIR . '/includes/frontend/class-hmlc-frontend.php';

        $frontend = new HMLC_Frontend();
        $frontend->init();
    }
}
