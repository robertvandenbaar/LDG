<?php

namespace Ldg;

class Setting
{
    protected static $settingFile = BASE_DIR . '/' . 'settings.json';

    protected static $settings = null;

    protected static $defaultSettings = [
        'image_base_dir' => '/var/www/testfoto/',
        'thumbnail_width' => 120,
        'thumbnail_height' => 80,
        'detail_width' => 1024,
        'full_size_by_default' => false,
        'auto_rotate' => true,
        'supported_extensions' => ['jpg', 'jpeg', 'gif', 'png'],
        'images_per_page' => 200
    ];

    public static function get($key)
    {
        if (self::$settings == null) {
            if (file_exists(self::$settingFile)) {
                self::$settings = json_decode(file_get_contents(self::$settingFile), true);
            } else {
                self::$settings = self::$defaultSettings;
            }
        }

        if (isset(self::$settings[$key])) {
            if ($key == 'image_base_dir') {
                return rtrim(self::$settings[$key], '/');
            }

            return self::$settings[$key];
        }

        return null;
    }

}