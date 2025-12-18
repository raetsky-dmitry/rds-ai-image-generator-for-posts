<?php
class AIGFP_Image_Generator
{
	private static $instance = null;

	// Настройки по умолчанию
	private $default_settings = array(
		'model' => 'gpt-image-1',
		'quality' => 'medium',
		'image_style' => 'professional',
		'aspect_ratio' => '4:3'
	);

	public static function get_instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct()
	{
		// Загружаем настройки
		$this->settings = wp_parse_args(
			get_option('aigfp_settings', array()),
			$this->default_settings
		);

		// AJAX для пакетной генерации
		add_action('wp_ajax_aigfp_batch_generate', array($this, 'batch_generate_images'));
	}

	/**
	 * Генерация промпта на основе контента поста
	 */
	public function generate_prompt($post_id)
	{
		$post = get_post($post_id);

		if (!$post) {
			return false;
		}

		$title = get_the_title($post);
		$content = wp_strip_all_tags($post->post_content);
		$categories = $this->get_post_categories($post_id);

		// Извлекаем ключевые слова
		$keywords = $this->extract_keywords($content);

		// Определяем стиль изображения
		$style = $this->get_image_style_prompt();

		// Определяем аспект/композицию
		$composition = $this->get_composition_prompt();

		// Собираем промпт
		$prompt = sprintf(__('Professional blog post featured image for article titled: "%s".', 'aigfp'), $title);

		if (!empty($keywords)) {
			$prompt .= ' ' . sprintf(__('Main themes and keywords: %s.', 'aigfp'), implode(', ', array_slice($keywords, 0, 7)));
		}

		if (!empty($categories)) {
			$prompt .= ' ' . sprintf(__('Categories: %s.', 'aigfp'), implode(', ', $categories));
		}

		$prompt .= ' ' . sprintf(__('Style: %s.', 'aigfp'), $style);
		$prompt .= ' ' . sprintf(__('Composition: %s.', 'aigfp'), $composition);
		$prompt .= ' ' . __('No text or logos on the image.', 'aigfp');
		$prompt .= ' ' . __('High quality, detailed, visually appealing.', 'aigfp');

		return apply_filters('aigfp_prompt', $prompt, $post_id);
	}

	/**
	 * Извлечение ключевых слов из текста
	 */
	private function extract_keywords($text, $limit = 10)
	{
		// Очищаем текст
		$text = strtolower($text);
		$text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);

		// Разбиваем на слова
		$words = str_word_count($text, 1, 'абвгдеёжзийклмнопрстуфхцчшщъыьэюяabcdefghijklmnopqrstuvwxyz0123456789');

		// Список стоп-слов (можно расширить)
		$stop_words = array(
			'the',
			'and',
			'is',
			'in',
			'to',
			'of',
			'a',
			'that',
			'it',
			'with',
			'for',
			'as',
			'on',
			'by',
			'this',
			'are',
			'be',
			'at',
			'from',
			'or',
			'an',
			'but',
			'not',
			'if',
			'so',
			'was',
			'what',
			'и',
			'в',
			'не',
			'на',
			'я',
			'он',
			'с',
			'что',
			'а',
			'по',
			'как',
			'но',
			'за',
			'то',
			'от',
			'из',
			'у',
			'к',
			'же',
			'мы',
			'вы',
			'о',
			'до',
			'бы',
			'для',
			'да',
			'во',
			'со'
		);

		// Убираем стоп-слова и короткие слова
		$words = array_filter($words, function ($word) use ($stop_words) {
			return !in_array($word, $stop_words) && strlen($word) > 2;
		});

		// Считаем частоту
		$word_count = array_count_values($words);
		arsort($word_count);

		// Берем топ-N слов
		$keywords = array_keys(array_slice($word_count, 0, $limit));

		return $keywords;
	}

	/**
	 * Получение категорий поста
	 */
	private function get_post_categories($post_id)
	{
		$categories = get_the_category($post_id);
		$category_names = array();

		foreach ($categories as $category) {
			$category_names[] = $category->name;
		}

		return $category_names;
	}

	/**
	 * Получение тегов поста
	 */
	private function get_post_tags($post_id)
	{
		$tags = get_the_tags($post_id);
		$tag_names = array();

		if ($tags) {
			foreach ($tags as $tag) {
				$tag_names[] = $tag->name;
			}
		}

		return $tag_names;
	}

	/**
	 * Промпт для стиля изображения
	 */
	private function get_image_style_prompt()
	{
		$style = isset($this->settings['image_style']) ? $this->settings['image_style'] : 'professional';

		$style_prompts = array(
			'professional' => __('professional, clean, modern, corporate, suitable for business blog', 'aigfp'),
			'minimalist' => __('minimalist, simple, clean lines, lots of negative space', 'aigfp'),
			'realistic' => __('photorealistic, detailed, realistic lighting and textures', 'aigfp'),
			'illustrated' => __('illustrated, vector art, flat design, colorful', 'aigfp'),
			'artistic' => __('artistic, painterly, creative, expressive brush strokes', 'aigfp'),
			'vintage' => __('vintage, retro, nostalgic, film grain effect', 'aigfp'),
			'futuristic' => __('futuristic, sci-fi, cyberpunk, neon lights, glowing elements', 'aigfp')
		);

		return isset($style_prompts[$style]) ? $style_prompts[$style] : $style_prompts['professional'];
	}

	/**
	 * Промпт для композиции
	 */
	private function get_composition_prompt()
	{
		$ratio = isset($this->settings['aspect_ratio']) ? $this->settings['aspect_ratio'] : '1:1';

		$ratio_prompts = array(
			'1:1' => __('square composition, centered subject', 'aigfp'),
			'16:9' => __('wide landscape composition, cinematic', 'aigfp'),
			'4:3' => __('standard photo composition, balanced', 'aigfp'),
			'9:16' => __('vertical portrait composition, mobile-friendly', 'aigfp'),
			'2:1' => __('panoramic composition, wide angle', 'aigfp')
		);

		return isset($ratio_prompts[$ratio]) ? $ratio_prompts[$ratio] : $ratio_prompts['1:1'];
	}

	/**
	 * Пакетная генерация изображений для нескольких постов
	 */
	public function batch_generate_images()
	{
		check_ajax_referer('aigfp_nonce', 'nonce');

		if (!current_user_can('edit_posts')) {
			wp_send_json_error(__('Insufficient permissions', 'aigfp'));
		}

		$post_ids = isset($_POST['post_ids']) ? array_map('intval', $_POST['post_ids']) : array();
		$model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : $this->settings['model'];
		$quality = isset($_POST['quality']) ? sanitize_text_field($_POST['quality']) : $this->settings['quality'];

		$results = array();
		$errors = array();

		foreach ($post_ids as $post_id) {
			if (!$this->has_featured_image($post_id)) {
				$prompt = $this->generate_prompt($post_id);

				if ($prompt) {
					$results[] = array(
						'post_id' => $post_id,
						'title' => get_the_title($post_id),
						'prompt' => $prompt,
						'status' => 'pending'
					);
				} else {
					$errors[] = sprintf(__('Failed to generate prompt for post #%d', 'aigfp'), $post_id);
				}
			}
		}

		wp_send_json_success(array(
			'total' => count($post_ids),
			'to_generate' => count($results),
			'results' => $results,
			'errors' => $errors
		));
	}

	/**
	 * Проверка наличия миниатюры
	 */
	private function has_featured_image($post_id)
	{
		return has_post_thumbnail($post_id);
	}
}
