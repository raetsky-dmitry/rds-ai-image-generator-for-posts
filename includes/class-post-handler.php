<?php
class AIGFP_Post_Handler
{
	private static $instance = null;

	public static function get_instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct()
	{
		// AJAX обработчики
		add_action('wp_ajax_aigfp_upload_image', array($this, 'handle_image_upload'));
		add_action('wp_ajax_aigfp_set_featured_image', array($this, 'set_featured_image'));
	}

	public function handle_image_upload()
	{
		check_ajax_referer('aigfp_nonce', 'nonce');

		if (!current_user_can('upload_files')) {
			wp_die(__('Insufficient permissions', 'aigfp'));
		}

		$post_id = intval($_POST['post_id']);
		$prompt = sanitize_text_field($_POST['prompt']);

		if (!function_exists('wp_handle_upload')) {
			require_once(ABSPATH . 'wp-admin/includes/file.php');
		}

		$upload = wp_handle_upload($_FILES['image'], array(
			'test_form' => false,
			'action' => 'aigfp_upload_image'
		));

		if (isset($upload['error'])) {
			wp_send_json_error($upload['error']);
		}

		// Создаем запись в медиабиблиотеке
		$attachment = array(
			'post_mime_type' => $upload['type'],
			'post_title' => sprintf(__('AI Generated: %s', 'aigfp'), $prompt),
			'post_content' => __('Generated with Puter.js AI', 'aigfp'),
			'post_excerpt' => __('AI Generated Image', 'aigfp'),
			'post_status' => 'inherit',
			'guid' => $upload['url']
		);

		$attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id);

		if (is_wp_error($attachment_id)) {
			wp_send_json_error($attachment_id->get_error_message());
		}

		// Генерируем метаданные для изображения
		require_once(ABSPATH . 'wp-admin/includes/image.php');
		$attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
		wp_update_attachment_metadata($attachment_id, $attachment_data);

		// Сохраняем промпт в метаданные
		update_post_meta($attachment_id, '_aigfp_prompt', $prompt);
		update_post_meta($attachment_id, '_aigfp_generated', current_time('mysql'));

		wp_send_json_success(array(
			'id' => $attachment_id,
			'url' => $upload['url']
		));
	}

	public function set_featured_image()
	{
		check_ajax_referer('aigfp_nonce', 'nonce');

		$post_id = intval($_POST['post_id']);
		$attachment_id = intval($_POST['attachment_id']);

		if (!current_user_can('edit_post', $post_id)) {
			wp_die(__('Insufficient permissions', 'aigfp'));
		}

		set_post_thumbnail($post_id, $attachment_id);

		// Получаем HTML для миниатюры
		$thumbnail_html = _wp_post_thumbnail_html($attachment_id, $post_id);

		wp_send_json_success(array(
			'html' => $thumbnail_html
		));
	}
}
