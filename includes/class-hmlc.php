<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class HMLC
{
    private static ?self $instance = null;

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
    }
}
