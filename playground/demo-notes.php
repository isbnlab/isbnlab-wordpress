<?php
/**
 * Plugin Name: ISBNLab demo notes
 * Description: Notes for visitors of the WordPress Playground demo. Copied to mu-plugins by playground/setup.php; not part of the plugin zip.
 *
 * @package ISBNLab
 */

add_action(
	'isbnlab_after_token_field',
	function () {
		?>
		<div class="notice notice-warning inline" style="max-width: 640px;">
			<p><strong>Using a real token in this demo?</strong>
			This WordPress runs in your browser, and its requests to ISBNLab pass through WordPress Playground's CORS proxy. When you are done, regenerate the token in your ISBNLab profile so this one stops working. A free token is quick to regenerate.</p>
		</div>
		<?php
	}
);
