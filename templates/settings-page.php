<?php
// Проверка прав доступа
if (!current_user_can('manage_options')) {
	wp_die(__('You do not have sufficient permissions to access this page.', 'aigfp'));
}
?>

<div class="wrap">
	<h1><?php echo esc_html(get_admin_page_title()); ?></h1>

	<form method="post" action="options.php">
		<?php
		settings_fields('aigfp_settings_group');
		do_settings_sections('ai-image-generator');
		submit_button(__('Save Settings', 'aigfp'));
		?>
	</form>

	<div class="settins-desc">
		<div class="card desc-card">
			<h2><?php _e('Usage Instructions', 'aigfp'); ?></h2>
			<ol>
				<li><?php _e('Create or edit a post', 'aigfp'); ?></li>
				<li><?php _e('Find the "Generate AI Image" meta box in the sidebar', 'aigfp'); ?></li>
				<li><?php _e('Adjust the prompt if needed', 'aigfp'); ?></li>
				<li><?php _e('Click "Generate Image"', 'aigfp'); ?></li>
				<li><?php _e('Use the generated image as featured image', 'aigfp'); ?></li>
			</ol>
		</div>
		<div class="card desc-card">
			<h2><?php _e('About Puter.js', 'aigfp'); ?></h2>
			<p><?php _e('This plugin uses the User-Pays Model. Users pay for their own AI credits when generating images.', 'aigfp'); ?></p>
			<p><?php _e('This plugin uses a user-based payment model.', 'aigfp'); ?></p>
			<p><?php _e('This means that you, as the user, will control and pay for the use of artificial intelligence to generate images. The cost of generating one image depends on the selected AI model and quality score and is approximately $0.005 per image. Registration on piter.com will occur automatically the first time you open the post editing page, without the need to enter an email address, password, or anything else. Subsequently, you will be directly logged into your account. No additional authentication is required.', 'aigfp'); ?></p>
			<p><?php _e('Upon registration, you will be credited with a certain amount of money, which you can use to test our plugin. Monitor your balance on the Puter.com dashboard.', 'aigfp'); ?></p>

			<div class="puter-links">
				<a href="https://puter.com/dashboard" target="_blank" class="button button-primary">
					<span class="dashicons dashicons-dashboard"></span> <?php _e('Open Puter Dashboard', 'aigfp'); ?>
				</a>
				<a href="https://developer.puter.com/" target="_blank" class="button">
					<span class="dashicons dashicons-welcome-learn-more"></span> <?php _e('Learn more', 'aigfp'); ?>
				</a>
			</div>
		</div>

		<div class="card desc-card" style="width: 100%; border-left: 4px solid #2271b1;">
			<h2 style="margin-top: 0;">⚠ <?php _e('Important Information and Disclaimer', 'aigfp'); ?></h2>

			<p><strong><?php _e('1. Third-party AI Service:', 'aigfp'); ?></strong></p>
			<p><?php _e('This plugin uses the third-party Puter.js service for image generation. The developers of Puter.js are in no way affiliated with the developers of this plugin and are not responsible for its operation.', 'aigfp'); ?></p>

			<p><strong><?php _e('2. Free Distribution:', 'aigfp'); ?></strong></p>
			<p><?php _e('The "AI Image Generator for Posts" plugin is provided completely free of charge and on an "AS IS" basis, without any warranties.', 'aigfp'); ?></p>

			<p><strong><?php _e('3. User-Pays Model:', 'aigfp'); ?></strong></p>
			<p><?php _e('The plugin operates on a "user-pays" model. All financial transactions for image generation occur directly between the user and the Puter.com service.', 'aigfp'); ?></p>

			<p><strong><?php _e('4. Financial Matters:', 'aigfp'); ?></strong></p>
			<p><?php _e('All issues related to payments, charges, pricing, refunds, and other financial aspects must be resolved directly with Puter.com administration through their official support channels.', 'aigfp'); ?></p>

			<p><strong><?php _e('5. Liability:', 'aigfp'); ?></strong></p>
			<p><?php _e('The developers of this plugin are not responsible for:', 'aigfp'); ?></p>
			<ul>
				<li><?php _e('The operation and availability of the Puter.js service', 'aigfp'); ?></li>
				<li><?php _e('The quality of generated images', 'aigfp'); ?></li>
				<li><?php _e('Financial disputes with Puter.com', 'aigfp'); ?></li>
				<li><?php _e('Changes to Puter.js API or pricing', 'aigfp'); ?></li>
				<li><?php _e('Data loss or any other damages', 'aigfp'); ?></li>
			</ul>
		</div>
	</div>
</div>