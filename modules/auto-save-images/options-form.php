<?php
/**
 * Settings template for Auto Save Images.
 *
 * @package ClassicPack
 *
 * @var array $options Saved options.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<style type="text/css">
#classicpress-auto-save-images{ width:650px; margin:20px 0;border:1px solid #ddd; background-color:#f7f7f7; padding:10px; }
#classicpress-auto-save-images span.des{ color:#999; margin-left:10px; }
#classicpress-auto-save-images label{ width:160px; display:inline-block; vertical-align:top; }
#classicpress-auto-save-images p { margin-bottom:14px; }
#classicpress-auto-save-images .option-desc { display:block; margin-left:160px; margin-top:4px; color:#666; font-size:12px; }
</style>

<div class="wrap">

	<h1><?php esc_html_e( 'Auto Save Images — Settings', 'classicpack' ); ?></h1>

	<div id="classicpress-auto-save-images">
		<form action="" method="post">
			<?php wp_nonce_field( 'classicpress_auto_save_images_options', 'classicpress_auto_save_images_nonce' ); ?>

			<p>
				<label for="tmb"><?php esc_html_e( 'Disable thumbnails:', 'classicpack' ); ?></label>
				<input type="checkbox" id="tmb" class="classicpack-ui-toggle" name="tmb" value="yes" <?php checked( 'yes', isset( $options['tmb'] ) ? $options['tmb'] : '' ); ?>/> <?php esc_html_e( 'Yes', 'classicpack' ); ?>
				<span class="option-desc"><?php esc_html_e( 'When checked, WordPress will not generate resized thumbnail versions for images downloaded by this plugin.', 'classicpack' ); ?></span>
			</p>

			<p>
				<label for="switch"><?php esc_html_e( 'Per-post skip toggle:', 'classicpack' ); ?></label>
				<input type="checkbox" id="switch" class="classicpack-ui-toggle" name="switch" value="yes" <?php checked( 'yes', isset( $options['switch'] ) ? $options['switch'] : '' ); ?>/> <?php esc_html_e( 'Yes', 'classicpack' ); ?>
				<span class="option-desc"><?php esc_html_e( 'When checked, a toggle appears in each post’s editor (classic and block) to skip remote images for that post.', 'classicpack' ); ?></span>
			</p>

			<p>
				<label for="post-tmb"><?php esc_html_e( 'Auto featured image:', 'classicpack' ); ?></label>
				<input type="checkbox" id="post-tmb" class="classicpack-ui-toggle" name="post-tmb" value="yes" <?php checked( 'yes', isset( $options['post-tmb'] ) ? $options['post-tmb'] : '' ); ?>/> <?php esc_html_e( 'Yes', 'classicpack' ); ?>
				<span class="option-desc"><?php esc_html_e( 'When checked, the first remote image found is set as the featured image after download.', 'classicpack' ); ?></span>
			</p>

			<?php submit_button( __( 'Save Settings', 'classicpack' ) ); ?>
		</form>
	</div>

	<div style="clear:both;"></div>

</div>
