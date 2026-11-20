<?php
/**
 * Settings > ISBNLab: the API token, caching and display options.
 *
 * @package ISBNLab
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stores everything in the single option `isbnlab_settings`.
 */
final class ISBNLab_Settings {

	const OPTION = 'isbnlab_settings';
	const PAGE   = 'isbnlab';

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'wp_ajax_isbnlab_test_connection', array( __CLASS__, 'ajax_test_connection' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( ISBNLAB_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'token'       => '',
			'cache_hours' => 24,
			'credit_link' => false,
		);
	}

	/**
	 * Current settings merged over the defaults.
	 *
	 * @return array
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );

		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * The API token. A token defined in wp-config.php as ISBNLAB_API_TOKEN wins over the saved one.
	 *
	 * @return string
	 */
	public static function token() {
		if ( self::token_from_constant() ) {
			return (string) ISBNLAB_API_TOKEN;
		}

		return (string) self::all()['token'];
	}

	/**
	 * Whether wp-config.php sets the token.
	 *
	 * @return bool
	 */
	public static function token_from_constant() {
		return defined( 'ISBNLAB_API_TOKEN' ) && '' !== (string) ISBNLAB_API_TOKEN;
	}

	/**
	 * Hours to cache a book lookup.
	 *
	 * @return int
	 */
	public static function cache_hours() {
		return max( 0, (int) self::all()['cache_hours'] );
	}

	/**
	 * Whether book cards link to the book's page on isbnlab.com. Off unless the site owner turns it on.
	 *
	 * @return bool
	 */
	public static function credit_link() {
		return ! empty( self::all()['credit_link'] );
	}

	/**
	 * Add the page under Settings.
	 */
	public static function add_page() {
		add_options_page(
			__( 'ISBNLab', 'isbnlab' ),
			__( 'ISBNLab', 'isbnlab' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * A Settings link on the Plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ), esc_html__( 'Settings', 'isbnlab' ) )
		);

		return $links;
	}

	/**
	 * Register the option and its fields.
	 */
	public static function register() {
		register_setting(
			self::PAGE,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);

		add_settings_section( 'isbnlab_api', __( 'API', 'isbnlab' ), array( __CLASS__, 'render_api_intro' ), self::PAGE );
		add_settings_field( 'isbnlab_token', __( 'API token', 'isbnlab' ), array( __CLASS__, 'render_token_field' ), self::PAGE, 'isbnlab_api', array( 'label_for' => 'isbnlab_token' ) );
		add_settings_field( 'isbnlab_cache_hours', __( 'Cache lookups for', 'isbnlab' ), array( __CLASS__, 'render_cache_field' ), self::PAGE, 'isbnlab_api', array( 'label_for' => 'isbnlab_cache_hours' ) );

		add_settings_section( 'isbnlab_display', __( 'Book display', 'isbnlab' ), '__return_false', self::PAGE );
		add_settings_field( 'isbnlab_credit_link', __( 'Link to ISBNLab', 'isbnlab' ), array( __CLASS__, 'render_credit_field' ), self::PAGE, 'isbnlab_display' );
	}

	/**
	 * Sanitize the submitted settings. An empty token field keeps the saved token, since the
	 * page never shows it; "Remove the saved token" clears it.
	 *
	 * @param mixed $input Submitted values.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input   = is_array( $input ) ? $input : array();
		$current = self::all();
		$clean   = self::defaults();

		$token = isset( $input['token'] ) ? trim( sanitize_text_field( wp_unslash( $input['token'] ) ) ) : '';

		if ( ! empty( $input['remove_token'] ) ) {
			$clean['token'] = '';
		} elseif ( '' !== $token ) {
			$clean['token'] = $token;
		} else {
			$clean['token'] = (string) $current['token'];
		}

		$hours                = isset( $input['cache_hours'] ) ? absint( $input['cache_hours'] ) : self::defaults()['cache_hours'];
		$clean['cache_hours'] = min( $hours, 24 * 365 );
		$clean['credit_link'] = ! empty( $input['credit_link'] );

		return $clean;
	}

	/**
	 * Intro text for the API section.
	 */
	public static function render_api_intro() {
		printf(
			'<p>%s</p>',
			wp_kses(
				sprintf(
					/* translators: 1: registration URL, 2: pricing URL. */
					__( 'Book data comes from the ISBNLab API. <a href="%1$s" target="_blank" rel="noopener">Create a free account</a> and copy the API token from your profile. The Free plan includes 1,000 lookups a day; see <a href="%2$s" target="_blank" rel="noopener">pricing</a> for more.', 'isbnlab' ),
					'https://www.isbnlab.com/register',
					'https://www.isbnlab.com/pricing'
				),
				array(
					'a' => array(
						'href'   => array(),
						'target' => array(),
						'rel'    => array(),
					),
				)
			)
		);
	}

	/**
	 * The token field. The saved token is never printed into the page.
	 */
	public static function render_token_field() {
		if ( self::token_from_constant() ) {
			echo '<p>' . esc_html__( 'Set in wp-config.php (ISBNLAB_API_TOKEN).', 'isbnlab' ) . '</p>';
		} else {
			$saved = (string) self::all()['token'];
			printf(
				'<input type="password" id="isbnlab_token" name="%s[token]" value="" class="regular-text" autocomplete="off" spellcheck="false" placeholder="%s" />',
				esc_attr( self::OPTION ),
				esc_attr( '' === $saved ? '' : __( 'Saved. Enter a new token to replace it.', 'isbnlab' ) )
			);

			if ( '' !== $saved ) {
				printf(
					'<p><label><input type="checkbox" name="%s[remove_token]" value="1" /> %s</label></p>',
					esc_attr( self::OPTION ),
					esc_html__( 'Remove the saved token', 'isbnlab' )
				);
			}

			echo '<p class="description">' . esc_html__( 'You can also define ISBNLAB_API_TOKEN in wp-config.php instead of saving it here.', 'isbnlab' ) . '</p>';
		}

		printf(
			'<p><button type="button" class="button" id="isbnlab-test-connection">%s</button> <span id="isbnlab-test-result" role="status"></span></p>',
			esc_html__( 'Test connection', 'isbnlab' )
		);
	}

	/**
	 * Cache duration field.
	 */
	public static function render_cache_field() {
		printf(
			'<input type="number" min="0" max="8760" step="1" id="isbnlab_cache_hours" name="%s[cache_hours]" value="%d" class="small-text" /> %s<p class="description">%s</p>',
			esc_attr( self::OPTION ),
			(int) self::cache_hours(),
			esc_html__( 'hours', 'isbnlab' ),
			esc_html__( 'Book blocks and shortcodes reuse a lookup for this long, so page views do not use up your daily calls. 0 turns caching off.', 'isbnlab' )
		);
	}

	/**
	 * Credit link field.
	 */
	public static function render_credit_field() {
		printf(
			'<label><input type="checkbox" name="%s[credit_link]" value="1" %s /> %s</label>',
			esc_attr( self::OPTION ),
			checked( self::credit_link(), true, false ),
			esc_html__( 'Show a "Book data from ISBNLab" link under book blocks and shortcodes', 'isbnlab' )
		);
	}

	/**
	 * The settings page.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_enqueue_script( 'isbnlab-settings', ISBNLAB_URL . 'assets/settings.js', array(), ISBNLAB_VERSION, true );
		wp_localize_script(
			'isbnlab-settings',
			'isbnlabSettings',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'isbnlab_test_connection' ),
				'testing' => __( 'Testing…', 'isbnlab' ),
				'failed'  => __( 'The test could not run.', 'isbnlab' ),
			)
		);
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::PAGE );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
			<h2><?php esc_html_e( 'Showing a book', 'isbnlab' ); ?></h2>
			<p><?php esc_html_e( 'Add the ISBNLab Book block in the editor, or use the shortcode:', 'isbnlab' ); ?></p>
			<p><code>[isbnlab_book isbn="9780765302304"]</code></p>
			<p><?php esc_html_e( 'Options: cover="no", description="no", details="no".', 'isbnlab' ); ?></p>
		</div>
		<?php
	}

	/**
	 * AJAX: check a token with one call. Tests the token typed in the field, so it can be
	 * checked before saving; with the field empty, tests the saved token.
	 */
	public static function ajax_test_connection() {
		check_ajax_referer( 'isbnlab_test_connection', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do that.', 'isbnlab' ) ), 403 );
		}

		$typed = isset( $_POST['token'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['token'] ) ) ) : '';
		$token = self::connection_token( $typed );

		if ( '' === $token ) {
			wp_send_json_error( array( 'message' => __( 'Enter your API token in the field above, then click Test connection.', 'isbnlab' ) ) );
		}

		$client = new ISBNLab_Client( $token, self::cache_hours() * HOUR_IN_SECONDS );
		$result = $client->test_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$unsaved = '' !== $typed && ! self::token_from_constant() && self::token() !== $typed;
		wp_send_json_success(
			array(
				'message' => $unsaved
					? __( 'Connected. This token works. Click Save Changes to keep it.', 'isbnlab' )
					: __( 'Connected. Your token works.', 'isbnlab' ),
			)
		);
	}

	/**
	 * The token Test connection checks: the wp-config.php constant if set, else the typed
	 * token, else the saved one.
	 *
	 * @param string $typed The token typed in the field, not saved yet.
	 * @return string
	 */
	public static function connection_token( $typed ) {
		if ( self::token_from_constant() || '' === $typed ) {
			return self::token();
		}
		return $typed;
	}
}
