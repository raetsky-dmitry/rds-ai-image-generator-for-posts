<?php
class AIGFP_Admin
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
		add_action('admin_menu', array($this, 'add_admin_menu'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
		add_action('add_meta_boxes', array($this, 'add_meta_box'));
		add_action('admin_footer', array($this, 'add_puter_sdk'));
		add_action('admin_init', array($this, 'register_settings'));

		add_filter(
			'plugin_action_links_' . plugin_basename(AIGFP_PLUGIN_DIR . 'rds-ai-image-generator-for-posts.php'),
			array($this, 'add_plugin_action_links')
		);
	}

	public function add_plugin_action_links($links)
	{
		$settings_link = '<a href="' . admin_url('options-general.php?page=ai-image-generator') . '">' . __('Settings', 'aigfp') . '</a>';
		array_unshift($links, $settings_link);
		return $links;
	}

	public function add_admin_menu()
	{
		// Основная страница настроек в разделе "Настройки"
		$hook_suffix = add_options_page(
			__('AI Image Generator Settings', 'aigfp'),
			__('AI Image Generator', 'aigfp'),
			'manage_options',
			'ai-image-generator',
			array($this, 'render_settings_page')
		);

		// Добавляем обработку загрузки скриптов для страницы настроек
		add_action("admin_print_scripts-{$hook_suffix}", array($this, 'enqueue_settings_scripts'));
	}

	public function enqueue_settings_scripts()
	{
		// Загружаем стили для страницы настроек
		wp_enqueue_style(
			'aigfp-settings',
			AIGFP_PLUGIN_URL . 'assets/css/settings.css',
			array(),
			AIGFP_VERSION
		);
	}

	public function enqueue_admin_scripts($hook)
	{
		if ('post.php' === $hook || 'post-new.php' === $hook) {
			// Основные стили плагина
			wp_enqueue_style(
				'aigfp-admin',
				AIGFP_PLUGIN_URL . 'assets/css/admin.css',
				array(),
				AIGFP_VERSION
			);

			// Основной JavaScript плагина
			wp_enqueue_script(
				'aigfp-admin',
				AIGFP_PLUGIN_URL . 'assets/js/admin.js',
				array('jquery', 'heartbeat'),
				AIGFP_VERSION,
				true
			);

			// Локализация скрипта
			wp_localize_script('aigfp-admin', 'aigfp_ajax', array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('aigfp_nonce'),
				'post_id' => get_the_ID(),
				'plugin_url' => AIGFP_PLUGIN_URL,
				'puter_sdk_loaded' => false,
				'i18n' => array(
					'generating_image' => __('Generating image...', 'aigfp'),
					'uploading_image' => __('Uploading image...', 'aigfp'),
					'loading_ai_engine' => __('Loading AI engine...', 'aigfp'),
					'generate_image' => __('Generate Image', 'aigfp'),
					'use_as_featured' => __('Use as Featured Image', 'aigfp'),
					'error_sdk' => __('Puter.js SDK not loaded. Please refresh the page.', 'aigfp'),
					'error_funds' => __('There may not be enough funds. Check your balance on the https://puter.com/dashboard.', 'aigfp'),
					'error_generic' => __('Error generating image:', 'aigfp'),
					'success_featured' => __('Featured image set successfully!', 'aigfp'),
					'error_saving' => __('Error saving image:', 'aigfp')
				)
			));
		}
	}

	/**
	 * Добавляем Puter.js SDK в футер админки
	 */
	public function add_puter_sdk()
	{
		global $pagenow;

		// Загружаем только на страницах редактирования постов
		if ('post.php' === $pagenow || 'post-new.php' === $pagenow) {
?>
			<script>
				(function() {
					// Проверяем, не загружен ли уже Puter.js
					if (typeof puter === 'undefined') {
						console.log('AIGFP: <?php _e('Loading Puter.js SDK...', 'aigfp'); ?>');

						// Создаем скрипт элемент
						var script = document.createElement('script');
						script.src = 'https://js.puter.com/v2/';
						script.async = true;

						// Обработчики загрузки SDK
						script.onload = function() {
							console.log('AIGFP: <?php _e('Puter.js SDK loaded successfully', 'aigfp'); ?>');

							// Инициализируем Puter с настройками
							if (typeof puter !== 'undefined') {
								window.aigfp_puter_loaded = true;

								// Обновляем флаг в локализованных данных
								if (window.aigfp_ajax) {
									window.aigfp_ajax.puter_sdk_loaded = true;
								}

								// Отправляем событие о загрузке SDK
								document.dispatchEvent(new Event('aigfp-puter-loaded'));
							}
						};

						script.onerror = function() {
							console.error('AIGFP: <?php _e('Failed to load Puter.js SDK', 'aigfp'); ?>');
							window.aigfp_puter_loaded = false;

							// Показываем уведомление пользователю
							var notice = document.createElement('div');
							notice.className = 'notice notice-error aigfp-notice';
							notice.innerHTML = '<p><?php _e('Failed to load AI engine. Please check your internet connection and refresh the page.', 'aigfp'); ?></p>';

							var firstH1 = document.querySelector('h1');
							if (firstH1) {
								firstH1.parentNode.insertBefore(notice, firstH1.nextSibling);
							}
						};

						// Добавляем скрипт в документ
						document.head.appendChild(script);
					} else {
						console.log('AIGFP: <?php _e('Puter.js SDK already loaded', 'aigfp'); ?>');
						window.aigfp_puter_loaded = true;
					}
				})();
			</script>
		<?php
		}
	}

	public function add_meta_box()
	{
		add_meta_box(
			'aigfp_generate_image',
			__('Generate AI Image', 'aigfp'),
			array($this, 'render_meta_box'),
			'post',
			'side',
			'high'
		);
	}

	public function render_meta_box($post)
	{
		// Получаем настройки плагина
		$settings = get_option('aigfp_settings', array());
		$default_model = isset($settings['default_model']) ? $settings['default_model'] : 'gpt-image-1-mini';
		$default_quality = isset($settings['default_quality']) ? $settings['default_quality'] : 'low';
		$default_style = isset($settings['image_style']) ? $settings['image_style'] : 'professional';

		?>
		<div class="aigfp-meta-box">
			<p><?php _e('Generate a featured image for this post using AI.', 'aigfp'); ?></p>

			<div class="aigfp-prompt-section">
				<label for="aigfp_prompt"><?php _e('Custom Prompt:', 'aigfp'); ?></label>
				<textarea id="aigfp_prompt" rows="3" style="width:100%; margin: 5px 0;">
<?php echo trim(esc_textarea($this->generate_prompt_from_post($post))); ?>
                </textarea>
				<p class="description"><?php _e('Customize the AI prompt for image generation.', 'aigfp'); ?></p>
			</div>

			<div class="aigfp-style-section">
				<label for="aigfp_style"><?php _e('Style:', 'aigfp'); ?></label>
				<select id="aigfp_style" style="width:100%; margin: 5px 0;">
					<option value="professional, clean, modern, corporate, suitable for business blog" <?php selected($default_style, 'professional'); ?>><?php _e('Professional', 'aigfp'); ?></option>
					<option value="minimalist, simple, clean lines, lots of negative space" <?php selected($default_style, 'minimalist'); ?>><?php _e('Minimalist', 'aigfp'); ?></option>
					<option value="photorealistic, detailed, realistic lighting and textures" <?php selected($default_style, 'realistic'); ?>><?php _e('Photorealistic', 'aigfp'); ?></option>
					<option value="illustrated, vector art, flat design, colorful" <?php selected($default_style, 'illustrated'); ?>><?php _e('Illustrated', 'aigfp'); ?></option>
					<option value="artistic, painterly, creative, expressive brush strokes" <?php selected($default_style, 'artistic'); ?>><?php _e('Artistic', 'aigfp'); ?></option>
					<option value="vintage, retro, nostalgic, film grain effect" <?php selected($default_style, 'vintage'); ?>><?php _e('Vintage', 'aigfp'); ?></option>
					<option value="futuristic, sci-fi, cyberpunk, neon lights, glowing elements" <?php selected($default_style, 'futuristic'); ?>><?php _e('Futuristic', 'aigfp'); ?></option>
				</select>
			</div>

			<div class="aigfp-model-section">
				<label for="aigfp_model"><?php _e('AI Model:', 'aigfp'); ?></label>
				<select id="aigfp_model" style="width:100%; margin: 5px 0;">
					<option value="gpt-image-1-mini" <?php selected($default_model, 'gpt-image-1-mini'); ?>><?php _e('GPT Image 1 Mini (Fastest)', 'aigfp'); ?></option>
					<option value="gpt-image-1" <?php selected($default_model, 'gpt-image-1'); ?>><?php _e('GPT Image 1 (Medium)', 'aigfp'); ?></option>
					<option value="dall-e-3" <?php selected($default_model, 'dall-e-3'); ?>><?php _e('DALL-E 3 (Highest Quality)', 'aigfp'); ?></option>
				</select>
			</div>

			<div class="aigfp-quality-section">
				<label for="aigfp_quality"><?php _e('Quality:', 'aigfp'); ?></label>
				<select id="aigfp_quality" style="width:100%; margin: 5px 0;">
					<option value="low" <?php selected($default_quality, 'low'); ?>><?php _e('Low (Fastest)', 'aigfp'); ?></option>
					<option value="medium" <?php selected($default_quality, 'medium'); ?>><?php _e('Medium', 'aigfp'); ?></option>
					<option value="high" <?php selected($default_quality, 'high'); ?>><?php _e('High (Best)', 'aigfp'); ?></option>
				</select>
			</div>

			<button type="button" id="aigfp_generate_btn" class="button button-primary">
				<span class="dashicons dashicons-format-image"></span>
				<?php _e('Generate Image', 'aigfp'); ?>
			</button>

			<div id="aigfp_result" style="margin-top: 15px; display: none;">
				<div id="aigfp_preview" style="margin-bottom: 10px;"></div>
				<button type="button" id="aigfp_use_image" class="button" style="display: none;">
					<?php _e('Use as Featured Image', 'aigfp'); ?>
				</button>
			</div>

			<div id="aigfp_loading" style="display: none; margin-top: 10px;">
				<span class="spinner is-active"></span>
				<span id="aigfp_loading_txt"><?php _e('Generating image...', 'aigfp'); ?><span>
			</div>

			<div id="aigfp_sdk_status" class="loading" style="display: none; margin-top: 10px; padding: 5px; border-radius: 3px; background: #f0f0f0;">
				<span class="dashicons dashicons-update"></span> <?php _e('Loading AI engine...', 'aigfp'); ?>
			</div>
		</div>
	<?php
	}

	private function generate_prompt_from_post($post)
	{
		$title = get_the_title($post);
		$content = wp_strip_all_tags($post->post_content);

		// Генерируем промпт
		$prompt = sprintf(__('Professional blog post featured image about: %s.', 'aigfp'), $title);
		$prompt .= " " . __('No text on image, just visual representation.', 'aigfp');

		return $prompt;
	}

	public function render_settings_page()
	{
		include AIGFP_PLUGIN_DIR . 'templates/settings-page.php';
	}

	/**
	 * Регистрация настроек плагина
	 */
	public function register_settings()
	{
		register_setting(
			'aigfp_settings_group',
			'aigfp_settings',
			array($this, 'sanitize_settings')
		);

		add_settings_section(
			'aigfp_main_settings',
			__('Main Settings', 'aigfp'),
			array($this, 'render_settings_section'),
			'ai-image-generator'
		);

		add_settings_field(
			'image_style',
			__('Default Image Style', 'aigfp'),
			array($this, 'render_image_style_field'),
			'ai-image-generator',
			'aigfp_main_settings'
		);

		add_settings_field(
			'default_model',
			__('Default AI Model', 'aigfp'),
			array($this, 'render_model_field'),
			'ai-image-generator',
			'aigfp_main_settings'
		);

		add_settings_field(
			'default_quality',
			__('Default Quality', 'aigfp'),
			array($this, 'render_quality_field'),
			'ai-image-generator',
			'aigfp_main_settings'
		);
	}

	public function render_settings_section()
	{
		echo '<p>' . __('Configure the default settings for AI image generation.', 'aigfp') . '</p>';
	}

	public function render_model_field()
	{
		$settings = get_option('aigfp_settings', array());
		$value = isset($settings['default_model']) ? $settings['default_model'] : 'gpt-image-1-mini';
	?>
		<select name="aigfp_settings[default_model]">
			<option value="gpt-image-1-mini" <?php selected($value, 'gpt-image-1-mini'); ?>><?php _e('GPT Image 1 Mini (Fastest)', 'aigfp'); ?></option>
			<option value="gpt-image-1" <?php selected($value, 'gpt-image-1'); ?>><?php _e('GPT Image 1 (Medium)', 'aigfp'); ?></option>
			<option value="dall-e-3" <?php selected($value, 'dall-e-3'); ?>><?php _e('DALL-E 3 (Highest Quality)', 'aigfp'); ?></option>
		</select>
		<p class="description"><?php _e('Select the default AI model for image generation.', 'aigfp'); ?></p>
	<?php
	}

	public function render_quality_field()
	{
		$settings = get_option('aigfp_settings', array());
		$value = isset($settings['default_quality']) ? $settings['default_quality'] : 'low';
	?>
		<select name="aigfp_settings[default_quality]">
			<option value="low" <?php selected($value, 'low'); ?>><?php _e('Low (Fastest)', 'aigfp'); ?></option>
			<option value="medium" <?php selected($value, 'medium'); ?>><?php _e('Medium', 'aigfp'); ?></option>
			<option value="high" <?php selected($value, 'high'); ?>><?php _e('High (Best Quality)', 'aigfp'); ?></option>
		</select>
		<p class="description"><?php _e('Select the default quality setting.', 'aigfp'); ?></p>
	<?php
	}

	public function render_image_style_field()
	{
		$settings = get_option('aigfp_settings', array());
		$value = isset($settings['image_style']) ? $settings['image_style'] : 'professional';
	?>
		<select name="aigfp_settings[image_style]">
			<option value="professional" <?php selected($value, 'professional'); ?>><?php _e('Professional (Clean & Modern)', 'aigfp'); ?></option>
			<option value="minimalist" <?php selected($value, 'minimalist'); ?>><?php _e('Minimalist', 'aigfp'); ?></option>
			<option value="realistic" <?php selected($value, 'realistic'); ?>><?php _e('Photorealistic', 'aigfp'); ?></option>
			<option value="illustrated" <?php selected($value, 'illustrated'); ?>><?php _e('Illustrated', 'aigfp'); ?></option>
			<option value="artistic" <?php selected($value, 'artistic'); ?>><?php _e('Artistic', 'aigfp'); ?></option>
			<option value="vintage" <?php selected($value, 'vintage'); ?>><?php _e('Vintage', 'aigfp'); ?></option>
			<option value="futuristic" <?php selected($value, 'futuristic'); ?>><?php _e('Futuristic', 'aigfp'); ?></option>
		</select>
		<p class="description"><?php _e('Default style for generated images.', 'aigfp'); ?></p>
<?php
	}

	public function sanitize_settings($input)
	{
		$sanitized = array();

		if (isset($input['default_model'])) {
			$allowed_models = array('gpt-image-1-mini', 'gpt-image-1', 'dall-e-3');
			$sanitized['default_model'] = in_array($input['default_model'], $allowed_models)
				? $input['default_model']
				: 'gpt-image-1';
		}

		if (isset($input['default_quality'])) {
			$allowed_qualities = array('low', 'medium', 'high');
			$sanitized['default_quality'] = in_array($input['default_quality'], $allowed_qualities)
				? $input['default_quality']
				: 'medium';
		}

		if (isset($input['image_style'])) {
			$allowed_styles = array('professional', 'minimalist', 'realistic', 'illustrated', 'artistic', 'vintage', 'futuristic');
			$sanitized['image_style'] = in_array($input['image_style'], $allowed_styles)
				? $input['image_style']
				: 'professional';
		}

		return $sanitized;
	}
}
?>