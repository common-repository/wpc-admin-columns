<?php
defined( 'ABSPATH' ) || exit;

class Wpcac_Backend {
	protected static $post_types = [];
	protected static $taxonomies = [];
	protected static $support_columns = [];
	protected static $sortable_columns = [];
	protected static $editable_columns = [];
	protected static $settings = [];
	protected static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		self::$settings = (array) get_option( 'wpcac_settings', [] );

		add_action( 'wp_loaded', [ $this, 'wp_loaded' ] );
		add_action( 'admin_init', [ $this, 'admin_init' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'admin_footer', [ $this, 'admin_columns' ] );

		// ajax
		add_action( 'wp_ajax_wpcac_add_column', [ $this, 'ajax_add_column' ] );
		add_action( 'wp_ajax_wpcac_save_columns', [ $this, 'ajax_save_columns' ] );
		add_action( 'wp_ajax_wpcac_reset_columns', [ $this, 'ajax_reset_columns' ] );
		add_action( 'wp_ajax_wpcac_edit_get', [ $this, 'ajax_edit_get' ] );
		add_action( 'wp_ajax_wpcac_edit_save', [ $this, 'ajax_edit_save' ] );
		add_action( 'wp_ajax_wpcac_search_terms', [ $this, 'ajax_search_terms' ] );
		add_action( 'wp_ajax_wpcac_search_tags', [ $this, 'ajax_search_tags' ] );
		add_action( 'wp_ajax_wpcac_product_variations', [ $this, 'ajax_product_variations' ] );
		add_action( 'wp_ajax_wpcac_intro_done', [ $this, 'ajax_intro_done' ] );

		// duplicate
		add_action( 'admin_action_wpcac_duplicate', [ $this, 'action_duplicate' ] );

		// save posts
		add_action( 'save_post', [ $this, 'save_post' ], 10, 2 );

		// update user
		add_action( 'wp_update_user', [ $this, 'update_user' ] );

		// settings
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );
		add_filter( 'plugin_action_links', [ $this, 'action_links' ], 10, 2 );
		add_filter( 'plugin_row_meta', [ $this, 'row_meta' ], 10, 2 );
	}

	function wp_loaded() {
		self::$post_types       = get_post_types(); // get all registered post types
		self::$taxonomies       = get_taxonomies(); // get all registered taxonomies
		self::$support_columns  = (array) apply_filters( 'wpcac_support_columns', array_merge( self::$post_types, self::$taxonomies, [
			'users'         => 'users',
			'plugins'       => 'plugins',
			'shop_order'    => 'shop_order',
			'edit-comments' => 'edit-comments'
		] ) );
		self::$sortable_columns = (array) apply_filters( 'wpcac_sortable_columns', [
			'id'              => 'ID',
			'slug'            => 'name',
			'title'           => 'title',
			'modified'        => 'modified',
			'published'       => 'date',
			'product_id'      => 'ID',
			'product_slug'    => 'name',
			'product_name'    => 'title',
			'user_id'         => 'ID',
			'user_email'      => 'email',
			'user_login'      => 'login',
			'user_registered' => 'registered',
			'order_id'        => 'ID',
		] );
		self::$editable_columns = (array) apply_filters( 'wpcac_editable_columns', [
			'featured_image',
			'custom_field',
			'user_meta',
			'term_meta',
			'taxonomy',
			'product_sku',
			'product_image',
			'product_gallery',
			'product_taxonomy',
			'product_meta',
		] );
	}

	function admin_init() {
		// Post types
		foreach ( self::$post_types as $post_type ) {
			add_filter( 'manage_edit-' . $post_type . '_columns', [ $this, 'filter_columns' ], 9999 );
			add_action( 'manage_' . $post_type . '_posts_custom_column', [ $this, 'custom_column' ], 9999, 2 );
			add_filter( 'manage_edit-' . $post_type . '_sortable_columns', [ $this, 'sortable_columns' ], 9999 );
		}

		// Taxonomies
		foreach ( self::$taxonomies as $taxonomy ) {
			add_filter( 'manage_edit-' . $taxonomy . '_columns', [ $this, 'filter_columns' ], 9999 );
			add_filter( 'manage_' . $taxonomy . '_custom_column', [ $this, 'taxonomy_custom_column' ], 9999, 3 );
		}

		// Plugins
		add_filter( 'manage_plugins_columns', [ $this, 'filter_columns' ], 9999 );
		add_action( 'manage_plugins_custom_column', [ $this, 'plugins_custom_column' ], 9999, 3 );

		// Comments
		add_filter( 'manage_edit-comments_columns', [ $this, 'filter_columns' ], 9999 );
		add_action( 'manage_comments_custom_column', [ $this, 'comments_custom_column' ], 9999, 2 );

		// Media
		add_filter( 'manage_media_columns', [ $this, 'filter_columns' ], 9999 );
		add_action( 'manage_media_custom_column', [ $this, 'media_custom_column' ], 9999, 2 );

		// Sortable
		add_filter( 'pre_get_posts', [ $this, 'sortable_query' ], 9999 );
	}

	function enqueue_scripts() {
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );

		// hint
		wp_enqueue_style( 'hint', WPCAC_URI . 'assets/css/hint.css' );

		// intro
		wp_enqueue_style( 'intro', WPCAC_URI . 'assets/libs/intro/introjs.css' );
		wp_enqueue_script( 'intro', WPCAC_URI . 'assets/libs/intro/intro.js', [ 'jquery' ], WPCAC_VERSION, true );

		// select2
		wp_enqueue_style( 'select2', WPCAC_URI . 'assets/libs/select2/select2.min.css' );
		wp_enqueue_script( 'select2', WPCAC_URI . 'assets/libs/select2/select2.min.js', [ 'jquery' ], WPCAC_VERSION, true );

		if ( self::get_setting( 'json_editor', 'no' ) === 'yes' ) {
			wp_enqueue_script( 'json-editor', WPCAC_URI . 'assets/libs/json-editor/jquery.json-editor.min.js', [ 'jquery' ], WPCAC_VERSION, true );
		}

		wp_enqueue_style( 'wpcac-backend', WPCAC_URI . 'assets/css/backend.css', [], WPCAC_VERSION );
		wp_add_inline_style( 'wpcac-backend', self::inline_css() );

		wp_enqueue_script( 'wpcac-backend', WPCAC_URI . 'assets/js/backend.js', [
			'jquery',
			'jquery-ui-sortable',
			'jquery-ui-dialog',
			'wp-color-picker'
		], WPCAC_VERSION, true );
		wp_localize_script( 'wpcac-backend', 'wpcac_vars', [
				'nonce'             => wp_create_nonce( 'wpcac-security' ),
				'remove_confirm'    => esc_html__( 'Are you sure?', 'wpc-admin-columns' ),
				'reset_confirm'     => esc_html__( 'All column organization on this page will be removed. Are you sure?', 'wpc-admin-columns' ),
				'copy'              => esc_html__( 'Copy', 'wpc-admin-columns' ),
				'copied'            => esc_html__( 'Copied', 'wpc-admin-columns' ),
				'enabled'           => esc_html__( 'Enabled', 'wpc-admin-columns' ),
				'disabled'          => esc_html__( 'Disabled', 'wpc-admin-columns' ),
				'horizontal_scroll' => apply_filters( 'wpcac_horizontal_scroll', 'yes' ),
				'media_add'         => esc_html__( 'Add Image', 'wpc-admin-columns' ),
				'media_title'       => esc_html__( 'Custom Image', 'wpc-admin-columns' ),
				'intro_done'        => esc_html__( 'Got it!', 'wpc-admin-columns' ),
				'intro_text'        => esc_html__( 'Click here to take control of columns. Add, remove, or rearrange them as you wish.', 'wpc-admin-columns' ),
				'intro'             => get_user_meta( get_current_user_id(), 'wpcac_intro_' . WPCAC_VERSION, true )
			]
		);
	}

	function get_screen_key() {
		$taxonomy   = get_current_screen()->taxonomy ?: '';
		$post_type  = get_current_screen()->post_type ?: '';
		$screen_id  = get_current_screen()->id ?: '';
		$screen_key = ! empty( $taxonomy ) ? $taxonomy : ( ! empty( $post_type ) ? $post_type : $screen_id );

		return apply_filters( 'wpcac_get_screen_key', $screen_key );
	}

	function get_columns_name( $screen_key = '' ) {
		if ( self::get_setting( 'personalized', 'yes' ) === 'yes' ) {
			$columns_name = 'wpcac_columns_' . get_current_user_id() . '_' . $screen_key;
		} else {
			$columns_name = 'wpcac_columns_' . $screen_key;
		}

		return apply_filters( 'wpcac_get_columns_name', $columns_name, $screen_key );
	}

	function inline_css() {
		$screen_key = self::get_screen_key();

		if ( empty( $screen_key ) ) {
			return null;
		}

		$columns_name   = self::get_columns_name( $screen_key );
		$saved_columns  = get_option( $columns_name, [] );
		$system_columns = get_column_headers( get_current_screen() );

		foreach ( $system_columns as $sc_k => $sc_v ) {
			$system_columns[ $sc_k ] = [ 'name' => $sc_v, 'type' => 'system', 'enable' => 'yes' ];
		}

		$columns = $saved_columns + $system_columns;
		$css     = '';

		if ( ! empty( $columns ) ) {
			foreach ( $columns as $key => $column ) {
				$column = array_merge( [
					'enable'     => 'yes',
					'type'       => 'system',
					'name'       => '',
					'width'      => '',
					'width_unit' => 'px',
					'text_align' => 'start',
					'text_color' => '',
				], $column );

				$css .= ' table.wp-list-table .column-' . esc_attr( $key ) . ' { ';
				$css .= 'text-align: ' . $column['text_align'] . ';';

				if ( ! empty( $column['width'] ) ) {
					$css .= 'width: ' . $column['width'] . $column['width_unit'] . ';';
				}

				if ( ! empty( $column['text_color'] ) ) {
					$css .= 'color: ' . $column['text_color'] . ';';
				}

				$css .= ' } ';
			}
		}

		return $css;
	}

	function admin_columns() {
		$screen_key = self::get_screen_key();

		if ( empty( $screen_key ) || ! isset( self::$support_columns[ $screen_key ] ) ) {
			return null;
		}

		$columns_name   = self::get_columns_name( $screen_key );
		$saved_columns  = get_option( $columns_name, [] );
		$system_columns = get_column_headers( get_current_screen() );

		foreach ( $system_columns as $sc_k => $sc_v ) {
			$system_columns[ $sc_k ] = [ 'name' => $sc_v, 'type' => 'system', 'enable' => 'yes' ];
		}

		$columns = $saved_columns + $system_columns;
		?>
        <div class="wpcac-actions alignleft actions">
            <button type="button" class="wpcac-btn button">
                <span class="dashicons dashicons-admin-settings"></span>
				<?php esc_html_e( 'Columns', 'wpc-admin-columns' ); ?>
            </button>
        </div>
        <div class="wpcac-popup wpcac-popup-columns" id="wpcac-popup-columns" data-title="<?php echo esc_attr( sprintf( /* translators: screen */ esc_html__( 'Columns Manager › %s', 'wpc-admin-columns' ), $screen_key ) ); ?>">
			<?php
			if ( ( $screen_key === 'shop_order' ) && ( get_option( 'woocommerce_custom_orders_table_enabled' ) === 'yes' ) ) {
				echo '<div style="color: #c9356e;padding: 10px;border-radius: 4px;border: 1px dashed #c9356e;margin-bottom: 10px;">* Manage order columns in HPOS (High-performance order storage) mode only available on Premium Version. Click <a href="https://wpclever.net/downloads/wpc-admin-columns/?utm_source=pro&utm_medium=wpcac&utm_campaign=wporg" target="_blank">here</a> to buy for just $29!</div>';
			}

			if ( $screen_key === 'users' ) {
				echo '<div style="color: #c9356e;padding: 10px;border-radius: 4px;border: 1px dashed #c9356e;margin-bottom: 10px;">* Manage user columns only available on Premium Version. Click <a href="https://wpclever.net/downloads/wpc-admin-columns/?utm_source=pro&utm_medium=wpcac&utm_campaign=wporg" target="_blank">here</a> to buy for just $29!</div>';
			}
			?>
            <div class="wpcac-columns">
				<?php
				if ( ! empty( $columns ) ) {
					foreach ( $columns as $key => $column ) {
						self::get_column( $key, $column, $system_columns, $screen_key );
					}
				}
				?>
            </div>
            <div class="wpcac-btns" data-screen_key="<?php echo esc_attr( $screen_key ); ?>">
                <button type="button" class="wpcac-add-btn button"><?php esc_html_e( '+ Add column', 'wpc-admin-columns' ); ?></button>
                <a class="wpcac-reset-btn" href="#"><?php esc_html_e( 'reset', 'wpc-admin-columns' ); ?></a>
                <button type="button" class="wpcac-save-btn button button-primary"><?php esc_html_e( 'Save Changes', 'wpc-admin-columns' ); ?></button>
            </div>
        </div>
        <div class="wpcac-popup wpcac-popup-edit" id="wpcac-popup-edit"></div>
        <div class="wpcac-popup wpcac-popup-view" id="wpcac-popup-view"></div>
		<?php
	}

	function get_column( $key, $column, $system_columns = [], $screen_key = '' ) {
		$column = array_merge( [
			'enable'     => 'yes',
			'type'       => 'id',
			'name'       => '',
			'field'      => '',
			'text'       => '',
			'taxonomy'   => '',
			'data_type'  => 'textarea',
			'choices'    => '',
			'sortable'   => 'no',
			'editable'   => 'no',
			'width'      => '',
			'width_unit' => 'px',
			'text_align' => 'start',
			'text_color' => '',
		], $column );

		$type      = $column['type'];
		$taxonomy  = $column['taxonomy'];
		$is_system = $type === 'system';
		$is_enable = ! ( isset( $column['enable'] ) && ( $column['enable'] === 'no' ) );

		if ( $is_system && isset( $system_columns[ $key ] ) ) {
			$name = ! empty( $system_columns[ $key ]['name'] ) ? $system_columns[ $key ]['name'] : $key;
		} else {
			$name = ! empty( $column['name'] ) ? $column['name'] : '';
		}

		if ( $key !== 'cb' && $key !== 'handle' ) {
			?>
            <div class="<?php echo esc_attr( 'wpcac-column wpcac-column-' . $type . ' wpcac-column-' . $key . ' ' . ( $is_enable ? 'wpcac-column-enable' : '' ) ); ?>" data-column="<?php echo esc_attr( $key ); ?>">
                <div class="wpcac-column-heading">
                    <span class="move"></span><span class="title"><span class="name"><?php echo esc_html( wp_strip_all_tags( $name ) ); ?></span><span class="type"><?php echo esc_attr( $is_system ? $key : $type ); ?></span></span><span class="enable hint--left" aria-label="<?php echo esc_attr( $is_enable ? esc_attr__( 'Enabled', 'wpc-admin-columns' ) : esc_attr__( 'Disabled', 'wpc-admin-columns' ) ); ?>"><a class="wpcac-enable-btn button <?php echo esc_attr( $is_enable ? 'enabled button-primary' : 'disabled' ); ?>"></a></span>
                </div>
                <div class="wpcac-column-content">
                    <input type="hidden" name="wpcac_columns[<?php echo esc_attr( $key ); ?>][enable]" class="wpcac_column_enable" value="<?php echo esc_attr( $column['enable'] ); ?>"/>
					<?php if ( ! $is_system ) { ?>
                        <div class="wpcac-column-content-line">
                            <div class="wpcac-column-content-label"><?php esc_html_e( 'Name', 'wpc-admin-columns' ); ?></div>
                            <div class="wpcac-column-content-value">
                                <input type="text" name="wpcac_columns[<?php echo esc_attr( $key ); ?>][name]" class="wpcac_column_name" value="<?php echo esc_attr( $name ); ?>"/>
                            </div>
                        </div>
                        <div class="wpcac-column-content-line">
                            <div class="wpcac-column-content-label"><?php esc_html_e( 'Type', 'wpc-admin-columns' ); ?></div>
                            <div class="wpcac-column-content-value">
                                <select name="wpcac_columns[<?php echo esc_attr( $key ); ?>][type]" class="wpcac_column_type">
									<?php
									$column_types = self::get_column_types();

									foreach ( $column_types as $column_key => $column_type ) {
										if ( empty( $column_type['options'] ) ) {
											continue;
										}

										if ( ! empty( $column_type['type'] ) ) {
											if ( ( $column_type['type'] === 'post_type' ) && ! in_array( $screen_key, self::$post_types ) ) {
												continue;
											}

											if ( ( $column_type['type'] === 'taxonomy' ) && ! in_array( $screen_key, self::$taxonomies ) ) {
												continue;
											}
										}

										if ( ( isset( $column_type['include'] ) && ! in_array( $screen_key, $column_type['include'] ) ) || ( isset( $column_type['exclude'] ) && in_array( $screen_key, $column_type['exclude'] ) ) ) {
											continue;
										}

										echo '<optgroup label="' . esc_attr( ! empty( $column_type['name'] ) ? $column_type['name'] : $column_key ) . '">';

										foreach ( $column_type['options'] as $ct_k => $ct_v ) {
											echo '<option value="' . esc_attr( $ct_k ) . '" ' . selected( $type, $ct_k, false ) . '>' . esc_html( $ct_v ) . '</option>';
										}

										echo '</optgroup>';
									}
									?>
                                </select>
                            </div>
                        </div>
                        <div class="wpcac-column-content-line wpcac-hide-if-type-default wpcac-show-if-type-custom_text">
                            <div class="wpcac-column-content-label"><?php esc_html_e( 'Content', 'wpc-admin-columns' ); ?></div>
                            <div class="wpcac-column-content-value">
                                <textarea name="wpcac_columns[<?php echo esc_attr( $key ); ?>][text]"><?php echo esc_textarea( $column['text'] ); ?></textarea>
                            </div>
                        </div>
                        <div class="wpcac-column-content-line wpcac-hide-if-type-default wpcac-show-if-type-custom_field wpcac-show-if-type-product_meta wpcac-show-if-type-user_meta wpcac-show-if-type-term_meta">
                            <div class="wpcac-column-content-label"><?php esc_html_e( 'Field', 'wpc-admin-columns' ); ?></div>
                            <div class="wpcac-column-content-value">
								<?php
								if ( $meta_keys = self::get_meta_keys( $screen_key ) ) {
									echo '<select name="wpcac_columns[' . esc_attr( $key ) . '][field]" class="wpcac_meta_fields">';

									foreach ( $meta_keys as $meta_key ) {
										echo '<option value="' . esc_attr( $meta_key ) . '" ' . selected( $column['field'], $meta_key, false ) . '>' . esc_html( $meta_key ) . '</option>';
									}

									echo '</select>';
								} else {
									esc_html_e( 'Have no fields.', 'wpc-admin-columns' );
								}
								?>
                            </div>
                        </div>
                        <div class="wpcac-column-content-line wpcac-hide-if-type-default wpcac-show-if-type-custom_field wpcac-show-if-type-product_meta wpcac-show-if-type-user_meta wpcac-show-if-type-term_meta">
                            <div class="wpcac-column-content-label"><?php esc_html_e( 'Data type', 'wpc-admin-columns' ); ?></div>
                            <div class="wpcac-column-content-value">
                                <select name="wpcac_columns[<?php echo esc_attr( $key ); ?>][data_type]" class="wpcac_data_type">
                                    <option value="text" <?php selected( 'text', $column['data_type'] ); ?>><?php esc_html_e( 'Text', 'wpc-admin-columns' ); ?></option>
                                    <option value="number" <?php selected( 'number', $column['data_type'] ); ?>><?php esc_html_e( 'Number', 'wpc-admin-columns' ); ?></option>
                                    <option value="email" <?php selected( 'email', $column['data_type'] ); ?>><?php esc_html_e( 'Email', 'wpc-admin-columns' ); ?></option>
                                    <option value="url" <?php selected( 'url', $column['data_type'] ); ?>><?php esc_html_e( 'URL', 'wpc-admin-columns' ); ?></option>
                                    <option value="color" <?php selected( 'color', $column['data_type'] ); ?>><?php esc_html_e( 'Color', 'wpc-admin-columns' ); ?></option>
                                    <option value="image" <?php selected( 'image', $column['data_type'] ); ?>><?php esc_html_e( 'Image', 'wpc-admin-columns' ); ?></option>
                                    <option value="multiple_images" <?php selected( 'multiple_images', $column['data_type'] ); ?>><?php esc_html_e( 'Multiple Images', 'wpc-admin-columns' ); ?></option>
                                    <option value="true_false" <?php selected( 'true_false', $column['data_type'] ); ?>><?php esc_html_e( 'True/False', 'wpc-admin-columns' ); ?></option>
                                    <option value="yes_no" <?php selected( 'yes_no', $column['data_type'] ); ?>><?php esc_html_e( 'Yes/No', 'wpc-admin-columns' ); ?></option>
                                    <option value="on_off" <?php selected( 'on_off', $column['data_type'] ); ?>><?php esc_html_e( 'On/Off', 'wpc-admin-columns' ); ?></option>
                                    <option value="select" <?php selected( 'select', $column['data_type'] ); ?>><?php esc_html_e( 'Select', 'wpc-admin-columns' ); ?></option>
                                    <option value="multiple_select" <?php selected( 'multiple_select', $column['data_type'] ); ?>><?php esc_html_e( 'Multiple Select', 'wpc-admin-columns' ); ?></option>
                                    <option value="textarea" <?php selected( 'textarea', $column['data_type'] ); ?>><?php esc_html_e( 'Textarea', 'wpc-admin-columns' ); ?></option>
                                    <option value="universal" <?php selected( 'universal', $column['data_type'] ); ?>><?php esc_html_e( 'Universal', 'wpc-admin-columns' ); ?></option>
									<?php if ( self::get_setting( 'json_editor', 'no' ) === 'yes' ) { ?>
                                        <option value="json" <?php selected( 'json', $column['data_type'] ); ?>><?php esc_html_e( 'JSON (JavaScript Object Notation)', 'wpc-admin-columns' ); ?></option>
									<?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="wpcac-column-content-line wpcac-hide-if-data-type-default wpcac-show-if-data-type-select wpcac-show-if-data-type-multiple_select">
                            <div class="wpcac-column-content-label"><?php esc_html_e( 'Choices', 'wpc-admin-columns' ); ?></div>
                            <div class="wpcac-column-content-value">
                                <p class="description"><?php esc_html_e( 'Enter each choice on a new line. For more control, you may specify both a value and label like this: red : Red', 'wpc-admin-columns' ); ?></p>
                                <textarea name="wpcac_columns[<?php echo esc_attr( $key ); ?>][choices]"><?php echo esc_textarea( $column['choices'] ); ?></textarea>
                            </div>
                        </div>
                        <div class="wpcac-column-content-line wpcac-hide-if-type-default wpcac-show-if-type-taxonomy wpcac-show-if-type-product_taxonomy">
                            <div class="wpcac-column-content-label"><?php esc_html_e( 'Taxonomy', 'wpc-admin-columns' ); ?></div>
                            <div class="wpcac-column-content-value">
								<?php if ( $taxonomies = get_object_taxonomies( $screen_key, 'objects' ) ) {
									echo '<select name="wpcac_columns[' . esc_attr( $key ) . '][taxonomy]">';

									foreach ( $taxonomies as $tx ) {
										echo '<option value="' . esc_attr( $tx->name ) . '" ' . selected( $taxonomy, $tx->name, false ) . '>' . esc_html( $tx->label ) . '</option>';
									}

									echo '</select>';
								} ?>
                            </div>
                        </div>
						<?php
						$editable_class = 'wpcac-column-content-line wpcac-hide-if-type-default';

						if ( is_array( self::$editable_columns ) && ! empty( self::$editable_columns ) ) {
							foreach ( self::$editable_columns as $ec ) {
								$editable_class .= ' wpcac-show-if-type-' . $ec;
							}
						}
						?>
                        <div class="<?php echo esc_attr( $editable_class ); ?>">
                            <div class="wpcac-column-content-label"><?php esc_html_e( 'Editable', 'wpc-admin-columns' ); ?></div>
                            <div class="wpcac-column-content-value">
                                <select name="wpcac_columns[<?php echo esc_attr( $key ); ?>][editable]">
                                    <option value="no" <?php selected( 'no', $column['editable'] ); ?>><?php esc_html_e( 'No', 'wpc-admin-columns' ); ?></option>
                                    <option value="yes" <?php selected( 'yes', $column['editable'] ); ?>><?php esc_html_e( 'Yes', 'wpc-admin-columns' ); ?></option>
                                </select>
                            </div>
                        </div>
						<?php
						$sortable_class = 'wpcac-column-content-line wpcac-hide-if-type-default wpcac-show-if-type-custom_field wpcac-show-if-type-product_meta wpcac-show-if-type-user_meta wpcac-show-if-type-term_meta';

						if ( is_array( self::$sortable_columns ) && ! empty( self::$sortable_columns ) ) {
							foreach ( self::$sortable_columns as $sk => $sc ) {
								$sortable_class .= ' wpcac-show-if-type-' . $sk;
							}
						}
						?>
                        <div class="<?php echo esc_attr( $sortable_class ); ?>">
                            <div class="wpcac-column-content-label"><?php esc_html_e( 'Sortable', 'wpc-admin-columns' ); ?></div>
                            <div class="wpcac-column-content-value">
                                <select name="wpcac_columns[<?php echo esc_attr( $key ); ?>][sortable]">
                                    <option value="no" <?php selected( 'no', $column['sortable'] ); ?>><?php esc_html_e( 'No', 'wpc-admin-columns' ); ?></option>
                                    <option value="yes" <?php selected( 'yes', $column['sortable'] ); ?>><?php esc_html_e( 'Yes', 'wpc-admin-columns' ); ?></option>
                                </select>
                            </div>
                        </div>
					<?php } else { ?>
                        <input type="hidden" name="wpcac_columns[<?php echo esc_attr( $key ); ?>][type]" value="system"/>
                        <input type="hidden" name="wpcac_columns[<?php echo esc_attr( $key ); ?>][name]" value="<?php echo esc_attr( $name ); ?>"/>
					<?php } ?>
                    <div class="wpcac-column-content-line">
                        <div class="wpcac-column-content-label"><?php esc_html_e( 'Width', 'wpc-admin-columns' ); ?></div>
                        <div class="wpcac-column-content-value">
                            <input type="number" class="small-text float-left" step="1" name="wpcac_columns[<?php echo esc_attr( $key ); ?>][width]" value="<?php echo esc_attr( $column['width'] ); ?>"/>
                            <select name="wpcac_columns[<?php echo esc_attr( $key ); ?>][width_unit]">
                                <option value="px" <?php selected( 'px', $column['width_unit'] ); ?>>px</option>
                                <option value="%" <?php selected( '%', $column['width_unit'] ); ?>>%</option>
                            </select>
                        </div>
                    </div>
                    <div class="wpcac-column-content-line">
                        <div class="wpcac-column-content-label"><?php esc_html_e( 'Text align', 'wpc-admin-columns' ); ?></div>
                        <div class="wpcac-column-content-value">
                            <select name="wpcac_columns[<?php echo esc_attr( $key ); ?>][text_align]">
                                <option value="start" <?php selected( 'start', $column['text_align'] ); ?>><?php esc_html_e( 'start', 'wpc-admin-columns' ); ?></option>
                                <option value="center" <?php selected( 'center', $column['text_align'] ); ?>><?php esc_html_e( 'center', 'wpc-admin-columns' ); ?></option>
                                <option value="end" <?php selected( 'end', $column['text_align'] ); ?>><?php esc_html_e( 'end', 'wpc-admin-columns' ); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="wpcac-column-content-line">
                        <div class="wpcac-column-content-label"><?php esc_html_e( 'Text color', 'wpc-admin-columns' ); ?></div>
                        <div class="wpcac-column-content-value">
                            <input type="text" name="wpcac_columns[<?php echo esc_attr( $key ); ?>][text_color]" value="<?php echo esc_attr( $column['text_color'] ); ?>" class="wpcac-color-picker"/>
                        </div>
                    </div>
					<?php if ( ! $is_system ) { ?>
                        <div class="wpcac-column-content-line">
                            <div class="wpcac-column-content-label">&nbsp;</div>
                            <div class="wpcac-column-content-value">
                                <a href="#" class="wpcac-remove remove"><?php esc_html_e( 'remove', 'wpc-admin-columns' ); ?></a>
                            </div>
                        </div>
					<?php } ?>
                </div>
            </div>
			<?php
		}
	}

	function get_column_types() {
		return apply_filters( 'wpcac_column_types', [
			'product'    => [
				'name'    => esc_html__( 'Product', 'wpc-admin-columns' ),
				'type'    => 'post_type',
				'include' => [ 'product' ],
				'options' => [
					'product_id'                => esc_html__( 'Product ID', 'wpc-admin-columns' ),
					'product_sku'               => esc_html__( 'Product SKU', 'wpc-admin-columns' ),
					'product_type'              => esc_html__( 'Product type', 'wpc-admin-columns' ),
					'product_type_icon'         => esc_html__( 'Product type icon', 'wpc-admin-columns' ),
					'product_image'             => esc_html__( 'Product featured image', 'wpc-admin-columns' ),
					'product_gallery'           => esc_html__( 'Product gallery images', 'wpc-admin-columns' ),
					'product_name'              => esc_html__( 'Product name', 'wpc-admin-columns' ),
					'product_slug'              => esc_html__( 'Product slug', 'wpc-admin-columns' ),
					'product_status'            => esc_html__( 'Product status', 'wpc-admin-columns' ),
					'product_description'       => esc_html__( 'Product description', 'wpc-admin-columns' ),
					'product_short_description' => esc_html__( 'Product short description', 'wpc-admin-columns' ),
					'product_weight'            => esc_html__( 'Product weight', 'wpc-admin-columns' ),
					'product_length'            => esc_html__( 'Product length', 'wpc-admin-columns' ),
					'product_width'             => esc_html__( 'Product width', 'wpc-admin-columns' ),
					'product_height'            => esc_html__( 'Product height', 'wpc-admin-columns' ),
					'product_dimensions'        => esc_html__( 'Product dimensions', 'wpc-admin-columns' ),
					'product_taxonomy'          => esc_html__( 'Product taxonomy', 'wpc-admin-columns' ),
					'product_variations'        => esc_html__( 'Product variations', 'wpc-admin-columns' ),
					'product_actions'           => esc_html__( 'Product actions', 'wpc-admin-columns' ),
					'product_meta'              => esc_html__( 'Product meta', 'wpc-admin-columns' ),
					'product_duplicate'         => esc_html__( 'Duplicate', 'wpc-admin-columns' ),
				]
			],
			'shop_order' => [
				'name'    => esc_html__( 'Order', 'wpc-admin-columns' ),
				'type'    => 'post_type',
				'include' => [ 'shop_order' ],
				'options' => [
					'order_id'        => esc_html__( 'Order ID', 'wpc-admin-columns' ),
					'order_products'  => esc_html__( 'Order products', 'wpc-admin-columns' ),
					'billing_phone'   => esc_html__( 'Billing phone', 'wpc-admin-columns' ),
					'shipping_phone'  => esc_html__( 'Shipping phone', 'wpc-admin-columns' ),
					'order_duplicate' => esc_html__( 'Duplicate', 'wpc-admin-columns' ),
				],
			],
			'users'      => [
				'name'    => esc_html__( 'User', 'wpc-admin-columns' ),
				'include' => [ 'users' ],
				'options' => [
					'user_id'          => esc_html__( 'User ID', 'wpc-admin-columns' ),
					'user_email'       => esc_html__( 'User email', 'wpc-admin-columns' ),
					'user_login'       => esc_html__( 'User login name', 'wpc-admin-columns' ),
					'user_firstname'   => esc_html__( 'User first name', 'wpc-admin-columns' ),
					'user_lastname'    => esc_html__( 'User last name', 'wpc-admin-columns' ),
					'user_nicename'    => esc_html__( 'User nice name', 'wpc-admin-columns' ),
					'nickname'         => esc_html__( 'User nickname', 'wpc-admin-columns' ),
					'display_name'     => esc_html__( 'User display name', 'wpc-admin-columns' ),
					'user_registered'  => esc_html__( 'User registration date', 'wpc-admin-columns' ),
					'user_description' => esc_html__( 'User description', 'wpc-admin-columns' ),
					'user_url'         => esc_html__( 'User URL', 'wpc-admin-columns' ),
					'user_level'       => esc_html__( 'User level', 'wpc-admin-columns' ),
					'user_status'      => esc_html__( 'User status', 'wpc-admin-columns' ),
					'user_meta'        => esc_html__( 'User meta', 'wpc-admin-columns' ),
				],
			],
			'plugins'    => [
				'name'    => esc_html__( 'Plugin', 'wpc-admin-columns' ),
				'include' => [ 'plugins' ],
				'options' => [
					'plugin_name'    => esc_html__( 'Name', 'wpc-admin-columns' ),
					'plugin_author'  => esc_html__( 'Author', 'wpc-admin-columns' ),
					'plugin_version' => esc_html__( 'Version', 'wpc-admin-columns' ),
					'plugin_icon'    => esc_html__( 'Icon', 'wpc-admin-columns' ),
					'plugin_desc'    => esc_html__( 'Description', 'wpc-admin-columns' ),
				],
			],
			'comments'   => [
				'name'    => esc_html__( 'Comment', 'wpc-admin-columns' ),
				'include' => [ 'edit-comments' ],
				'options' => [
					'comment_id'           => esc_html__( 'ID', 'wpc-admin-columns' ),
					'comment_type'         => esc_html__( 'Type', 'wpc-admin-columns' ),
					'comment_date'         => esc_html__( 'Date', 'wpc-admin-columns' ),
					'comment_author'       => esc_html__( 'Author', 'wpc-admin-columns' ),
					'comment_author_email' => esc_html__( 'Author email', 'wpc-admin-columns' ),
					'comment_author_url'   => esc_html__( 'Author URL', 'wpc-admin-columns' ),
					'comment_words_count'  => esc_html__( 'Words count', 'wpc-admin-columns' ),
					'comment_post'         => esc_html__( 'Post', 'wpc-admin-columns' ),
				],
			],
			'attachment' => [
				'name'    => esc_html__( 'Attachment', 'wpc-admin-columns' ),
				'include' => [ 'attachment' ],
				'options' => [
					'attachment_id'        => esc_html__( 'ID', 'wpc-admin-columns' ),
					'attachment_author'    => esc_html__( 'Author', 'wpc-admin-columns' ),
					'attachment_published' => esc_html__( 'Published', 'wpc-admin-columns' ),
					'attachment_modified'  => esc_html__( 'Last modified', 'wpc-admin-columns' ),
				],
			],
			'general'    => [
				'name'    => esc_html__( 'General', 'wpc-admin-columns' ),
				'type'    => 'post_type',
				'exclude' => [ 'product', 'shop_order', 'attachment' ],
				'options' => [
					'id'             => esc_html__( 'ID', 'wpc-admin-columns' ),
					'slug'           => esc_html__( 'Slug', 'wpc-admin-columns' ),
					'title'          => esc_html__( 'Title', 'wpc-admin-columns' ),
					'status'         => esc_html__( 'Status', 'wpc-admin-columns' ),
					'excerpt'        => esc_html__( 'Excerpt', 'wpc-admin-columns' ),
					'words_count'    => esc_html__( 'Words count', 'wpc-admin-columns' ),
					'author'         => esc_html__( 'Author', 'wpc-admin-columns' ),
					'published'      => esc_html__( 'Published', 'wpc-admin-columns' ),
					'modified'       => esc_html__( 'Last modified', 'wpc-admin-columns' ),
					'featured_image' => esc_html__( 'Featured image', 'wpc-admin-columns' ),
					'comment_status' => esc_html__( 'Comment status', 'wpc-admin-columns' ),
					'comment_count'  => esc_html__( 'Comment count', 'wpc-admin-columns' ),
					'ping_status'    => esc_html__( 'Ping status', 'wpc-admin-columns' ),
					'taxonomy'       => esc_html__( 'Taxonomy', 'wpc-admin-columns' ),
					'actions'        => esc_html__( 'Actions', 'wpc-admin-columns' ),
					'duplicate'      => esc_html__( 'Duplicate', 'wpc-admin-columns' ),
				]
			],
			'taxonomy'   => [
				'name'    => esc_html__( 'Taxonomy', 'wpc-admin-columns' ),
				'type'    => 'taxonomy',
				'options' => [
					'term_id'   => esc_html__( 'Term ID', 'wpc-admin-columns' ),
					'term_name' => esc_html__( 'Term name', 'wpc-admin-columns' ),
					'term_slug' => esc_html__( 'Term slug', 'wpc-admin-columns' ),
					'term_desc' => esc_html__( 'Term description', 'wpc-admin-columns' ),
					'term_meta' => esc_html__( 'Term meta', 'wpc-admin-columns' ),
				]
			],
			'custom'     => [
				'name'    => esc_html__( 'Custom', 'wpc-admin-columns' ),
				'exclude' => [ 'plugins' ],
				'options' => [
					'custom_field' => esc_html__( 'Custom field', 'wpc-admin-columns' ),
					'custom_text'  => esc_html__( 'Custom text/shortcode', 'wpc-admin-columns' ),
				]
			]
		] );
	}

	function ajax_add_column() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpcac-security' ) ) {
			die( 'Permissions check failed!' );
		}

		$key        = 'wpcac_' . self::generate_key();
		$screen_key = sanitize_key( $_POST['screen_key'] ?? '' );

		self::get_column( $key, [], [], $screen_key );

		wp_die();
	}

	function ajax_save_columns() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpcac-security' ) ) {
			die( 'Permissions check failed!' );
		}

		$screen_key = sanitize_key( $_POST['screen_key'] ?? '' );
		$form_data  = sanitize_post( $_POST['form_data'] ?? '' );

		if ( ! empty( $screen_key ) && ! empty( $form_data ) ) {
			$columns = [];
			parse_str( $form_data, $columns );

			if ( isset( $columns['wpcac_columns'] ) && is_array( $columns['wpcac_columns'] ) ) {
				$columns_name = self::get_columns_name( $screen_key );
				update_option( $columns_name, $columns['wpcac_columns'] );
			}
		}

		wp_die();
	}

	function ajax_reset_columns() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpcac-security' ) ) {
			die( 'Permissions check failed!' );
		}

		$screen_key = sanitize_key( $_POST['screen_key'] ?? '' );

		if ( ! empty( $screen_key ) ) {
			$columns_name = self::get_columns_name( $screen_key );
			delete_option( $columns_name );
		}

		wp_die();
	}

	function ajax_edit_get() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpcac-security' ) ) {
			die( 'Permissions check failed!' );
		}

		$post_id = absint( sanitize_key( $_POST['id'] ?? 0 ) );
		$user_id = absint( sanitize_key( $_POST['uid'] ?? 0 ) );
		$term_id = absint( sanitize_key( $_POST['tid'] ?? 0 ) );
		$field   = sanitize_text_field( $_POST['field'] ?? '' );
		$type    = sanitize_text_field( $_POST['type'] ?? '' );
		$name    = sanitize_text_field( $_POST['name'] ?? '' );
		$key     = sanitize_text_field( $_POST['key'] ?? '' );

		if ( ! empty( $field ) ) {
			if ( ! empty( $term_id ) ) {
				// custom field
				$field_value = get_term_meta( $term_id, $field, true ) ?: '';

				if ( $type === 'multiple_select' ) {
					$value = is_array( $field_value ) ? $field_value : [];
				} else {
					if ( is_array( $field_value ) || is_object( $field_value ) ) {
						$value = json_encode( $field_value );
					} elseif ( is_string( $field_value ) ) {
						$value = $field_value;
					} else {
						$value = '';
					}
				}

				echo '<p>' . esc_html( sprintf( /* translators: field */ esc_html__( 'You are editing the field "%1$s" of "%2$s".', 'wpc-admin-columns' ), $field, '[#' . $term_id . ']' ) ) . '</p>';

				switch ( $type ) {
					case 'text':
						echo '<input class="wpcac-edit-value" type="text" value="' . esc_attr( $value ) . '"/>';
						break;
					case 'number':
						echo '<input class="wpcac-edit-value" type="number" step="any" value="' . esc_attr( $value ) . '"/>';
						break;
					case 'url':
						echo '<input class="wpcac-edit-value" type="url" value="' . esc_attr( esc_url( $value ) ) . '"/>';
						break;
					case 'email':
						echo '<input class="wpcac-edit-value" type="email" value="' . esc_attr( $value ) . '"/>';
						break;
					case 'color':
						echo '<input class="wpcac-edit-value wpcac-color-picker" type="text" value="' . esc_attr( $value ) . '"/>';
						break;
					case 'image':
						echo '<span class="wpcac-image-selector">';
						echo '<input type="hidden" class="wpcac-image-id wpcac-edit-value" value="' . esc_attr( $value ) . '"/>';

						if ( $value ) {
							echo '<span class="wpcac-image-preview">' . wp_kses( wp_get_attachment_image( $value ), wp_kses_allowed_html( 'wpcac_img' ) ) . '</span>';
						} else {
							echo '<span class="wpcac-image-preview">' . wp_kses( wc_placeholder_img(), wp_kses_allowed_html( 'wpcac_img' ) ) . '</span>';
						}

						echo '<span class="wpcac-image-btns"><a href="#" class="wpcac-image-remove">' . esc_html__( 'Remove', 'wpc-admin-columns' ) . '</a>';
						echo '<a href="#" class="wpcac-image-add" rel="' . esc_attr( $post_id ) . '">' . esc_html__( 'Choose Image', 'wpc-admin-columns' ) . '</a></span>';
						echo '</span>';

						break;
					case 'multiple_images':
						echo '<div class="wpcac-images-selector">';
						echo '<input type="hidden" class="wpcac-images-ids wpcac-edit-value" value="' . esc_attr( $value ) . '">';
						echo '<ul class="wpcac-images">';

						foreach ( explode( ',', $value ) as $attach_id ) {
							$attachment = wp_get_attachment_image_src( $attach_id, [ 40, 40 ] );

							if ( $attachment ) {
								echo '<li class="wpcac-image" data-id="' . esc_attr( $attach_id ) . '"><span class="wpcac-image-thumb"><a class="wpcac-image-remove" href="#"></a><img src="' . esc_url( $attachment[0] ) . '" width="40" height="40" /></span></li>';
							}
						}

						echo '</ul>';
						echo '<a href="#" class="wpcac-images-add" rel="' . esc_attr( $post_id ) . '">+</a>';
						echo '</div>';

						break;
					case 'true_false':
						echo '<select class="wpcac-edit-value">';
						echo '<option value="1" ' . ( self::string_to_bool( $value ) ? 'selected' : '' ) . '>' . esc_html__( 'True', 'wpc-admin-columns' ) . '</option>';
						echo '<option value="0" ' . ( ! self::string_to_bool( $value ) ? 'selected' : '' ) . '>' . esc_html__( 'False', 'wpc-admin-columns' ) . '</option>';
						echo '</select>';

						break;
					case 'yes_no':
						echo '<select class="wpcac-edit-value">';
						echo '<option value="yes" ' . ( self::string_to_bool( $value ) ? 'selected' : '' ) . '>' . esc_html__( 'Yes', 'wpc-admin-columns' ) . '</option>';
						echo '<option value="no" ' . ( ! self::string_to_bool( $value ) ? 'selected' : '' ) . '>' . esc_html__( 'No', 'wpc-admin-columns' ) . '</option>';
						echo '</select>';

						break;
					case 'on_off':
						echo '<select class="wpcac-edit-value">';
						echo '<option value="on" ' . ( self::string_to_bool( $value ) ? 'selected' : '' ) . '>' . esc_html__( 'On', 'wpc-admin-columns' ) . '</option>';
						echo '<option value="off" ' . ( ! self::string_to_bool( $value ) ? 'selected' : '' ) . '>' . esc_html__( 'Off', 'wpc-admin-columns' ) . '</option>';
						echo '</select>';

						break;
					case 'select':
					case 'multiple_select':
						if ( ! empty( $name ) && ! empty( $key ) ) {
							$saved_columns = get_option( $name, [] );
							$choices_str   = $saved_columns[ $key ]['choices'] ?? '';
							$choices       = array_map( 'trim', explode( "\n", $choices_str ) );
							$choices_arr   = [];
							$value_arr     = [];

							if ( ! empty( $value ) ) {
								if ( is_array( $value ) ) {
									$value_arr = $value;
								} else {
									$value_arr = [ $value ];
								}

								$choices = array_unique( array_merge( $choices, $value_arr ) );
							}

							echo '<select class="wpcac-edit-value" ' . ( $type === 'multiple_select' ? 'multiple' : '' ) . '>';

							if ( ! empty( $choices ) ) {
								foreach ( $choices as $choice ) {
									if ( str_contains( $choice, ' : ' ) ) {
										$choice_arr = explode( ' : ', $choice );
										$choice_val = trim( $choice_arr[0] );

										echo '<option value="' . esc_attr( $choice_val ) . '" ' . ( in_array( $choice_val, $value_arr ) ? 'selected' : '' ) . '>' . esc_html( trim( $choice_arr[1] ?? $choice_val ) ) . '</option>';

										if ( in_array( $choice_val, $value_arr ) ) {
											$choices_arr[] = $choice_val;
										}
									} else {
										if ( ! in_array( $choice, $choices_arr ) ) {
											echo '<option value="' . esc_attr( $choice ) . '" ' . ( in_array( $choice, $value_arr ) ? 'selected' : '' ) . '>' . esc_html( $choice ) . '</option>';
										}
									}
								}
							}

							echo '</select>';
						}

						break;
					case 'json':
						if ( self::get_setting( 'json_editor', 'no' ) === 'yes' ) {
							echo '<textarea id="wpcac-json-editor" class="wpcac-json-editor wpcac-edit-value" autocomplete="off">' . esc_textarea( $value ) . '</textarea>';
							echo '<pre id="wpcac-json-display" class="wpcac-json-display"></pre>';
							echo '<div id="wpcac-json-error" class="wpcac-json-error"></div>';
						} else {
							echo '<textarea class="wpcac-edit-value">' . esc_textarea( $value ) . '</textarea>';
						}

						break;
					default:
						echo '<textarea class="wpcac-edit-value">' . esc_textarea( $value ) . '</textarea>';
				}

				echo '<div class="wpcac-edit-btns"><button class="button button-primary wpcac-edit-save" data-id="0" data-tid="' . esc_attr( $term_id ) . '" data-field="' . esc_attr( $field ) . '" data-type="' . esc_attr( $type ) . '">' . esc_html__( 'Update', 'wpc-admin-columns' ) . '</button></div>';
			} elseif ( ! empty( $user_id ) ) {
				// custom field
				$field_value = get_user_meta( $user_id, $field, true );

				if ( $type === 'multiple_select' ) {
					$value = is_array( $field_value ) ? $field_value : [];
				} else {
					if ( is_array( $field_value ) || is_object( $field_value ) ) {
						$value = json_encode( $field_value );
					} elseif ( is_string( $field_value ) ) {
						$value = $field_value;
					} else {
						$value = '';
					}
				}

				echo '<p>' . esc_html( sprintf( /* translators: field */ esc_html__( 'You are editing the field "%1$s" of "%2$s".', 'wpc-admin-columns' ), $field, '[#' . $user_id . ']' ) ) . '</p>';

				switch ( $type ) {
					case 'text':
						echo '<input class="wpcac-edit-value" type="text" value="' . esc_attr( $value ) . '"/>';
						break;
					case 'number':
						echo '<input class="wpcac-edit-value" type="number" step="any" value="' . esc_attr( $value ) . '"/>';
						break;
					case 'url':
						echo '<input class="wpcac-edit-value" type="url" value="' . esc_attr( esc_url( $value ) ) . '"/>';
						break;
					case 'email':
						echo '<input class="wpcac-edit-value" type="email" value="' . esc_attr( $value ) . '"/>';
						break;
					case 'color':
						echo '<input class="wpcac-edit-value wpcac-color-picker" type="text" value="' . esc_attr( $value ) . '"/>';
						break;
					case 'image':
						echo '<span class="wpcac-image-selector">';
						echo '<input type="hidden" class="wpcac-image-id wpcac-edit-value" value="' . esc_attr( $value ) . '"/>';

						if ( $value ) {
							echo '<span class="wpcac-image-preview">' . wp_kses( wp_get_attachment_image( $value ), wp_kses_allowed_html( 'wpcac_img' ) ) . '</span>';
						} else {
							echo '<span class="wpcac-image-preview">' . wp_kses( wc_placeholder_img(), wp_kses_allowed_html( 'wpcac_img' ) ) . '</span>';
						}

						echo '<span class="wpcac-image-btns"><a href="#" class="wpcac-image-remove">' . esc_html__( 'Remove', 'wpc-admin-columns' ) . '</a>';
						echo '<a href="#" class="wpcac-image-add" rel="' . esc_attr( $post_id ) . '">' . esc_html__( 'Choose Image', 'wpc-admin-columns' ) . '</a></span>';
						echo '</span>';

						break;
					case 'multiple_images':
						echo '<div class="wpcac-images-selector">';
						echo '<input type="hidden" class="wpcac-images-ids wpcac-edit-value" value="' . esc_attr( $value ) . '">';
						echo '<ul class="wpcac-images">';

						foreach ( explode( ',', $value ) as $attach_id ) {
							$attachment = wp_get_attachment_image_src( $attach_id, [ 40, 40 ] );

							if ( $attachment ) {
								echo '<li class="wpcac-image" data-id="' . esc_attr( $attach_id ) . '"><span class="wpcac-image-thumb"><a class="wpcac-image-remove" href="#"></a><img src="' . esc_url( $attachment[0] ) . '" width="40" height="40" /></span></li>';
							}
						}

						echo '</ul>';
						echo '<a href="#" class="wpcac-images-add" rel="' . esc_attr( $post_id ) . '">+</a>';
						echo '</div>';

						break;
					case 'true_false':
						echo '<select class="wpcac-edit-value">';
						echo '<option value="1" ' . ( self::string_to_bool( $value ) ? 'selected' : '' ) . '>' . esc_html__( 'True', 'wpc-admin-columns' ) . '</option>';
						echo '<option value="0" ' . ( ! self::string_to_bool( $value ) ? 'selected' : '' ) . '>' . esc_html__( 'False', 'wpc-admin-columns' ) . '</option>';
						echo '</select>';

						break;
					case 'yes_no':
						echo '<select class="wpcac-edit-value">';
						echo '<option value="yes" ' . ( self::string_to_bool( $value ) ? 'selected' : '' ) . '>' . esc_html__( 'Yes', 'wpc-admin-columns' ) . '</option>';
						echo '<option value="no" ' . ( ! self::string_to_bool( $value ) ? 'selected' : '' ) . '>' . esc_html__( 'No', 'wpc-admin-columns' ) . '</option>';
						echo '</select>';

						break;
					case 'on_off':
						echo '<select class="wpcac-edit-value">';
						echo '<option value="on" ' . ( self::string_to_bool( $value ) ? 'selected' : '' ) . '>' . esc_html__( 'On', 'wpc-admin-columns' ) . '</option>';
						echo '<option value="off" ' . ( ! self::string_to_bool( $value ) ? 'selected' : '' ) . '>' . esc_html__( 'Off', 'wpc-admin-columns' ) . '</option>';
						echo '</select>';

						break;
					case 'select':
					case 'multiple_select':
						if ( ! empty( $name ) && ! empty( $key ) ) {
							$saved_columns = get_option( $name, [] );
							$choices_str   = $saved_columns[ $key ]['choices'] ?? '';
							$choices       = array_map( 'trim', explode( "\n", $choices_str ) );
							$choices_arr   = [];
							$value_arr     = [];

							if ( ! empty( $value ) ) {
								if ( is_array( $value ) ) {
									$value_arr = $value;
								} else {
									$value_arr = [ $value ];
								}

								$choices = array_unique( array_merge( $choices, $value_arr ) );
							}

							echo '<select class="wpcac-edit-value" ' . ( $type === 'multiple_select' ? 'multiple' : '' ) . '>';

							if ( ! empty( $choices ) ) {
								foreach ( $choices as $choice ) {
									if ( str_contains( $choice, ' : ' ) ) {
										$choice_arr = explode( ' : ', $choice );
										$choice_val = trim( $choice_arr[0] );

										echo '<option value="' . esc_attr( $choice_val ) . '" ' . ( in_array( $choice_val, $value_arr ) ? 'selected' : '' ) . '>' . esc_html( trim( $choice_arr[1] ?? $choice_val ) ) . '</option>';

										if ( in_array( $choice_val, $value_arr ) ) {
											$choices_arr[] = $choice_val;
										}
									} else {
										if ( ! in_array( $choice, $choices_arr ) ) {
											echo '<option value="' . esc_attr( $choice ) . '" ' . ( in_array( $choice, $value_arr ) ? 'selected' : '' ) . '>' . esc_html( $choice ) . '</option>';
										}
									}
								}
							}

							echo '</select>';
						}

						break;
					case 'json':
						if ( self::get_setting( 'json_editor', 'no' ) === 'yes' ) {
							echo '<textarea id="wpcac-json-editor" class="wpcac-json-editor wpcac-edit-value" autocomplete="off">' . esc_textarea( $value ) . '</textarea>';
							echo '<pre id="wpcac-json-display" class="wpcac-json-display"></pre>';
							echo '<div id="wpcac-json-error" class="wpcac-json-error"></div>';
						} else {
							echo '<textarea class="wpcac-edit-value">' . esc_textarea( $value ) . '</textarea>';
						}

						break;
					default:
						echo '<textarea class="wpcac-edit-value">' . esc_textarea( $value ) . '</textarea>';
				}

				echo '<div class="wpcac-edit-btns"><button class="button button-primary wpcac-edit-save" data-id="0" data-uid="' . esc_attr( $user_id ) . '" data-field="' . esc_attr( $field ) . '" data-type="' . esc_attr( $type ) . '">' . esc_html__( 'Update', 'wpc-admin-columns' ) . '</button></div>';
			} elseif ( ! empty( $post_id ) ) {
				if ( $type === 'taxonomy' ) {
					// taxonomy
					echo '<p>' . sprintf( /* translators: taxonomy */ esc_html__( 'You are editing the taxonomy "%1$s" of "%2$s".', 'wpc-admin-columns' ), esc_html( $field ), esc_html( '[#' . $post_id . '] ' . get_the_title( $post_id ) ) ) . '</p>';

					if ( is_taxonomy_hierarchical( $field ) ) {
						$selected = [];

						if ( $terms = get_the_terms( $post_id, $field ) ) {
							foreach ( $terms as $term ) {
								$selected[] = [ 'id' => $term->term_id, 'name' => $term->name ];
							}
						}

						echo '<div class="wpcac-select2-wrapper" data-taxonomy="' . esc_attr( $field ) . '">';
						echo '<select class="wpcac-edit-value wpcac-select2" multiple="multiple">';

						if ( ! empty( $selected ) ) {
							foreach ( $selected as $term ) {
								echo '<option value="' . esc_attr( $term['id'] ) . '" selected>' . esc_html( $term['name'] ) . '</option>';
							}
						}

						echo '</select>';
						echo '</div>';
					} else {
						$selected = [];

						if ( $terms = get_the_terms( $post_id, $field ) ) {
							foreach ( $terms as $term ) {
								$selected[] = $term->name;
							}
						}

						echo '<div class="wpcac-select2-wrapper" data-taxonomy="' . esc_attr( $field ) . '">';
						echo '<select class="wpcac-edit-value wpcac-select2-tags" multiple="multiple">';

						if ( ! empty( $selected ) ) {
							foreach ( $selected as $tag ) {
								echo '<option value="' . esc_attr( $tag ) . '" selected>' . esc_html( $tag ) . '</option>';
							}
						}

						echo '</select>';
						echo '</div>';
					}
				} else {
					// custom field
					$field_value = get_post_meta( $post_id, $field, true );

					if ( $type === 'multiple_select' ) {
						$value = is_array( $field_value ) ? $field_value : [];
					} else {
						if ( is_array( $field_value ) || is_object( $field_value ) ) {
							$value = json_encode( $field_value );
						} elseif ( is_string( $field_value ) ) {
							$value = $field_value;
						} else {
							$value = '';
						}
					}

					echo '<p>' . esc_html( sprintf( /* translators: field */ esc_html__( 'You are editing the field "%1$s" of "%2$s".', 'wpc-admin-columns' ), esc_html( $field ), esc_html( '[#' . $post_id . '] ' . get_the_title( $post_id ) ) ) ) . '</p>';

					switch ( $type ) {
						case 'text':
							echo '<input class="wpcac-edit-value" type="text" value="' . esc_attr( $value ) . '"/>';
							break;
						case 'number':
							echo '<input class="wpcac-edit-value" type="number" step="any" value="' . esc_attr( $value ) . '"/>';
							break;
						case 'url':
							echo '<input class="wpcac-edit-value" type="url" value="' . esc_attr( esc_url( $value ) ) . '"/>';
							break;
						case 'email':
							echo '<input class="wpcac-edit-value" type="email" value="' . esc_attr( $value ) . '"/>';
							break;
						case 'color':
							echo '<input class="wpcac-edit-value wpcac-color-picker" type="text" value="' . esc_attr( $value ) . '"/>';
							break;
						case 'image':
							echo '<span class="wpcac-image-selector">';
							echo '<input type="hidden" class="wpcac-image-id wpcac-edit-value" value="' . esc_attr( $value ) . '"/>';

							if ( $value ) {
								echo '<span class="wpcac-image-preview">' . wp_kses( wp_get_attachment_image( $value ), wp_kses_allowed_html( 'wpcac_img' ) ) . '</span>';
							} else {
								echo '<span class="wpcac-image-preview">' . wp_kses( wc_placeholder_img(), wp_kses_allowed_html( 'wpcac_img' ) ) . '</span>';
							}

							echo '<span class="wpcac-image-btns"><a href="#" class="wpcac-image-remove">' . esc_html__( 'Remove', 'wpc-admin-columns' ) . '</a>';
							echo '<a href="#" class="wpcac-image-add" rel="' . esc_attr( $post_id ) . '">' . esc_html__( 'Choose Image', 'wpc-admin-columns' ) . '</a></span>';
							echo '</span>';

							break;
						case 'multiple_images':
							echo '<div class="wpcac-images-selector">';
							echo '<input type="hidden" class="wpcac-images-ids wpcac-edit-value" value="' . esc_attr( $value ) . '">';
							echo '<ul class="wpcac-images">';

							foreach ( explode( ',', $value ) as $attach_id ) {
								$attachment = wp_get_attachment_image_src( $attach_id, [ 40, 40 ] );

								if ( $attachment ) {
									echo '<li class="wpcac-image" data-id="' . esc_attr( $attach_id ) . '"><span class="wpcac-image-thumb"><a class="wpcac-image-remove" href="#"></a><img src="' . esc_url( $attachment[0] ) . '" width="40" height="40" /></span></li>';
								}
							}

							echo '</ul>';
							echo '<a href="#" class="wpcac-images-add" rel="' . esc_attr( $post_id ) . '">+</a>';
							echo '</div>';

							break;
						case 'true_false':
							echo '<select class="wpcac-edit-value">';
							echo '<option value="1" ' . ( self::string_to_bool( $value ) ? 'selected' : '' ) . '>' . esc_html__( 'True', 'wpc-admin-columns' ) . '</option>';
							echo '<option value="0" ' . ( ! self::string_to_bool( $value ) ? 'selected' : '' ) . '>' . esc_html__( 'False', 'wpc-admin-columns' ) . '</option>';
							echo '</select>';

							break;
						case 'yes_no':
							echo '<select class="wpcac-edit-value">';
							echo '<option value="yes" ' . ( self::string_to_bool( $value ) ? 'selected' : '' ) . '>' . esc_html__( 'Yes', 'wpc-admin-columns' ) . '</option>';
							echo '<option value="no" ' . ( ! self::string_to_bool( $value ) ? 'selected' : '' ) . '>' . esc_html__( 'No', 'wpc-admin-columns' ) . '</option>';
							echo '</select>';

							break;
						case 'on_off':
							echo '<select class="wpcac-edit-value">';
							echo '<option value="on" ' . ( self::string_to_bool( $value ) ? 'selected' : '' ) . '>' . esc_html__( 'On', 'wpc-admin-columns' ) . '</option>';
							echo '<option value="off" ' . ( ! self::string_to_bool( $value ) ? 'selected' : '' ) . '>' . esc_html__( 'Off', 'wpc-admin-columns' ) . '</option>';
							echo '</select>';

							break;
						case 'select':
						case 'multiple_select':
							if ( ! empty( $name ) && ! empty( $key ) ) {
								$saved_columns = get_option( $name, [] );
								$choices_str   = $saved_columns[ $key ]['choices'] ?? '';
								$choices       = array_map( 'trim', explode( "\n", $choices_str ) );
								$choices_arr   = [];
								$value_arr     = [];

								if ( ! empty( $value ) ) {
									if ( is_array( $value ) ) {
										$value_arr = $value;
									} else {
										$value_arr = [ $value ];
									}

									$choices = array_unique( array_merge( $choices, $value_arr ) );
								}

								echo '<select class="wpcac-edit-value" ' . ( $type === 'multiple_select' ? 'multiple' : '' ) . '>';

								if ( ! empty( $choices ) ) {
									foreach ( $choices as $choice ) {
										if ( str_contains( $choice, ' : ' ) ) {
											$choice_arr = explode( ' : ', $choice );
											$choice_val = trim( $choice_arr[0] );

											echo '<option value="' . esc_attr( $choice_val ) . '" ' . ( in_array( $choice_val, $value_arr ) ? 'selected' : '' ) . '>' . esc_html( trim( $choice_arr[1] ?? $choice_val ) ) . '</option>';

											if ( in_array( $choice_val, $value_arr ) ) {
												$choices_arr[] = $choice_val;
											}
										} else {
											if ( ! in_array( $choice, $choices_arr ) ) {
												echo '<option value="' . esc_attr( $choice ) . '" ' . ( in_array( $choice, $value_arr ) ? 'selected' : '' ) . '>' . esc_html( $choice ) . '</option>';
											}
										}
									}
								}

								echo '</select>';
							}

							break;
						case 'json':
							if ( self::get_setting( 'json_editor', 'no' ) === 'yes' ) {
								echo '<textarea id="wpcac-json-editor" class="wpcac-json-editor wpcac-edit-value" autocomplete="off">' . esc_textarea( $value ) . '</textarea>';
								echo '<pre id="wpcac-json-display" class="wpcac-json-display"></pre>';
								echo '<div id="wpcac-json-error" class="wpcac-json-error"></div>';
							} else {
								echo '<textarea class="wpcac-edit-value">' . esc_textarea( $value ) . '</textarea>';
							}

							break;
						default:
							echo '<textarea class="wpcac-edit-value">' . esc_textarea( $value ) . '</textarea>';
					}
				}

				echo '<div class="wpcac-edit-btns"><button class="button button-primary wpcac-edit-save" data-id="' . esc_attr( $post_id ) . '" data-field="' . esc_attr( $field ) . '" data-type="' . esc_attr( $type ) . '">' . esc_html__( 'Update', 'wpc-admin-columns' ) . '</button></div>';
			} else {
				esc_html_e( 'Have an error!', 'wpc-admin-columns' );
			}
		} else {
			esc_html_e( 'Have an error!', 'wpc-admin-columns' );
		}

		wp_die();
	}

	function ajax_edit_save() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpcac-security' ) ) {
			die( 'Permissions check failed!' );
		}

		$post_id  = absint( sanitize_key( $_POST['id'] ?? 0 ) );
		$user_id  = absint( sanitize_key( $_POST['uid'] ?? 0 ) );
		$term_id  = absint( sanitize_key( $_POST['tid'] ?? 0 ) );
		$field    = sanitize_text_field( $_POST['field'] ?? '' );
		$type     = sanitize_text_field( $_POST['type'] ?? '' );
		$response = [
			'status' => 0,
			'value'  => esc_html__( 'Have an error!', 'wpc-admin-columns' )
		];

		if ( ! empty( $term_id ) && ! empty( $field ) ) {
			// term field
			if ( ! empty( $_POST['value'] ) ) {
				if ( is_array( $_POST['value'] ) ) {
					$value = self::sanitize_array( $_POST['value'] );
				} else {
					$value = sanitize_text_field( $_POST['value'] );
				}
			} else {
				$value = '';
			}

			switch ( $type ) {
				case 'color':
					// save as color
					update_term_meta( $term_id, $field, $value );
					$response['status'] = 1;
					$response['value']  = '<span style="background-color: ' . esc_attr( $value ) . '">' . esc_html( $value ) . '</span>';

					break;
				case 'image':
					// save as image
					update_term_meta( $term_id, $field, $value );
					$response['status'] = 1;
					$response['value']  = wp_get_attachment_image( $value );

					break;
				case 'multiple_images':
					// save as multiple images
					update_term_meta( $term_id, $field, $value );
					$response_value = '';

					if ( ! empty( $value ) ) {
						foreach ( explode( ',', $value ) as $val ) {
							$response_value .= wp_get_attachment_image( $val );
						}
					}

					$response['status'] = 1;
					$response['value']  = $response_value;

					break;
				default:
					if ( is_array( $value ) ) {
						// save as array
						update_term_meta( $term_id, $field, $value );

						$response_value = '<ul>';

						foreach ( $value as $v ) {
							$response_value .= '<li>' . esc_html( $v ) . '</li>';
						}

						$response_value .= '</ul>';

						$response['status'] = 1;
						$response['value']  = $response_value;
					} elseif ( is_numeric( $value ) ) {
						// save as number
						update_term_meta( $term_id, $field, $value );
						$response['status'] = 1;
						$response['value']  = $value;
					} elseif ( self::is_json( stripcslashes( trim( $value ) ) ) ) {
						// json decode
						update_term_meta( $term_id, $field, json_decode( stripcslashes( trim( $value ) ), true ) );
						$response['status'] = 1;
						$response['value']  = '[...]';
					} else {
						// save as string
						update_term_meta( $term_id, $field, (string) $value );
						$response['status'] = 1;
						$response['value']  = (string) $value;
					}
			}
		}

		if ( ! empty( $user_id ) && ! empty( $field ) ) {
			// user field
			if ( ! empty( $_POST['value'] ) ) {
				if ( is_array( $_POST['value'] ) ) {
					$value = self::sanitize_array( $_POST['value'] );
				} else {
					$value = sanitize_text_field( $_POST['value'] );
				}
			} else {
				$value = '';
			}

			switch ( $type ) {
				case 'color':
					// save as color
					update_user_meta( $user_id, $field, $value );
					$response['status'] = 1;
					$response['value']  = '<span style="background-color: ' . esc_attr( $value ) . '">' . esc_html( $value ) . '</span>';

					break;
				case 'image':
					// save as image
					update_user_meta( $user_id, $field, $value );
					$response['status'] = 1;
					$response['value']  = wp_get_attachment_image( $value );

					break;
				case 'multiple_images':
					// save as multiple images
					update_user_meta( $user_id, $field, $value );
					$response_value = '';

					if ( ! empty( $value ) ) {
						foreach ( explode( ',', $value ) as $val ) {
							$response_value .= wp_get_attachment_image( $val );
						}
					}

					$response['status'] = 1;
					$response['value']  = $response_value;

					break;
				default:
					if ( is_array( $value ) ) {
						// save as array
						update_user_meta( $user_id, $field, $value );

						$response_value = '<ul>';

						foreach ( $value as $v ) {
							$response_value .= '<li>' . esc_html( $v ) . '</li>';
						}

						$response_value .= '</ul>';

						$response['status'] = 1;
						$response['value']  = $response_value;
					} elseif ( is_numeric( $value ) ) {
						// save as number
						update_user_meta( $user_id, $field, $value );
						$response['status'] = 1;
						$response['value']  = $value;
					} elseif ( self::is_json( stripcslashes( trim( $value ) ) ) ) {
						// json decode
						update_user_meta( $user_id, $field, json_decode( stripcslashes( trim( $value ) ), true ) );
						$response['status'] = 1;
						$response['value']  = '[...]';
					} else {
						// save as string
						update_user_meta( $user_id, $field, (string) $value );
						$response['status'] = 1;
						$response['value']  = (string) $value;
					}
			}
		}

		if ( ! empty( $post_id ) && ! empty( $field ) ) {
			// post field
			if ( $type === 'taxonomy' ) {
				// taxonomy
				if ( ! empty( $_POST['value'] ) ) {
					if ( is_array( $_POST['value'] ) ) {
						$value = self::sanitize_array( $_POST['value'] );
					} else {
						$value = sanitize_text_field( $_POST['value'] );
					}
				} else {
					$value = '';
				}

				wp_set_post_terms( $post_id, $value, $field );

				// get terms
				if ( $terms = get_the_terms( $post_id, $field ) ) {
					$links = [];

					foreach ( $terms as $term ) {
						$link = get_edit_term_link( $term, $field );

						if ( is_wp_error( $link ) ) {
							return $link;
						}

						$links[] = '<a href="' . esc_url( $link ) . '" rel="tag">' . esc_html( $term->name ) . '</a>';
					}

					$response['status'] = 1;
					$response['value']  = implode( ', ', $links );
				} else {
					// remove all terms
					$response['status'] = 1;
					$response['value']  = '';
				}
			} else {
				// custom field
				if ( ! empty( $_POST['value'] ) ) {
					if ( is_array( $_POST['value'] ) ) {
						$value = self::sanitize_array( $_POST['value'] );
					} else {
						$value = sanitize_text_field( $_POST['value'] );
					}
				} else {
					$value = '';
				}

				switch ( $type ) {
					case 'color':
						// save as color
						update_post_meta( $post_id, $field, $value );
						$response['status'] = 1;
						$response['value']  = '<span style="background-color: ' . esc_attr( $value ) . '">' . esc_html( $value ) . '</span>';

						break;
					case 'image':
						// save as image
						update_post_meta( $post_id, $field, $value );
						$response['status'] = 1;
						$response['value']  = wp_get_attachment_image( $value );

						break;
					case 'multiple_images':
						// save as multiple images
						update_post_meta( $post_id, $field, $value );
						$response_value = '';

						if ( ! empty( $value ) ) {
							foreach ( explode( ',', $value ) as $val ) {
								$response_value .= wp_get_attachment_image( $val );
							}
						}

						$response['status'] = 1;
						$response['value']  = $response_value;

						break;
					default:
						if ( is_array( $value ) ) {
							// save as array
							update_post_meta( $post_id, $field, $value );

							$response_value = '<ul>';

							foreach ( $value as $v ) {
								$response_value .= '<li>' . esc_html( $v ) . '</li>';
							}

							$response_value .= '</ul>';

							$response['status'] = 1;
							$response['value']  = $response_value;
						} elseif ( is_numeric( $value ) ) {
							// save as number
							update_post_meta( $post_id, $field, $value );
							$response['status'] = 1;
							$response['value']  = $value;
						} elseif ( self::is_json( stripcslashes( trim( $value ) ) ) ) {
							// json decode
							update_post_meta( $post_id, $field, json_decode( stripcslashes( trim( $value ) ), true ) );
							$response['status'] = 1;
							$response['value']  = '[...]';
						} else {
							// save as string
							update_post_meta( $post_id, $field, (string) $value );
							$response['status'] = 1;
							$response['value']  = (string) $value;
						}
				}
			}
		}

		wp_send_json( $response );
	}

	function ajax_search_terms() {
		$return = [];

		$args = [
			'taxonomy'   => sanitize_text_field( $_REQUEST['taxonomy'] ),
			'orderby'    => 'id',
			'order'      => 'ASC',
			'hide_empty' => false,
			'fields'     => 'all',
			'name__like' => sanitize_text_field( $_REQUEST['term'] ),
		];

		$terms = get_terms( $args );

		if ( count( $terms ) ) {
			foreach ( $terms as $term ) {
				$return[] = [ $term->term_id, $term->name ];
			}
		}

		wp_send_json( $return );
	}

	function ajax_search_tags() {
		$return = [];

		$args = [
			'taxonomy'   => sanitize_text_field( $_REQUEST['taxonomy'] ),
			'orderby'    => 'id',
			'order'      => 'ASC',
			'hide_empty' => false,
			'fields'     => 'all',
			'name__like' => sanitize_text_field( $_REQUEST['term'] ),
		];

		$terms = get_terms( $args );

		if ( count( $terms ) ) {
			foreach ( $terms as $term ) {
				$return[] = [ $term->name, $term->name ];
			}
		}

		wp_send_json( $return );
	}

	function ajax_product_variations() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpcac-security' ) ) {
			die( 'Permissions check failed!' );
		}

		$product_id = absint( sanitize_key( $_POST['id'] ?? 0 ) );

		if ( ( $product = wc_get_product( $product_id ) ) && $product->is_type( 'variable' ) ) {
			$variation_ids = $product->get_children();

			if ( count( $variation_ids ) ) {
				$variations_html = '<div class="wpcac-product-variations">';

				foreach ( $variation_ids as $variation_id ) {
					if ( $variation = wc_get_product( $variation_id ) ) {
						$variation_html = '<div class="wpcac-product-variation">';
						$variation_html .= '<div class="wpcac-product-variation-image">' . $variation->get_image() . '</div>';
						$variation_html .= '<div class="wpcac-product-variation-info">';
						$variation_html .= '<div class="wpcac-product-variation-id-name"><span class="wpcac-product-variation-id">#' . $variation->get_id() . '</span> <span class="wpcac-product-variation-name">' . esc_html( wp_strip_all_tags( $variation->get_formatted_name() ) ) . '</span></div>';
						$variation_html .= '<div class="wpcac-product-variation-price-stock"><span class="wpcac-product-variation-price">' . $variation->get_price_html() . '</span> <span class="wpcac-product-variation-stock">' . esc_html( wp_strip_all_tags( wc_get_stock_html( $variation ) ) ) . '</span></div>';
						$variation_html .= '<div class="wpcac-product-variation-dimensions-weight"><span class="wpcac-product-variation-dimensions">' . esc_html__( 'Dimensions:', 'wpc-admin-columns' ) . ' ' . esc_html( wp_strip_all_tags( $variation->get_dimensions() ) ) . '</span> <span class="wpcac-product-variation-weight">' . esc_html__( 'Weight:', 'wpc-admin-columns' ) . ' ' . esc_html( wp_strip_all_tags( $variation->get_weight() ) ) . '</span></div>';
						$variation_html .= '</div>';
						$variation_html .= '</div>';

						$variations_html .= apply_filters( 'wpcac_product_variation_html', $variation_html, $variation_id );
					}
				}

				$variations_html .= '</div>';

				echo apply_filters( 'wpcac_product_variations_html', $variations_html, $product_id );
			}
		}

		wp_die();
	}

	function ajax_intro_done() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpcac-security' ) ) {
			die( 'Permissions check failed!' );
		}

		update_user_meta( get_current_user_id(), 'wpcac_intro_' . WPCAC_VERSION, current_time( 'U' ) );

		wp_die();
	}

	function save_post( $post_id, $post ) {
		if ( ! empty( $post->post_type ) ) {
			delete_transient( 'wpcac_get_' . $post->post_type . '_meta_keys' );
		}
	}

	function update_user() {
		delete_transient( 'wpcac_get_users_meta_keys' );
	}

	function filter_columns( $columns ) {
		$screen_key = self::get_screen_key();

		if ( empty( $screen_key ) ) {
			return $columns;
		}

		$columns_name  = self::get_columns_name( $screen_key );
		$saved_columns = get_option( $columns_name, [] );
		$new_columns   = [];

		if ( isset( $columns['cb'] ) ) {
			$new_columns['cb'] = $columns['cb'];
		}

		if ( is_array( $saved_columns ) && ! empty( $saved_columns ) ) {
			foreach ( $saved_columns as $key => $column ) {
				if ( isset( $column['enable'] ) && ( $column['enable'] === 'no' ) ) {
					unset( $columns[ $key ] );
				} else {
					if ( ( $column['type'] === 'system' ) && isset( $columns[ $key ] ) ) {
						$new_columns[ $key ] = $columns[ $key ];
					} else {
						$new_columns[ $key ] = ! empty( $column['name'] ) ? $column['name'] : '';
					}
				}
			}
		}

		if ( isset( $columns['handle'] ) ) {
			$new_columns['handle'] = $columns['handle'];
		}

		return $new_columns + $columns;
	}

	function custom_column( $column, $postid_or_obj ) {
		if ( is_numeric( $postid_or_obj ) ) {
			$postid = $postid_or_obj;
		} elseif ( is_a( $postid_or_obj, 'WC_Order' ) ) {
			$postid = $postid_or_obj->get_id();
		} else {
			return null;
		}

		$screen_key = self::get_screen_key();

		if ( empty( $screen_key ) ) {
			return null;
		}

		$columns_name  = self::get_columns_name( $screen_key );
		$saved_columns = get_option( $columns_name, [] );

		if ( is_string( $column ) && ( str_starts_with( $column, 'wpcac_' ) ) && isset( $saved_columns[ $column ] ) ) {
			$column_content = '';
			$column_type    = $saved_columns[ $column ]['type'] ?? '';
			$editable       = ! empty( $saved_columns[ $column ]['editable'] ) && ( $saved_columns[ $column ]['editable'] === 'yes' );
			$field          = ! empty( $saved_columns[ $column ]['field'] ) ? $saved_columns[ $column ]['field'] : '';
			$data_type      = ! empty( $saved_columns[ $column ]['data_type'] ) ? $saved_columns[ $column ]['data_type'] : '';

			switch ( $column_type ) {
				case 'id':
				case 'attachment_id':
				case 'product_id':
				case 'order_id':
				case 'user_id':
					$column_content = esc_html( $postid );

					break;
				case 'slug':
					if ( ( $post = get_post( $postid ) ) && ! empty( $post->post_name ) ) {
						$column_content = $post->post_name;
					}

					break;
				case 'status':
					if ( ( $post = get_post( $postid ) ) && ! empty( $post->post_status ) ) {
						$column_content = $post->post_status;
					}

					break;
				case 'attachment_author':
				case 'author':
					if ( ( $post = get_post( $postid ) ) && ! empty( $post->post_author ) ) {
						if ( is_numeric( $post->post_author ) ) {
							$user           = get_user_by( 'id', $post->post_author );
							$column_content = $user->nickname;
						} else {
							$column_content = $post->post_author;
						}
					}

					break;
				case 'taxonomy':
				case 'product_taxonomy':
					if ( ! empty( $saved_columns[ $column ]['taxonomy'] ) ) {
						if ( $terms = get_the_terms( $postid, $saved_columns[ $column ]['taxonomy'] ) ) {
							$links = [];

							foreach ( $terms as $term ) {
								$link = get_edit_term_link( $term, $saved_columns[ $column ]['taxonomy'] );

								if ( is_wp_error( $link ) ) {
									return $link;
								}

								$links[] = '<a href="' . esc_url( $link ) . '" rel="tag">' . esc_html( $term->name ) . '</a>';
							}

							$column_content = '<div class="wpcac-value" data-id="' . esc_attr( $postid ) . '" data-type="' . esc_attr( $saved_columns[ $column ]['data_type'] ) . '" data-field="' . esc_attr( $saved_columns[ $column ]['taxonomy'] ) . '">' . implode( ', ', $links ) . '</div>';
						} else {
							$column_content = '<div class="wpcac-value" data-id="' . esc_attr( $postid ) . '" data-type="' . esc_attr( $saved_columns[ $column ]['data_type'] ) . '" data-field="' . esc_attr( $saved_columns[ $column ]['taxonomy'] ) . '"></div>';
						}

						if ( $editable ) {
							$column_content .= '<span class="wpcac-value-actions">';
							$column_content .= '<a href="#" class="wpcac-edit hint--top" aria-label="' . esc_attr( sprintf( /* translators: edit */ esc_html__( 'Edit "%1$s" of #%2$s', 'wpc-admin-columns' ), $saved_columns[ $column ]['taxonomy'], $postid ) ) . '" data-name="' . esc_attr( $columns_name ) . '" data-key="' . esc_attr( $column ) . '" data-id="' . esc_attr( $postid ) . '" data-field="' . esc_attr( $saved_columns[ $column ]['taxonomy'] ) . '" data-type="taxonomy"><span>' . esc_html__( 'edit', 'wpc-admin-columns' ) . '</span></a>';
							$column_content .= '</span>';
						}
					}

					break;
				case 'attachment_published':
				case 'published':
					if ( ( $post = get_post( $postid ) ) && ! empty( $post->post_date ) ) {
						$column_content = $post->post_date;
					}

					break;
				case 'attachment_modified':
				case 'modified':
					if ( ( $post = get_post( $postid ) ) && ! empty( $post->post_modified ) ) {
						$column_content = $post->post_modified;
					}

					break;
				case 'comment_status':
					if ( ( $post = get_post( $postid ) ) && ! empty( $post->comment_status ) ) {
						$column_content = $post->comment_status;
					}

					break;
				case 'comment_count':
					if ( ( $post = get_post( $postid ) ) && isset( $post->comment_count ) ) {
						$column_content = $post->comment_count;
					}

					break;
				case 'ping_status':
					if ( ( $post = get_post( $postid ) ) && ! empty( $post->ping_status ) ) {
						$column_content = $post->ping_status;
					}

					break;
				case 'featured_image':
					$field          = '_thumbnail_id';
					$data_type      = 'image';
					$column_content = '<span class="wpcac-featured-image wpcac-value wpcac-value-image" data-id="' . esc_attr( $postid ) . '" data-field="' . esc_attr( $field ) . '">' . ( has_post_thumbnail( $postid ) ? get_the_post_thumbnail( $postid ) : '' ) . '</span>';

					break;
				case 'excerpt':
					$column_content = esc_html( get_the_excerpt( $postid ) );

					break;
				case 'words_count':
					if ( $post = get_post( $postid ) ) {
						$column_content = str_word_count( wp_strip_all_tags( $post->post_content ) );
					}

					break;
				case 'name':
				case 'title':
				case 'product_name':
					$column_content = esc_html( get_the_title( $postid ) );

					break;
				case 'product_sku':
					$field     = '_sku';
					$data_type = 'text';

					if ( class_exists( 'WC_Product' ) && ( $product = wc_get_product( $postid ) ) ) {
						$column_content = '<span class="wpcac-product-sku wpcac-value wpcac-value-text" data-id="' . esc_attr( $postid ) . '" data-field="' . esc_attr( $field ) . '">' . $product->get_sku() . '</span>';
					}

					break;
				case 'product_slug':
					if ( class_exists( 'WC_Product' ) && ( $product = wc_get_product( $postid ) ) ) {
						$column_content = $product->get_slug();
					}

					break;
				case 'product_image':
					$field     = '_thumbnail_id';
					$data_type = 'image';

					if ( class_exists( 'WC_Product' ) && ( $product = wc_get_product( $postid ) ) ) {
						$column_content = '<span class="wpcac-featured-image wpcac-value wpcac-value-image" data-id="' . esc_attr( $postid ) . '" data-field="' . esc_attr( $field ) . '">' . $product->get_image( 'thumbnail' ) . '</span>';
					}

					break;
				case 'product_gallery':
					$field     = '_product_image_gallery';
					$data_type = 'multiple_images';

					if ( class_exists( 'WC_Product' ) && ( $product = wc_get_product( $postid ) ) ) {
						$column_content = '<span class="wpcac-image-gallery wpcac-value wpcac-value-multiple_images" data-id="' . esc_attr( $postid ) . '" data-field="' . esc_attr( $field ) . '">';
						$images         = $product->get_gallery_image_ids();

						if ( ! empty( $images ) ) {
							foreach ( $images as $image ) {
								$column_content .= wp_get_attachment_image( $image );
							}
						}

						$column_content .= '</span>';
					}

					break;
				case 'product_type':
					if ( class_exists( 'WC_Product' ) && ( $product = wc_get_product( $postid ) ) ) {
						$type         = $product->get_type();
						$all_types    = wc_get_product_types();
						$product_type = ! empty( $all_types[ $type ] ) ? $all_types[ $type ] : $type;
						$product_type = ucwords( trim( str_replace( 'product', '', $product_type ) ) );

						$column_content = esc_html( $product_type );
					}

					break;
				case 'product_type_icon':
					if ( class_exists( 'WC_Product' ) && ( $product = wc_get_product( $postid ) ) ) {
						$type         = $product->get_type();
						$all_types    = wc_get_product_types();
						$product_type = ! empty( $all_types[ $type ] ) ? $all_types[ $type ] : $type;
						$product_type = ucwords( trim( str_replace( 'product', '', $product_type ) ) );

						if ( $type === 'simple' ) {
							$column_content = '<span class="wpcac-product-type-icon wpcac-product-type-' . esc_attr( $product->is_virtual() ? 'virtual' : ( $product->is_downloadable() ? 'downloadable' : 'simple' ) ) . ' hint--top" aria-label="' . esc_attr( $product_type ) . '"><span>' . esc_html( $product_type ) . '</span></span>';
						} else {
							$column_content = '<span class="wpcac-product-type-icon wpcac-product-type-' . esc_attr( $type ) . ' hint--top" aria-label="' . esc_attr( $product_type ) . '"><span>' . esc_html( $product_type ) . '</span></span>';
						}
					}

					break;
				case 'product_status':
					if ( class_exists( 'WC_Product' ) && ( $product = wc_get_product( $postid ) ) ) {
						$column_content = esc_html( $product->get_status() );
					}

					break;
				case 'product_description':
					if ( class_exists( 'WC_Product' ) && ( $product = wc_get_product( $postid ) ) ) {
						$column_content = esc_html( $product->get_description() );
					}

					break;
				case 'product_short_description':
					if ( class_exists( 'WC_Product' ) && ( $product = wc_get_product( $postid ) ) ) {
						$column_content = esc_html( $product->get_short_description() );
					}

					break;
				case 'product_weight':
					if ( class_exists( 'WC_Product' ) && ( $product = wc_get_product( $postid ) ) ) {
						$column_content = esc_html( $product->get_weight() );
					}

					break;
				case 'product_length':
					if ( class_exists( 'WC_Product' ) && ( $product = wc_get_product( $postid ) ) ) {
						$column_content = esc_html( $product->get_length() );
					}

					break;
				case 'product_width':
					if ( class_exists( 'WC_Product' ) && ( $product = wc_get_product( $postid ) ) ) {
						$column_content = esc_html( $product->get_width() );
					}

					break;
				case 'product_height':
					if ( class_exists( 'WC_Product' ) && ( $product = wc_get_product( $postid ) ) ) {
						$column_content = esc_html( $product->get_height() );
					}

					break;
				case 'product_dimensions':
					if ( class_exists( 'WC_Product' ) && ( $product = wc_get_product( $postid ) ) ) {
						$column_content = esc_html( $product->get_dimensions() );
					}

					break;
				case 'product_variations':
					if ( class_exists( 'WC_Product' ) && ( $product = wc_get_product( $postid ) ) && $product->is_type( 'variable' ) ) {
						$count          = count( $product->get_children() );
						$count_txt      = apply_filters( 'wpcac_product_variations_txt', sprintf( /* translators: count */ _n( '%s variation', '%s variations', $count, 'wpc-admin-columns' ), $count ), $product );
						$column_content = '<a href="#" class="wpcac-product-variations-btn" data-id="' . esc_attr( $postid ) . '" data-title="' . esc_attr( sprintf( /* translators: product id */ esc_html__( 'Variations of #%s', 'wpc-admin-columns' ), $postid ) ) . '">' . esc_html( $count_txt ) . '</a>';
					}

					break;
				case 'actions':
				case 'product_actions':
					$post             = get_post( $postid );
					$actions          = [];
					$title            = _draft_or_post_title( $post );
					$post_type_object = get_post_type_object( $post->post_type );
					$can_edit_post    = current_user_can( 'edit_post', $post->ID );

					if ( $can_edit_post && 'trash' !== $post->post_status ) {
						$actions['edit'] = sprintf(
							'<a href="%s">%s</a>',
							get_edit_post_link( $postid ),
							esc_html__( 'Edit', 'wpc-admin-columns' )
						);
					}

					if ( current_user_can( 'delete_post', $post->ID ) ) {
						if ( 'trash' === $post->post_status ) {
							$actions['untrash'] = sprintf(
								'<a href="%s" aria-label="%s">%s</a>',
								wp_nonce_url( admin_url( sprintf( $post_type_object->_edit_link . '&amp;action=untrash', $post->ID ) ), 'untrash-post_' . $post->ID ),
								/* translators: post title */
								esc_attr( sprintf( esc_html__( 'Restore &#8220;%s&#8221; from the Trash', 'wpc-admin-columns' ), $title ) ),
								esc_html__( 'Restore', 'wpc-admin-columns' )
							);
						} elseif ( EMPTY_TRASH_DAYS ) {
							$actions['trash'] = sprintf(
								'<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
								get_delete_post_link( $post->ID ),
								/* translators: post title */
								esc_attr( sprintf( esc_html__( 'Move &#8220;%s&#8221; to the Trash', 'wpc-admin-columns' ), $title ) ),
								_x( 'Trash', 'verb', 'wpc-admin-columns' )
							);
						}

						if ( 'trash' === $post->post_status || ! EMPTY_TRASH_DAYS ) {
							$actions['delete'] = sprintf(
								'<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
								get_delete_post_link( $post->ID, '', true ),
								/* translators: post title */
								esc_attr( sprintf( esc_html__( 'Delete &#8220;%s&#8221; permanently', 'wpc-admin-columns' ), $title ) ),
								esc_html__( 'Delete Permanently', 'wpc-admin-columns' )
							);
						}
					}

					if ( is_post_type_viewable( $post_type_object ) ) {
						if ( in_array( $post->post_status, [ 'pending', 'draft', 'future' ], true ) ) {
							if ( $can_edit_post ) {
								$preview_link    = get_preview_post_link( $post );
								$actions['view'] = sprintf(
									'<a href="%s" rel="bookmark" aria-label="%s">%s</a>',
									esc_url( $preview_link ),
									/* translators: post title */
									esc_attr( sprintf( esc_html__( 'Preview &#8220;%s&#8221;', 'wpc-admin-columns' ), $title ) ),
									esc_html__( 'Preview', 'wpc-admin-columns' )
								);
							}
						} elseif ( 'trash' !== $post->post_status ) {
							$actions['view'] = sprintf(
								'<a href="%s" rel="bookmark" aria-label="%s">%s</a>',
								get_permalink( $post->ID ),
								/* translators: post title */
								esc_attr( sprintf( esc_html__( 'View &#8220;%s&#8221;', 'wpc-admin-columns' ), $title ) ),
								esc_html__( 'View', 'wpc-admin-columns' )
							);
						}
					}

					$actions        = apply_filters( 'post_row_actions', $actions, $post );
					$column_content = '<div class="wpcac-column-actions-content">' . implode( ' | ', array_values( $actions ) ) . '</div>';

					break;
				case 'duplicate':
				case 'product_duplicate':
				case 'order_duplicate':
					$column_content = '<div class="wpcac-column-duplicate-content"><a href="' . esc_url( self::get_duplicate_link( $postid ) ) . '" class="wpcac-duplicate-btn hint--top" aria-label="' . esc_attr( sprintf( /* translators: post id */ esc_attr__( 'Duplicate #%d', 'wpc-admin-columns' ), $postid ) ) . '" data-id="' . esc_attr( $postid ) . '"><span class="dashicons dashicons-admin-page"></span></a></div>';

					break;
				case 'order_products':
					if ( $order = wc_get_order( $postid ) ) {
						foreach ( $order->get_items() as $item ) {
							$column_content .= $item->get_quantity() . ' &times; ' . $item->get_name() . '<br/>';
						}
					}

					break;
				case 'billing_phone':
					if ( $order = wc_get_order( $postid ) ) {
						$column_content = $order->get_billing_phone();
					}

					break;
				case 'shipping_phone':
					if ( $order = wc_get_order( $postid ) ) {
						$column_content = $order->get_shipping_phone();
					}

					break;
				case 'product_meta':
				case 'custom_field':
					if ( ! empty( $field ) ) {
						$value = get_post_meta( $postid, $field, true ) ?: '';

						switch ( $data_type ) {
							case 'color':
								$column_content = '<div class="wpcac-value wpcac-value-color" data-id="' . esc_attr( $postid ) . '" data-field="' . esc_attr( $field ) . '"><span style="background-color: ' . esc_attr( $value ) . '">' . esc_html( $value ) . '</span></div>';

								break;
							case 'image':
								$column_content = '<div class="wpcac-value wpcac-value-image" data-id="' . esc_attr( $postid ) . '" data-field="' . esc_attr( $field ) . '">' . wp_kses( wp_get_attachment_image( $value ), wp_kses_allowed_html( 'wpcac_img' ) ) . '</div>';

								break;
							case 'multiple_select':
								$column_content = '<div class="wpcac-value wpcac-value-multiple_select" data-id="' . esc_attr( $postid ) . '" data-field="' . esc_attr( $field ) . '">';
								$column_content .= '<ul>';

								if ( ! empty( $value ) && is_array( $value ) ) {
									foreach ( $value as $val ) {
										$column_content .= '<li>' . esc_html( $val ) . '</li>';
									}
								}

								$column_content .= '</ul>';
								$column_content .= '</div>';

								break;
							case 'multiple_images':
								$column_content = '<div class="wpcac-value wpcac-value-multiple_images" data-id="' . esc_attr( $postid ) . '" data-field="' . esc_attr( $field ) . '">';

								if ( ! empty( $value ) ) {
									foreach ( explode( ',', $value ) as $val ) {
										$column_content .= wp_get_attachment_image( $val );
									}
								}

								$column_content .= '</div>';

								break;
							default:
								if ( is_string( $value ) ) {
									$column_content = '<div class="wpcac-value" data-id="' . esc_attr( $postid ) . '" data-type="' . esc_attr( $saved_columns[ $column ]['data_type'] ) . '" data-field="' . esc_attr( $field ) . '">' . esc_html( $value ) . '</div>';
								} else {
									$column_content = '<div class="wpcac-value" data-id="' . esc_attr( $postid ) . '" data-type="' . esc_attr( $saved_columns[ $column ]['data_type'] ) . '" data-field="' . esc_attr( $field ) . '">[...]</div>';
								}
						}
					}

					break;
				default:
					$column_content = ! empty( $saved_columns[ $column ]['text'] ) ? do_shortcode( str_replace( '{post_id}', $postid, $saved_columns[ $column ]['text'] ) ) : $postid;
			}

			if ( $editable ) {
				$column_content .= '<span class="wpcac-value-actions">';
				$column_content .= '<a href="#" class="wpcac-copy hint--top" aria-label="' . esc_attr__( 'Copy', 'wpc-admin-columns' ) . '" data-type="' . esc_attr( $data_type ) . '"><span>' . esc_html__( 'copy', 'wpc-admin-columns' ) . '</span></a>';
				$column_content .= '<a href="#" class="wpcac-edit hint--top" aria-label="' . esc_attr( sprintf( /* translators: edit */ esc_html__( 'Edit "%1$s" of #%2$s', 'wpc-admin-columns' ), $field, $postid ) ) . '" data-name="' . esc_attr( $columns_name ) . '" data-key="' . esc_attr( $column ) . '" data-id="' . esc_attr( $postid ) . '" data-field="' . esc_attr( $field ) . '" data-type="' . esc_attr( $data_type ) . '"><span>' . esc_html__( 'edit', 'wpc-admin-columns' ) . '</span></a>';
				$column_content .= '</span>';
			}

			echo '<div class="' . esc_attr( $editable ? 'wpcac-value-wrapper wpcac-value-wrapper-editable' : 'wpcac-value-wrapper' ) . '">' . wp_kses_post( apply_filters( 'wpcac_custom_column_' . $column_type, $column_content, $column, $postid ) ) . '</div>';
		}

		return null;
	}

	function plugins_custom_column( $column, $plugin_slug, $plugin ) {
		$screen_key = self::get_screen_key();

		if ( empty( $screen_key ) ) {
			return null;
		}

		$columns_name  = self::get_columns_name( $screen_key );
		$saved_columns = get_option( $columns_name, [] );

		if ( is_string( $column ) && ( str_starts_with( $column, 'wpcac_' ) ) && isset( $saved_columns[ $column ] ) ) {
			$column_type    = $saved_columns[ $column ]['type'];
			$column_content = '';

			switch ( $column_type ) {
				case 'plugin_name':
					if ( ! empty( $plugin['Name'] ) ) {
						$column_content = $plugin['Name'];
					}

					break;
				case 'plugin_author':
					if ( ! empty( $plugin['Author'] ) ) {
						if ( ! empty( $plugin['AuthorURI'] ) ) {
							$column_content = '<a href="' . esc_url( $plugin['AuthorURI'] ) . '" target="_blank">' . $plugin['Author'] . '</a>';
						} else {
							$column_content = $plugin['Author'];
						}
					}

					break;
				case 'plugin_version':
					if ( ! empty( $plugin['Version'] ) ) {
						$column_content = $plugin['Version'];
					}

					break;
				case 'plugin_icon':
					if ( ! empty( $plugin['icons']['1x'] ) ) {
						$column_content = '<img style="width: 48px; height: 48px" src="' . esc_url( $plugin['icons']['1x'] ) . '"/>';
					} else {
						$column_content = '<span class="wpcac-no-icon"></span>';
					}

					break;
				case 'plugin_desc':
					if ( ! empty( $plugin['Description'] ) ) {
						$column_content = $plugin['Description'];
					}

					break;
				default:
					$column_content = ! empty( $saved_columns[ $column ]['text'] ) ? do_shortcode( $saved_columns[ $column ]['text'] ) : $plugin['Name'];
			}

			echo '<div class="wpcac-value-wrapper">' . wp_kses_post( apply_filters( 'wpcac_plugins_custom_column_' . $column_type, $column_content, $column, $plugin ) ) . '</div>';
		}
	}

	function comments_custom_column( $column, $comment_id ) {
		$screen_key = self::get_screen_key();

		if ( empty( $screen_key ) ) {
			return null;
		}

		$columns_name  = self::get_columns_name( $screen_key );
		$saved_columns = get_option( $columns_name, [] );

		if ( is_string( $column ) && ( str_starts_with( $column, 'wpcac_' ) ) && isset( $saved_columns[ $column ] ) ) {
			$column_type    = $saved_columns[ $column ]['type'];
			$column_content = '';

			switch ( $column_type ) {
				case 'id':
				case 'comment_id':
					$column_content = esc_html( $comment_id );

					break;
				case 'comment_author':
					if ( $comment = get_comment( $comment_id ) ) {
						$column_content = $comment->comment_author;
					}

					break;
				case 'comment_type':
					if ( $comment = get_comment( $comment_id ) ) {
						$column_content = $comment->comment_type;
					}

					break;
				case 'comment_date':
					if ( $comment = get_comment( $comment_id ) ) {
						$column_content = $comment->comment_date;
					}

					break;
				case 'comment_author_email':
					if ( $comment = get_comment( $comment_id ) ) {
						$column_content = $comment->comment_author_email;
					}

					break;
				case 'comment_author_url':
					if ( $comment = get_comment( $comment_id ) ) {
						$column_content = $comment->comment_author_url;
					}

					break;
				case 'comment_post':
					if ( $comment = get_comment( $comment_id ) ) {
						$column_content = '<a href="' . esc_url( get_permalink( $comment->comment_post_ID ) ) . '" target="_blank">' . esc_html( get_the_title( $comment->comment_post_ID ) ) . '</a>';
					}

					break;
				case 'comment_words_count':
					if ( $comment = get_comment( $comment_id ) ) {
						$column_content = str_word_count( wp_strip_all_tags( $comment->comment_content ) );
					}

					break;
				default:
					$column_content = ! empty( $saved_columns[ $column ]['text'] ) ? do_shortcode( str_replace( '{comment_id}', $comment_id, $saved_columns[ $column ]['text'] ) ) : $comment_id;
			}

			echo '<div class="wpcac-value-wrapper">' . wp_kses_post( apply_filters( 'wpcac_comments_custom_column_' . $column_type, $column_content, $column, $comment_id ) ) . '</div>';
		}
	}

	function media_custom_column( $column, $media_id ) {
		$screen_key = self::get_screen_key();

		if ( empty( $screen_key ) ) {
			return null;
		}

		$columns_name  = self::get_columns_name( $screen_key );
		$saved_columns = get_option( $columns_name, [] );

		if ( is_string( $column ) && ( str_starts_with( $column, 'wpcac_' ) ) && isset( $saved_columns[ $column ] ) ) {
			$column_type    = $saved_columns[ $column ]['type'];
			$editable       = ! empty( $saved_columns[ $column ]['editable'] ) && ( $saved_columns[ $column ]['editable'] === 'yes' );
			$column_content = '';

			switch ( $column_type ) {
				case 'id':
				case 'media_id':
				case 'attachment_id':
					$column_content = esc_html( $media_id );

					break;
				case 'author':
				case 'media_author':
				case 'attachment_author':
					if ( ( $post = get_post( $media_id ) ) && ! empty( $post->post_author ) ) {
						if ( is_numeric( $post->post_author ) ) {
							$user           = get_user_by( 'id', $post->post_author );
							$column_content = $user->nickname;
						} else {
							$column_content = $post->post_author;
						}
					}

					break;
				case 'published':
				case 'media_published':
				case 'attachment_published':
					if ( ( $post = get_post( $media_id ) ) && ! empty( $post->post_date ) ) {
						$column_content = $post->post_date;
					}

					break;
				case 'modified':
				case 'media_modified':
				case 'attachment_modified':
					if ( ( $post = get_post( $media_id ) ) && ! empty( $post->post_modified ) ) {
						$column_content = $post->post_modified;
					}

					break;
				case 'media_meta':
				case 'attachment_meta':
				case 'custom_field':
					if ( ! empty( $saved_columns[ $column ]['field'] ) ) {
						$value     = get_post_meta( $media_id, $saved_columns[ $column ]['field'], true ) ?: '';
						$data_type = ! empty( $saved_columns[ $column ]['data_type'] ) ? $saved_columns[ $column ]['data_type'] : '';

						switch ( $data_type ) {
							case 'color':
								$column_content = '<div class="wpcac-value wpcac-value-color" data-id="' . esc_attr( $media_id ) . '" data-field="' . esc_attr( $saved_columns[ $column ]['field'] ) . '"><span style="background-color: ' . esc_attr( $value ) . '">' . esc_html( $value ) . '</span></div>';

								break;
							case 'image':
								$column_content = '<div class="wpcac-value wpcac-value-image" data-id="' . esc_attr( $media_id ) . '" data-field="' . esc_attr( $saved_columns[ $column ]['field'] ) . '">' . wp_kses( wp_get_attachment_image( $value ), wp_kses_allowed_html( 'wpcac_img' ) ) . '</div>';

								break;
							case 'multiple_select':
								$column_content = '<div class="wpcac-value wpcac-value-multiple_select" data-id="' . esc_attr( $media_id ) . '" data-field="' . esc_attr( $saved_columns[ $column ]['field'] ) . '">';
								$column_content .= '<ul>';

								if ( ! empty( $value ) && is_array( $value ) ) {
									foreach ( $value as $val ) {
										$column_content .= '<li>' . esc_html( $val ) . '</li>';
									}
								}

								$column_content .= '</ul>';
								$column_content .= '</div>';

								break;
							case 'multiple_images':
								$column_content = '<div class="wpcac-value wpcac-value-multiple_images" data-id="' . esc_attr( $media_id ) . '" data-field="' . esc_attr( $saved_columns[ $column ]['field'] ) . '">';

								if ( ! empty( $value ) ) {
									foreach ( explode( ',', $value ) as $val ) {
										$column_content .= wp_get_attachment_image( $val );
									}
								}

								$column_content .= '</div>';

								break;
							default:
								if ( is_string( $value ) ) {
									$column_content = '<div class="wpcac-value" data-id="' . esc_attr( $media_id ) . '" data-type="' . esc_attr( $saved_columns[ $column ]['data_type'] ) . '" data-field="' . esc_attr( $saved_columns[ $column ]['field'] ) . '">' . esc_html( $value ) . '</div>';
								} else {
									$column_content = '<div class="wpcac-value" data-id="' . esc_attr( $media_id ) . '" data-type="' . esc_attr( $saved_columns[ $column ]['data_type'] ) . '" data-field="' . esc_attr( $saved_columns[ $column ]['field'] ) . '">[...]</div>';
								}
						}

						if ( $editable ) {
							$column_content .= '<span class="wpcac-value-actions">';
							$column_content .= '<a href="#" class="wpcac-copy hint--top" aria-label="' . esc_attr__( 'Copy', 'wpc-admin-columns' ) . '" data-type="' . esc_attr( $data_type ) . '"><span>' . esc_html__( 'copy', 'wpc-admin-columns' ) . '</span></a>';
							$column_content .= '<a href="#" class="wpcac-edit hint--top" aria-label="' . esc_attr( sprintf( /* translators: edit */ esc_html__( 'Edit "%1$s" of #%2$s', 'wpc-admin-columns' ), $saved_columns[ $column ]['field'], $media_id ) ) . '" data-name="' . esc_attr( $columns_name ) . '" data-key="' . esc_attr( $column ) . '" data-id="' . esc_attr( $media_id ) . '" data-field="' . esc_attr( $saved_columns[ $column ]['field'] ) . '" data-type="' . esc_attr( $data_type ) . '"><span>' . esc_html__( 'edit', 'wpc-admin-columns' ) . '</span></a>';
							$column_content .= '</span>';
						}
					}

					break;
				default:
					$column_text    = str_replace( '{media_id}', $media_id, $saved_columns[ $column ]['text'] );
					$column_text    = str_replace( '{attachment_id}', $media_id, $column_text );
					$column_content = ! empty( $saved_columns[ $column ]['text'] ) ? do_shortcode( $column_text ) : $media_id;
			}

			echo '<div class="wpcac-value-wrapper">' . wp_kses_post( apply_filters( 'wpcac_media_custom_column_' . $column_type, $column_content, $column, $media_id ) ) . '</div>';
		}
	}

	function taxonomy_custom_column( $column_content, $column, $term_id ) {
		$screen_key = self::get_screen_key();

		if ( empty( $screen_key ) ) {
			return null;
		}

		$columns_name  = self::get_columns_name( $screen_key );
		$saved_columns = get_option( $columns_name, [] );

		if ( is_string( $column ) && ( str_starts_with( $column, 'wpcac_' ) ) && isset( $saved_columns[ $column ] ) ) {
			$column_type = $saved_columns[ $column ]['type'];
			$editable    = ! empty( $saved_columns[ $column ]['editable'] ) && ( $saved_columns[ $column ]['editable'] === 'yes' );

			switch ( $column_type ) {
				case 'id':
				case 'term_id':
					$column_content = esc_html( $term_id );

					break;
				case 'slug':
				case 'term_slug':
					if ( $term = get_term( $term_id ) ) {
						$column_content = esc_html( $term->slug );
					}

					break;
				case 'name':
				case 'title':
				case 'term_name':
					if ( $term = get_term( $term_id ) ) {
						$column_content = esc_html( $term->name );
					}

					break;
				case 'desc':
				case 'excerpt':
				case 'term_desc':
					if ( $term = get_term( $term_id ) ) {
						$column_content = esc_html( $term->description );
					}

					break;
				case 'term_meta':
				case 'custom_field':
					if ( ! empty( $saved_columns[ $column ]['field'] ) ) {
						$value     = get_term_meta( $term_id, $saved_columns[ $column ]['field'], true ) ?: '';
						$data_type = ! empty( $saved_columns[ $column ]['data_type'] ) ? $saved_columns[ $column ]['data_type'] : '';

						switch ( $data_type ) {
							case 'color':
								$column_content = '<div class="wpcac-value wpcac-value-color" data-id="0" data-tid="' . esc_attr( $term_id ) . '" data-field="' . esc_attr( $saved_columns[ $column ]['field'] ) . '"><span style="background-color: ' . esc_attr( $value ) . '">' . esc_html( $value ) . '</span></div>';

								break;
							case 'image':
								$column_content = '<div class="wpcac-value wpcac-value-image" data-id="0" data-tid="' . esc_attr( $term_id ) . '" data-field="' . esc_attr( $saved_columns[ $column ]['field'] ) . '">' . wp_kses( wp_get_attachment_image( $value ), wp_kses_allowed_html( 'wpcac_img' ) ) . '</div>';

								break;
							case 'multiple_select':
								$column_content = '<div class="wpcac-value wpcac-value-multiple_select" data-id="0" data-tid="' . esc_attr( $term_id ) . '" data-field="' . esc_attr( $saved_columns[ $column ]['field'] ) . '">';
								$column_content .= '<ul>';

								if ( ! empty( $value ) && is_array( $value ) ) {
									foreach ( $value as $val ) {
										$column_content .= '<li>' . esc_html( $val ) . '</li>';
									}
								}

								$column_content .= '</ul>';
								$column_content .= '</div>';

								break;
							case 'multiple_images':
								$column_content = '<div class="wpcac-value wpcac-value-multiple_images" data-id="0" data-tid="' . esc_attr( $term_id ) . '" data-field="' . esc_attr( $saved_columns[ $column ]['field'] ) . '">';

								if ( ! empty( $value ) ) {
									foreach ( explode( ',', $value ) as $val ) {
										$column_content .= wp_get_attachment_image( $val );
									}
								}

								$column_content .= '</div>';

								break;
							default:
								if ( is_string( $value ) ) {
									$column_content = '<div class="wpcac-value" data-id="0" data-tid="' . esc_attr( $term_id ) . '" data-type="' . esc_attr( $saved_columns[ $column ]['data_type'] ) . '" data-field="' . esc_attr( $saved_columns[ $column ]['field'] ) . '">' . esc_html( $value ) . '</div>';
								} else {
									$column_content = '<div class="wpcac-value" data-id="0" data-tid="' . esc_attr( $term_id ) . '" data-type="' . esc_attr( $saved_columns[ $column ]['data_type'] ) . '" data-field="' . esc_attr( $saved_columns[ $column ]['field'] ) . '">[...]</div>';
								}
						}

						if ( $editable ) {
							$column_content .= '<span class="wpcac-value-actions">';
							$column_content .= '<a href="#" class="wpcac-copy hint--top" aria-label="' . esc_attr__( 'Copy', 'wpc-admin-columns' ) . '" data-type="' . esc_attr( $data_type ) . '"><span>' . esc_html__( 'copy', 'wpc-admin-columns' ) . '</span></a>';
							$column_content .= '<a href="#" class="wpcac-edit hint--top" aria-label="' . esc_attr( sprintf( /* translators: edit */ esc_html__( 'Edit "%1$s" of #%2$s', 'wpc-admin-columns' ), $saved_columns[ $column ]['field'], $term_id ) ) . '" data-name="' . esc_attr( $columns_name ) . '" data-key="' . esc_attr( $column ) . '" data-id="0" data-tid="' . esc_attr( $term_id ) . '" data-field="' . esc_attr( $saved_columns[ $column ]['field'] ) . '" data-type="' . esc_attr( $data_type ) . '"><span>' . esc_html__( 'edit', 'wpc-admin-columns' ) . '</span></a>';
							$column_content .= '</span>';
						}
					}

					break;
				default:
					$column_content = ! empty( $saved_columns[ $column ]['text'] ) ? do_shortcode( str_replace( '{term_id}', $term_id, $saved_columns[ $column ]['text'] ) ) : $term_id;
			}

			$column_content = '<div class="' . esc_attr( $editable ? 'wpcac-value-wrapper wpcac-value-wrapper-editable' : 'wpcac-value-wrapper' ) . '">' . wp_kses_post( apply_filters( 'wpcac_taxonomy_custom_column_' . $column_type, $column_content, $column, $term_id ) ) . '</div>';
		}

		return $column_content;
	}

	function sortable_columns( $columns ) {
		$screen_key = self::get_screen_key();

		if ( empty( $screen_key ) ) {
			return $columns;
		}

		$columns_name  = self::get_columns_name( $screen_key );
		$saved_columns = get_option( $columns_name, [] );

		if ( is_array( $saved_columns ) && ! empty( $saved_columns ) ) {
			foreach ( $saved_columns as $key => $column ) {
				if ( isset( $column['enable'] ) && ( $column['enable'] !== 'no' ) && ( ( $column['type'] === 'custom_field' ) || in_array( $column['type'], array_keys( self::$sortable_columns ) ) ) && ( $column['sortable'] === 'yes' ) ) {
					$columns[ $key ] = $key;
				}
			}
		}

		return $columns;
	}

	function sortable_query( $query ) {
		if ( ( $column_key = $query->get( 'orderby' ) ) && is_string( $column_key ) && ( str_starts_with( $column_key, 'wpcac_' ) ) ) {
			if ( ( $screen_key = self::get_screen_key() ) && ! empty( $screen_key ) ) {
				$columns_name  = self::get_columns_name( $screen_key );
				$saved_columns = get_option( $columns_name, [] );

				if ( ! empty( $saved_columns[ $column_key ]['sortable'] ) && ( $saved_columns[ $column_key ]['sortable'] === 'yes' ) ) {
					$column_type = $saved_columns[ $column_key ]['type'];

					if ( in_array( $column_type, array_keys( self::$sortable_columns ) ) ) {
						$query->set( 'orderby', self::$sortable_columns[ $column_type ] );
					} else {
						if ( ! empty( $saved_columns[ $column_key ]['field'] ) ) {
							if ( ! empty( $saved_columns[ $column_key ]['data_type'] ) && ( $saved_columns[ $column_key ]['data_type'] === 'number' ) ) {
								$query->set( 'orderby', 'meta_value_num' );
								$query->set( 'meta_key', $saved_columns[ $column_key ]['field'] );
							} else {
								$query->set( 'orderby', 'meta_value' );
								$query->set( 'meta_key', $saved_columns[ $column_key ]['field'] );
							}
						}
					}
				}
			}
		}
	}

	public static function get_settings() {
		return apply_filters( 'wpcac_get_settings', self::$settings );
	}

	public static function get_setting( $name, $default = false ) {
		if ( ! empty( self::$settings ) && isset( self::$settings[ $name ] ) ) {
			$setting = self::$settings[ $name ];
		} else {
			$setting = get_option( 'wpcac_' . $name, $default );
		}

		return apply_filters( 'wpcac_get_setting', $setting, $name, $default );
	}

	function register_settings() {
		// settings
		register_setting( 'wpcac_settings', 'wpcac_settings' );
	}

	function admin_menu() {
		add_submenu_page( 'wpclever', esc_html__( 'WPC Admin Columns', 'wpc-admin-columns' ), esc_html__( 'Admin Columns', 'wpc-admin-columns' ), 'manage_options', 'wpclever-wpcac', [
			$this,
			'admin_menu_content'
		] );
	}

	function admin_menu_content() {
		$active_tab = sanitize_key( $_GET['tab'] ?? 'settings' );
		?>
        <div class="wpclever_settings_page wrap">
            <h1 class="wpclever_settings_page_title"><?php echo esc_html__( 'WPC Admin Columns', 'wpc-admin-columns' ) . ' ' . esc_html( WPCAC_VERSION ) . ' ' . ( defined( 'WPCAC_PREMIUM' ) ? '<span class="premium" style="display: none">' . esc_html__( 'Premium', 'wpc-admin-columns' ) . '</span>' : '' ); ?></h1>
            <div class="wpclever_settings_page_desc about-text">
                <p>
					<?php printf( /* translators: stars */ esc_html__( 'Thank you for using our plugin! If you are satisfied, please reward it a full five-star %s rating.', 'wpc-admin-columns' ), '<span style="color:#ffb900">&#9733;&#9733;&#9733;&#9733;&#9733;</span>' ); ?>
                    <br/>
                    <a href="<?php echo esc_url( WPCAC_REVIEWS ); ?>" target="_blank"><?php esc_html_e( 'Reviews', 'wpc-admin-columns' ); ?></a> |
                    <a href="<?php echo esc_url( WPCAC_CHANGELOG ); ?>" target="_blank"><?php esc_html_e( 'Changelog', 'wpc-admin-columns' ); ?></a> |
                    <a href="<?php echo esc_url( WPCAC_DISCUSSION ); ?>" target="_blank"><?php esc_html_e( 'Discussion', 'wpc-admin-columns' ); ?></a>
                </p>
            </div>
			<?php if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) { ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Settings updated.', 'wpc-admin-columns' ); ?></p>
                </div>
			<?php } ?>
            <div class="wpclever_settings_page_nav">
                <h2 class="nav-tab-wrapper">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcac&tab=settings' ) ); ?>" class="<?php echo esc_attr( $active_tab === 'settings' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
						<?php esc_html_e( 'Settings', 'wpc-admin-columns' ); ?>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcac&tab=premium' ) ); ?>" class="<?php echo esc_attr( $active_tab === 'premium' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>" style="color: #c9356e">
						<?php esc_html_e( 'Premium Version', 'wpc-admin-columns' ); ?>
                    </a> <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-kit' ) ); ?>" class="nav-tab">
						<?php esc_html_e( 'Essential Kit', 'wpc-admin-columns' ); ?>
                    </a>
                </h2>
            </div>
            <div class="wpclever_settings_page_content">
				<?php if ( $active_tab === 'settings' ) {
					$personalized = self::get_setting( 'personalized', 'yes' );
					$json_editor  = self::get_setting( 'json_editor', 'no' );
					?>
                    <form method="post" action="options.php">
                        <table class="form-table">
                            <tr class="heading">
                                <th colspan="2">
									<?php esc_html_e( 'General', 'wpc-admin-columns' ); ?>
                                </th>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Personalized', 'wpc-admin-columns' ); ?></th>
                                <td>
                                    <select name="wpcac_settings[personalized]">
                                        <option value="yes" <?php selected( $personalized, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-admin-columns' ); ?></option>
                                        <option value="no" <?php selected( $personalized, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-admin-columns' ); ?></option>
                                    </select>
                                    <span class="description"><?php esc_html_e( 'Enable it to let each manager has their own columns organization. Their changes will not affect another.', 'wpc-admin-columns' ); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Enable JSON Editor', 'wpc-admin-columns' ); ?></th>
                                <td>
                                    <select name="wpcac_settings[json_editor]">
                                        <option value="yes" <?php selected( $json_editor, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-admin-columns' ); ?></option>
                                        <option value="no" <?php selected( $json_editor, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-admin-columns' ); ?></option>
                                    </select>
                                    <span class="description"><?php esc_html_e( 'Enable JSON Editor to editing custom field in JSON format. It uses the library https://github.com/dblate/jquery.json-editor', 'wpc-admin-columns' ); ?></span>
                                </td>
                            </tr>
                            <tr class="submit">
                                <th colspan="2">
									<?php settings_fields( 'wpcac_settings' ); ?><?php submit_button(); ?>
                                </th>
                            </tr>
                        </table>
                    </form>
				<?php } elseif ( $active_tab === 'premium' ) { ?>
                    <div class="wpclever_settings_page_content_text">
                        <p>Get the Premium Version just $29!
                            <a href="https://wpclever.net/downloads/wpc-admin-columns/?utm_source=pro&utm_medium=wpcac&utm_campaign=wporg" target="_blank">https://wpclever.net/downloads/wpc-admin-columns/</a>
                        </p>
                        <p><strong>Extra features for Premium Version:</strong></p>
                        <ul style="margin-bottom: 0">
                            <li>- Support Users Table.</li>
                            <li>- Support WooCommerce Custom Order Tables (COT).</li>
                            <li>- Get the lifetime update & premium support.</li>
                        </ul>
                    </div>
				<?php } ?>
            </div><!-- /.wpclever_settings_page_content -->
            <div class="wpclever_settings_page_suggestion">
                <div class="wpclever_settings_page_suggestion_label">
                    <span class="dashicons dashicons-yes-alt"></span> Suggestion
                </div>
                <div class="wpclever_settings_page_suggestion_content">
                    <div>
                        To display custom engaging real-time messages on any wished positions, please install
                        <a href="https://wordpress.org/plugins/wpc-smart-messages/" target="_blank">WPC Smart Messages</a> plugin. It's free!
                    </div>
                    <div>
                        Wanna save your precious time working on variations? Try our brand-new free plugin
                        <a href="https://wordpress.org/plugins/wpc-variation-bulk-editor/" target="_blank">WPC Variation Bulk Editor</a> and
                        <a href="https://wordpress.org/plugins/wpc-variation-duplicator/" target="_blank">WPC Variation Duplicator</a>.
                    </div>
                </div>
            </div>
        </div>
		<?php
	}

	function action_links( $links, $file ) {
		static $plugin;

		if ( ! isset( $plugin ) ) {
			$plugin = plugin_basename( WPCAC_FILE );
		}

		if ( $plugin === $file ) {
			$settings             = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-wpcac&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'wpc-admin-columns' ) . '</a>';
			$links['wpc-premium'] = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-wpcac&tab=premium' ) ) . '">' . esc_html__( 'Premium Version', 'wpc-admin-columns' ) . '</a>';
			array_unshift( $links, $settings );
		}

		return (array) $links;
	}

	function row_meta( $links, $file ) {
		static $plugin;

		if ( ! isset( $plugin ) ) {
			$plugin = plugin_basename( WPCAC_FILE );
		}

		if ( $plugin === $file ) {
			$row_meta = [
				'support' => '<a href="' . esc_url( WPCAC_DISCUSSION ) . '" target="_blank">' . esc_html__( 'Community support', 'wpc-admin-columns' ) . '</a>',
			];

			return array_merge( $links, $row_meta );
		}

		return (array) $links;
	}

	function sanitize_array( $arr ) {
		foreach ( (array) $arr as $k => $v ) {
			if ( is_array( $v ) ) {
				$arr[ $k ] = self::sanitize_array( $v );
			} else {
				$arr[ $k ] = sanitize_text_field( $v );
			}
		}

		return $arr;
	}

	function get_meta_keys( $screen_key = 'product' ) {
		global $wpdb;
		$transient_key = 'wpcac_get_' . $screen_key . '_meta_keys';
		$get_meta_keys = get_transient( $transient_key );

		if ( true === (bool) $get_meta_keys ) {
			return $get_meta_keys;
		}

		if ( $screen_key === 'users' ) {
			$get_meta_keys = $wpdb->get_col( "SELECT DISTINCT meta_key FROM {$wpdb->usermeta} ORDER BY meta_key ASC" );

			set_transient( $transient_key, $get_meta_keys, DAY_IN_SECONDS );

			return $get_meta_keys;
		} elseif ( in_array( $screen_key, self::$taxonomies ) ) {
			// taxonomies
			$get_meta_keys = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT pm.meta_key FROM {$wpdb->termmeta} pm 
        LEFT JOIN {$wpdb->term_taxonomy} p ON p.term_id = pm.term_id 
        WHERE p.taxonomy = %s ORDER BY pm.meta_key ASC", $screen_key ) );

			set_transient( $transient_key, $get_meta_keys, DAY_IN_SECONDS );

			return $get_meta_keys;
		} else {
			// post types
			global $wp_post_types;

			if ( ! isset( $wp_post_types[ $screen_key ] ) ) {
				return false;
			}

			$get_meta_keys = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT pm.meta_key FROM {$wpdb->postmeta} pm 
        LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id 
        WHERE p.post_type = %s ORDER BY pm.meta_key ASC", $screen_key ) );

			set_transient( $transient_key, $get_meta_keys, DAY_IN_SECONDS );

			return $get_meta_keys;
		}
	}

	public function action_duplicate() {
		if ( ! ( isset( $_GET['post'] ) || isset( $_POST['post'] ) || ( isset( $_REQUEST['action'] ) && 'wpcac_duplicate' === $_REQUEST['action'] ) ) ) {
			wp_die( esc_html__( 'No post to duplicate!', 'wpc-admin-columns' ) );
		}

		$postid = ( isset( $_GET['post'] ) ? sanitize_text_field( $_GET['post'] ) : sanitize_text_field( $_POST['post'] ) );

		check_admin_referer( 'wpcac-duplicate-' . $postid );

		$post = get_post( $postid );

		if ( isset( $post ) ) {
			$postType        = $post->post_type;
			$createDuplicate = Wpcac_Duplicate::getInstance();
			$newPostId       = $createDuplicate->createDuplicate( $post );
			$redirect        = wp_get_referer();

			if ( ! $redirect ||
			     str_contains( $redirect, 'post.php' ) ||
			     str_contains( $redirect, 'post-new.php' ) ) {
				if ( 'attachment' == $postType ) {
					$redirect = admin_url( 'upload.php' );
				} else {
					$redirect = admin_url( 'edit.php' );

					if ( ! empty( $postType ) ) {
						$redirect = add_query_arg( 'post_type', $postType, $redirect );
					}
				}
			} else {
				$redirect = remove_query_arg( [ 'trashed', 'untrashed', 'deleted', 'ids' ], $redirect );
			}

			wp_safe_redirect( add_query_arg( [ 'ids' => $post->ID ], $redirect ) );

			exit;
		} else {
			wp_die( esc_html__( 'Copy creation failed, could not find original:', 'wpc-admin-columns' ) . ' ' . htmlspecialchars( $postid ) );
		}
	}

	public static function get_duplicate_link( $postid = 0 ) {
		if ( ! $post = get_post( $postid ) ) {
			return '';
		}

		$postType = get_post_type_object( $post->post_type );

		if ( ! $postType ) {
			return '';
		}

		return wp_nonce_url( admin_url( 'admin.php' . '?action=wpcac_duplicate&amp;post=' . $post->ID ), 'wpcac-duplicate-' . $post->ID );
	}

	public static function is_json( $string ) {
		if ( in_array( $string, [ 'true', 'false', 'yes', 'no', '1', '0' ] ) ) {
			return false;
		}

		json_decode( $string );

		return json_last_error() === JSON_ERROR_NONE;
	}

	public static function string_to_bool( $string ) {
		return is_bool( $string ) ? $string : ( 'yes' === strtolower( $string ) || 1 === $string || 'true' === strtolower( $string ) || '1' === $string );
	}

	function generate_key() {
		$key         = '';
		$key_str     = apply_filters( 'wpcac_key_characters', 'abcdefghijklmnopqrstuvwxyz0123456789' );
		$key_str_len = strlen( $key_str );

		for ( $i = 0; $i < apply_filters( 'wpcac_key_length', 4 ); $i ++ ) {
			$key .= $key_str[ random_int( 0, $key_str_len - 1 ) ];
		}

		if ( is_numeric( $key ) ) {
			$key = self::generate_key();
		}

		return apply_filters( 'wpcac_generate_key', $key );
	}
}

Wpcac_Backend::instance();
