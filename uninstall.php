<?php

/**
 * Uninstall AI Image Generator for Posts
 *
 * @package AI Image Generator for Posts
 */

// Если файл вызван не через WordPress, выходим
if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

// Получаем настройки
$settings = get_option('aigfp_settings', array());

// Проверяем, нужно ли удалять данные
if (isset($settings['delete_on_uninstall']) && $settings['delete_on_uninstall']) {
	// Удаляем основные опции
	delete_option('aigfp_settings');
	delete_option('aigfp_version');

	// Здесь можно добавить удаление пользовательских таблиц или метаданных
	// если они используются

	// Пример удаления метаданных постов
	global $wpdb;
	$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_aigfp_%'");
}

// Чистим кэш
wp_cache_flush();
