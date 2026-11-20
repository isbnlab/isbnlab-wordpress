<?php
/**
 * WooCommerce: an ISBNLab box on the product screen that fills a product from an ISBN.
 *
 * @package ISBNLab
 */

defined( 'ABSPATH' ) || exit;

/**
 * Two steps, so nothing changes until the user has seen the data: "Look up" shows a preview
 * with a checkbox per kind of data, and "Apply to product" saves the ticked ones and reloads.
 */
final class ISBNLab_WooCommerce {

	const META_ISBN  = '_isbnlab_isbn13';
	const META_COVER = '_isbnlab_cover_source';

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'add_meta_boxes_product', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_ajax_isbnlab_preview', array( __CLASS__, 'ajax_preview' ) );
		add_action( 'wp_ajax_isbnlab_apply', array( __CLASS__, 'ajax_apply' ) );
	}

	/**
	 * What "Apply to product" can fill, with whether it is ticked by default.
	 *
	 * @return array<string, array{label: string, default: bool}>
	 */
	public static function fields() {
		return array(
			'title'       => array(
				'label'   => __( 'Product name (title)', 'isbnlab' ),
				'default' => true,
			),
			'subtitle'    => array(
				'label'   => __( 'Add the subtitle to the name', 'isbnlab' ),
				'default' => false,
			),
			'description' => array(
				'label'   => __( 'Description', 'isbnlab' ),
				'default' => true,
			),
			'cover'       => array(
				'label'   => __( 'Cover as product image', 'isbnlab' ),
				'default' => true,
			),
			'attributes'  => array(
				'label'   => __( 'Attributes (author, publisher, format, pages…)', 'isbnlab' ),
				'default' => true,
			),
			'gtin'        => array(
				'label'   => __( 'ISBN-13 as the GTIN/ISBN field', 'isbnlab' ),
				'default' => true,
			),
			'sku'         => array(
				'label'   => __( 'ISBN-13 as the SKU', 'isbnlab' ),
				'default' => false,
			),
		);
	}

	/**
	 * The box on the product edit screen.
	 */
	public static function add_meta_box() {
		add_meta_box( 'isbnlab-product', __( 'ISBNLab book data', 'isbnlab' ), array( __CLASS__, 'render_meta_box' ), 'product', 'side', 'high' );
	}

	/**
	 * Box contents.
	 *
	 * @param WP_Post $post The product.
	 */
	public static function render_meta_box( $post ) {
		$isbn = (string) get_post_meta( $post->ID, self::META_ISBN, true );

		if ( '' === $isbn ) {
			$product = wc_get_product( $post->ID );
			if ( $product && method_exists( $product, 'get_global_unique_id' ) && ISBNLab_Isbn::is_valid( $product->get_global_unique_id() ) ) {
				$isbn = $product->get_global_unique_id();
			}
		}

		if ( '' === ISBNLab_Settings::token() ) {
			printf(
				'<p>%s</p>',
				wp_kses(
					sprintf(
						/* translators: %s: settings page URL. */
						__( 'Add your API token on the <a href="%s">ISBNLab settings page</a> to look up books.', 'isbnlab' ),
						esc_url( admin_url( 'options-general.php?page=' . ISBNLab_Settings::PAGE ) )
					),
					array( 'a' => array( 'href' => array() ) )
				)
			);

			return;
		}
		?>
		<div class="isbnlab-box">
			<label for="isbnlab-isbn" class="screen-reader-text"><?php esc_html_e( 'ISBN', 'isbnlab' ); ?></label>
			<div class="isbnlab-row">
				<input type="text" id="isbnlab-isbn" value="<?php echo esc_attr( $isbn ); ?>" placeholder="<?php esc_attr_e( 'ISBN-10 or ISBN-13', 'isbnlab' ); ?>" inputmode="numeric" autocomplete="off" />
				<button type="button" class="button" id="isbnlab-lookup"><?php esc_html_e( 'Look up', 'isbnlab' ); ?></button>
			</div>
			<p class="isbnlab-message" id="isbnlab-message" role="status"></p>
			<div id="isbnlab-preview" hidden></div>
			<div id="isbnlab-apply-panel" hidden>
				<fieldset>
					<legend class="screen-reader-text"><?php esc_html_e( 'Fill in', 'isbnlab' ); ?></legend>
					<?php foreach ( self::fields() as $key => $field ) : ?>
						<label><input type="checkbox" name="isbnlab_fields[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $field['default'] ); ?> /> <?php echo esc_html( $field['label'] ); ?></label>
					<?php endforeach; ?>
				</fieldset>
				<p class="description"><?php esc_html_e( 'This saves the product and reloads the page. Save other changes first.', 'isbnlab' ); ?></p>
				<button type="button" class="button button-primary" id="isbnlab-apply"><?php esc_html_e( 'Apply to product', 'isbnlab' ); ?></button>
			</div>
		</div>
		<?php
	}

	/**
	 * Scripts and styles for the product screen.
	 *
	 * @param string $hook Admin page.
	 */
	public static function enqueue( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style( 'isbnlab-admin', ISBNLAB_URL . 'assets/admin.css', array(), ISBNLAB_VERSION );
		wp_enqueue_script( 'isbnlab-product', ISBNLAB_URL . 'assets/product.js', array(), ISBNLAB_VERSION, true );
		wp_localize_script(
			'isbnlab-product',
			'isbnlabProduct',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'isbnlab_product' ),
				'postId'   => (int) get_the_ID(),
				'looking'  => __( 'Looking up…', 'isbnlab' ),
				'applying' => __( 'Saving…', 'isbnlab' ),
				'failed'   => __( 'Something went wrong. Try again.', 'isbnlab' ),
				'nothing'  => __( 'Tick at least one thing to fill in.', 'isbnlab' ),
			)
		);
	}

	/**
	 * AJAX: look up an ISBN and return a preview.
	 */
	public static function ajax_preview() {
		self::check_request();
		$book = self::lookup();

		ob_start();
		self::render_preview( $book );

		wp_send_json_success(
			array(
				'isbn' => $book->isbn13(),
				'html' => ob_get_clean(),
			)
		);
	}

	/**
	 * AJAX: fill the product with the ticked fields and save it.
	 */
	public static function ajax_apply() {
		$post_id = self::check_request();
		$book    = self::lookup();
		$product = wc_get_product( $post_id );

		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Product not found.', 'isbnlab' ) ), 404 );
		}

		$fields = isset( $_POST['fields'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['fields'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in check_request().
		$fields = array_intersect( $fields, array_keys( self::fields() ) );

		$notes = self::apply( $product, $book, $fields );

		wp_send_json_success( array( 'notes' => $notes ) );
	}

	/**
	 * Copy the chosen book data onto a product and save it.
	 *
	 * @param WC_Product   $product The product.
	 * @param ISBNLab_Book $book    The book.
	 * @param string[]     $fields  Keys from fields().
	 * @return string[] Things that could not be filled, for the user.
	 */
	public static function apply( $product, ISBNLab_Book $book, array $fields ) {
		$notes = array();
		$want  = array_flip( $fields );

		if ( isset( $want['title'] ) && '' !== $book->title() ) {
			$product->set_name( isset( $want['subtitle'] ) ? $book->full_title() : $book->title() );
		}

		if ( isset( $want['description'] ) && '' !== $book->description() ) {
			$product->set_description( wpautop( wp_kses_post( $book->description() ) ) );
		}

		if ( isset( $want['attributes'] ) ) {
			$product->set_attributes( self::merge_attributes( $product->get_attributes(), $book ) );
		}

		if ( isset( $want['gtin'] ) && '' !== $book->isbn13() ) {
			if ( method_exists( $product, 'set_global_unique_id' ) ) {
				try {
					$product->set_global_unique_id( $book->isbn13() );
				} catch ( WC_Data_Exception $e ) {
					$notes[] = $e->getMessage();
				}
			} else {
				$notes[] = __( 'This WooCommerce version has no GTIN/ISBN field (it arrived in 9.2), so the ISBN was saved as an attribute only.', 'isbnlab' );
			}
		}

		if ( isset( $want['sku'] ) && '' !== $book->isbn13() ) {
			try {
				$product->set_sku( $book->isbn13() );
			} catch ( WC_Data_Exception $e ) {
				$notes[] = $e->getMessage();
			}
		}

		if ( isset( $want['cover'] ) ) {
			$image_id = self::cover_attachment( $book, $product->get_id() );
			if ( is_wp_error( $image_id ) ) {
				$notes[] = $image_id->get_error_message();
			} elseif ( $image_id ) {
				$product->set_image_id( $image_id );
			}
		}

		// A product filled straight after "Add New" is still an auto-draft, which WordPress deletes after a week.
		if ( 'auto-draft' === $product->get_status() ) {
			$product->set_status( 'draft' );
		}

		$product->update_meta_data( self::META_ISBN, $book->isbn13() );
		$product->save();

		return $notes;
	}

	/**
	 * The product's attributes with the book's details added, replacing earlier ones of the same name.
	 *
	 * @param WC_Product_Attribute[] $existing Current attributes.
	 * @param ISBNLab_Book           $book     The book.
	 * @return WC_Product_Attribute[]
	 */
	private static function merge_attributes( array $existing, ISBNLab_Book $book ) {
		$labels     = self::detail_labels();
		$attributes = array();
		$ours       = array();

		foreach ( $book->details() as $key => $value ) {
			if ( ! isset( $labels[ $key ] ) ) {
				continue;
			}
			$name = $labels[ $key ];

			$attribute = new WC_Product_Attribute();
			$attribute->set_id( 0 );
			$attribute->set_name( $name );
			$attribute->set_options( array( $value ) );
			$attribute->set_visible( true );
			$attribute->set_variation( false );

			$ours[ sanitize_title( $name ) ] = $attribute;
		}

		foreach ( $existing as $slug => $attribute ) {
			$key = sanitize_title( $attribute->get_name() );
			if ( ! isset( $ours[ $key ] ) ) {
				$attributes[ $slug ] = $attribute;
			}
		}

		$position = count( $attributes );
		foreach ( $ours as $key => $attribute ) {
			$attribute->set_position( $position++ );
			$attributes[ $key ] = $attribute;
		}

		return $attributes;
	}

	/**
	 * Labels for ISBNLab_Book::details() keys, used for the preview and as attribute names.
	 *
	 * @return array<string, string>
	 */
	public static function detail_labels() {
		return array(
			'author'    => __( 'Author', 'isbnlab' ),
			'publisher' => __( 'Publisher', 'isbnlab' ),
			'published' => __( 'Published', 'isbnlab' ),
			'format'    => __( 'Format', 'isbnlab' ),
			'pages'     => __( 'Pages', 'isbnlab' ),
			'language'  => __( 'Language', 'isbnlab' ),
			'series'    => __( 'Series', 'isbnlab' ),
			'isbn13'    => __( 'ISBN-13', 'isbnlab' ),
			'isbn10'    => __( 'ISBN-10', 'isbnlab' ),
		);
	}

	/**
	 * The media library image for the book's cover, downloading it the first time.
	 *
	 * @param ISBNLab_Book $book    The book.
	 * @param int          $post_id Product to attach a new image to.
	 * @return int|WP_Error Attachment id, or 0 when the book has no cover.
	 */
	private static function cover_attachment( ISBNLab_Book $book, $post_id ) {
		$url = $book->cover_url();
		if ( '' === $url ) {
			return new WP_Error( 'isbnlab_no_cover', __( 'This book has no cover image.', 'isbnlab' ) );
		}

		$existing = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::META_COVER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- once per Apply click.
				'meta_value'     => $url, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		if ( $existing ) {
			return (int) $existing[0];
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		/* translators: %s: book title. */
		$alt = sprintf( __( 'Cover of %s', 'isbnlab' ), $book->title() );
		$id  = media_sideload_image( $url, $post_id, $alt, 'id' );

		if ( is_wp_error( $id ) ) {
			return new WP_Error(
				'isbnlab_cover_download',
				/* translators: %s: error message. */
				sprintf( __( 'The cover could not be downloaded: %s', 'isbnlab' ), $id->get_error_message() )
			);
		}

		update_post_meta( $id, self::META_COVER, $url );
		update_post_meta( $id, '_wp_attachment_image_alt', $alt );

		return (int) $id;
	}

	/**
	 * Preview HTML for the box.
	 *
	 * @param ISBNLab_Book $book The book.
	 */
	private static function render_preview( ISBNLab_Book $book ) {
		$labels = self::detail_labels();
		?>
		<div class="isbnlab-preview">
			<?php if ( '' !== $book->cover_url() ) : ?>
				<img src="<?php echo esc_url( $book->cover_url() ); ?>" alt="" loading="lazy" />
			<?php endif; ?>
			<div>
				<strong><?php echo esc_html( $book->full_title() ); ?></strong>
				<dl>
					<?php foreach ( $book->details() as $key => $value ) : ?>
						<?php if ( isset( $labels[ $key ] ) ) : ?>
							<dt><?php echo esc_html( $labels[ $key ] ); ?></dt>
							<dd><?php echo esc_html( $value ); ?></dd>
						<?php endif; ?>
					<?php endforeach; ?>
				</dl>
			</div>
		</div>
		<?php if ( '' !== $book->description() ) : ?>
			<p class="isbnlab-description"><?php echo esc_html( wp_trim_words( $book->description(), 40 ) ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Check the nonce and permission for a product AJAX call.
	 *
	 * @return int The product id.
	 */
	private static function check_request() {
		check_ajax_referer( 'isbnlab_product', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id || 'product' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to edit this product.', 'isbnlab' ) ), 403 );
		}

		return $post_id;
	}

	/**
	 * Look up the posted ISBN, or stop with the error.
	 *
	 * @return ISBNLab_Book
	 */
	private static function lookup() {
		$isbn = isset( $_POST['isbn'] ) ? sanitize_text_field( wp_unslash( $_POST['isbn'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in check_request().
		$book = ISBNLab_Client::from_settings()->get_book( $isbn );

		if ( is_wp_error( $book ) ) {
			wp_send_json_error( array( 'message' => $book->get_error_message() ) );
		}

		return $book;
	}
}
