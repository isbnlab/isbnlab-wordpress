<?php
/**
 * Showing a book on the site: the ISBNLab Book block and the [isbnlab_book] shortcode.
 *
 * @package ISBNLab
 */

defined( 'ABSPATH' ) || exit;

/**
 * Both render the same card through render(), with Book structured data for search engines.
 */
final class ISBNLab_Display {

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register the style, the block and the shortcode.
	 */
	public static function register() {
		wp_register_style( 'isbnlab-book', ISBNLAB_URL . 'blocks/book/style.css', array(), ISBNLAB_VERSION );

		register_block_type(
			ISBNLAB_DIR . 'blocks/book',
			array( 'render_callback' => array( __CLASS__, 'render_block' ) )
		);

		add_shortcode( 'isbnlab_book', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Block render callback.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_block( $attributes ) {
		$attributes = wp_parse_args(
			$attributes,
			array(
				'isbn'            => '',
				'showCover'       => true,
				'showDetails'     => true,
				'showDescription' => true,
			)
		);

		$html = self::render(
			(string) $attributes['isbn'],
			array(
				'cover'       => (bool) $attributes['showCover'],
				'details'     => (bool) $attributes['showDetails'],
				'description' => (bool) $attributes['showDescription'],
			)
		);

		if ( '' === $html ) {
			return '';
		}

		return sprintf( '<div %s>%s</div>', get_block_wrapper_attributes(), $html );
	}

	/**
	 * [isbnlab_book isbn="9780765302304" cover="no" details="no" description="no"]
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'isbn'        => '',
				'cover'       => 'yes',
				'details'     => 'yes',
				'description' => 'yes',
			),
			$atts,
			'isbnlab_book'
		);

		wp_enqueue_style( 'isbnlab-book' );

		return self::render(
			$atts['isbn'],
			array(
				'cover'       => self::yes( $atts['cover'] ),
				'details'     => self::yes( $atts['details'] ),
				'description' => self::yes( $atts['description'] ),
			)
		);
	}

	/**
	 * The book card for an ISBN. On an error, editors see the reason and visitors see nothing.
	 *
	 * @param string $isbn    ISBN-10 or ISBN-13.
	 * @param array  $options cover, details, description: booleans.
	 * @return string HTML.
	 */
	public static function render( $isbn, array $options ) {
		if ( '' === trim( $isbn ) ) {
			return self::editor_notice( __( 'Enter an ISBN to show a book.', 'isbnlab' ) );
		}

		$book = ISBNLab_Client::from_settings()->get_book( $isbn );

		if ( is_wp_error( $book ) ) {
			return self::editor_notice( $book->get_error_message() );
		}

		/**
		 * Filters the book card options before rendering.
		 *
		 * @param array        $options cover, details, description.
		 * @param ISBNLab_Book $book    The book.
		 */
		$options = apply_filters( 'isbnlab_book_card_options', $options, $book );

		ob_start();
		self::render_card( $book, $options );
		$html = (string) ob_get_clean();

		/**
		 * Filters the book card HTML.
		 *
		 * @param string       $html    Card HTML, including the JSON-LD script.
		 * @param ISBNLab_Book $book    The book.
		 * @param array        $options cover, details, description.
		 */
		return (string) apply_filters( 'isbnlab_book_card_html', $html, $book, $options );
	}

	/**
	 * Card markup.
	 *
	 * @param ISBNLab_Book $book    The book.
	 * @param array        $options cover, details, description.
	 */
	private static function render_card( ISBNLab_Book $book, array $options ) {
		$labels  = ISBNLab_WooCommerce::detail_labels();
		$details = $book->details();
		unset( $details['isbn10'] );
		?>
		<div class="isbnlab-book">
			<?php if ( ! empty( $options['cover'] ) && '' !== $book->cover_url() ) : ?>
				<img class="isbnlab-book__cover" src="<?php echo esc_url( $book->cover_url() ); ?>" alt="<?php echo esc_attr( sprintf( /* translators: %s: book title. */ __( 'Cover of %s', 'isbnlab' ), $book->title() ) ); ?>" loading="lazy" decoding="async" />
			<?php endif; ?>
			<div class="isbnlab-book__body">
				<p class="isbnlab-book__title"><?php echo esc_html( $book->title() ); ?></p>
				<?php if ( '' !== $book->subtitle() ) : ?>
					<p class="isbnlab-book__subtitle"><?php echo esc_html( $book->subtitle() ); ?></p>
				<?php endif; ?>
				<?php if ( $book->authors() ) : ?>
					<p class="isbnlab-book__authors"><?php echo esc_html( implode( ', ', $book->authors() ) ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $options['details'] ) ) : ?>
					<dl class="isbnlab-book__details">
						<?php foreach ( $details as $key => $value ) : ?>
							<?php
							if ( 'author' === $key || ! isset( $labels[ $key ] ) ) {
								continue;
							}
							?>
							<dt><?php echo esc_html( $labels[ $key ] ); ?></dt>
							<dd><?php echo esc_html( $value ); ?></dd>
						<?php endforeach; ?>
					</dl>
				<?php endif; ?>
				<?php if ( ! empty( $options['description'] ) && '' !== $book->description() ) : ?>
					<div class="isbnlab-book__description"><?php echo wp_kses_post( wpautop( $book->description() ) ); ?></div>
				<?php endif; ?>
				<?php if ( ISBNLab_Settings::credit_link() && '' !== $book->link() ) : ?>
					<p class="isbnlab-book__credit"><a href="<?php echo esc_url( $book->link() ); ?>"><?php esc_html_e( 'Book data from ISBNLab', 'isbnlab' ); ?></a></p>
				<?php endif; ?>
			</div>
			<script type="application/ld+json"><?php echo wp_json_encode( $book->schema(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>
		</div>
		<?php
	}

	/**
	 * A message shown only to people who can edit posts, so visitors never see a broken card.
	 *
	 * @param string $message Text.
	 * @return string
	 */
	private static function editor_notice( $message ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return '';
		}

		return sprintf(
			'<p class="isbnlab-book-notice"><strong>%s</strong> %s</p>',
			esc_html__( 'ISBNLab:', 'isbnlab' ),
			esc_html( $message )
		);
	}

	/**
	 * Whether a shortcode attribute means yes.
	 *
	 * @param string $value Attribute value.
	 * @return bool
	 */
	private static function yes( $value ) {
		return ! in_array( strtolower( trim( (string) $value ) ), array( 'no', 'false', '0', 'off' ), true );
	}
}
