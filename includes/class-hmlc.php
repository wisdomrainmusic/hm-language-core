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
        $this->register_post_cleanup_hooks();

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

    private function register_post_cleanup_hooks(): void
    {
        add_action('before_delete_post', [$this, 'cleanup_translation_post']);
        add_action('wp_trash_post', [$this, 'cleanup_translation_post']);
    }

    public function cleanup_translation_post(int $post_id): void
    {
        if ($post_id <= 0) {
            return;
        }

        $post = get_post($post_id);
        if (!$post instanceof WP_Post) {
            return;
        }

        if (!in_array($post->post_type, $this->translated_post->get_supported_post_types(), true)) {
            return;
        }

        $this->translated_object->cleanup_post_id($post_id);
    }
}
