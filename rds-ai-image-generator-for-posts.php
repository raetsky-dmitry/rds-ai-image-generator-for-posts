<?php

/**
 * Plugin Name: RDS AI Image Generator for Posts
 * Description: <?php _e('Generates images for posts using AI via Puter.js', 'aigfp'); ?>
 * Version: 1.0.0
 * Author: RD Studio
 * Text Domain: aigfp
 * Domain Path: /languages
 */

// Защита от прямого доступа
defined('ABSPATH') or die(__('No script kiddies please!', 'aigfp'));

// Загрузка текстового домена
function aigfp_load_textdomain()
{
	load_plugin_textdomain('aigfp', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'aigfp_load_textdomain');

// Константы плагина
define('AIGFP_VERSION', '1.0.0');
define('AIGFP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AIGFP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Автозагрузка классов
spl_autoload_register(function ($class) {
	$prefix = 'AIGFP_';
	$base_dir = AIGFP_PLUGIN_DIR . 'includes/';

	$len = strlen($prefix);
	if (strncmp($prefix, $class, $len) !== 0) {
		return;
	}

	$relative_class = substr($class, $len);
	$file = $base_dir . 'class-' . strtolower(str_replace('_', '-', $relative_class)) . '.php';

	if (file_exists($file)) {
		require $file;
	}
});

// Инициализация плагина
function aigfp_init()
{
	// Проверяем, находимся ли в админке
	if (is_admin()) {
		// Инициализация классов только для админки
		AIGFP_Admin::get_instance();
		AIGFP_Image_Generator::get_instance();
		AIGFP_Post_Handler::get_instance();
	}
}
add_action('plugins_loaded', 'aigfp_init');

// Активация плагина
function aigfp_activate()
{
	// Создание таблиц или опций при необходимости
	update_option('aigfp_version', AIGFP_VERSION);

	// Создаем дефолтные настройки если их нет
	$default_settings = array(
		'default_model' => 'gpt-image-1-mini',
		'default_quality' => 'low',
		'image_style' => 'professional'
	);

	if (!get_option('aigfp_settings')) {
		update_option('aigfp_settings', $default_settings);
	}
}
register_activation_hook(__FILE__, 'aigfp_activate');

// Деактивация плагина
function aigfp_deactivate()
{
	// Очистка временных данных
	// Удаляем CRON задание если оно существует
	$timestamp = wp_next_scheduled('aigfp_auto_generate_images');
	if ($timestamp) {
		wp_unschedule_event($timestamp, 'aigfp_auto_generate_images');
	}
}
register_deactivation_hook(__FILE__, 'aigfp_deactivate');
