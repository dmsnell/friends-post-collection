<?php
/**
 * Friends Post Collection
 *
 * This contains the post collection functions.
 *
 * @package Friends_Post_Collection
 */

namespace Friends;

/**
 * This is the class for the downloading and storing posts for the Friends Plugin.
 *
 * @since 0.1
 *
 * @package Friends_Post_Collection
 * @author Alex Kirk
 */
class Post_Collection {
	const CPT = 'post_collection';
	/**
	 * Whether to cache the retrieved users
	 *
	 * @var boolean
	 */
	public static $cache = true;

	/**
	 * Contains a reference to the Friends class.
	 *
	 * @var Friends
	 */
	private $friends;

	/**
	 * Tracks whether how many items were already fetched for a feed.
	 *
	 * @var array
	 */
	private $fetched_for_feed = array();

	/**
	 * Constructor
	 *
	 * @param Friends $friends A reference to the Friends object.
	 */
	public function __construct( Friends $friends ) {
		$this->friends = $friends;
		$this->register_hooks();
	}

	/**
	 * Register the WordPress hooks
	 */
	private function register_hooks() {
		add_action( 'init', array( $this, 'register_custom_post_type' ) );
		add_filter( 'friends_frontend_post_types', array( $this, 'friends_frontend_post_types' ) );
		add_action( 'tool_box', array( $this, 'toolbox_bookmarklet' ) );
		add_filter( 'user_row_actions', array( $this, 'user_row_actions' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 50 );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_new_content' ), 72 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 99999 );
		add_action( 'wp_loaded', array( $this, 'save_url_endpoint' ), 100 );
		add_filter( 'get_edit_user_link', array( $this, 'edit_post_collection_link' ), 10, 2 );
		add_action( 'friend_post_edit_link', array( $this, 'allow_post_editing' ), 10, 2 );
		add_action( 'friends_entry_dropdown_menu', array( $this, 'entry_dropdown_menu' ) );
		add_action( 'friends_friend_feed_viewable', array( $this, 'friends_friend_feed_viewable' ), 10, 2 );
		add_action( 'friend_user_role_name', array( $this, 'friend_user_role_name' ), 10, 2 );
		add_filter( 'friends_plugin_roles', array( $this, 'associate_friend_user_role' ) );
		add_action( 'friends_override_author_name', array( $this, 'friends_override_author_name' ), 15, 3 );
		add_action( 'friends_widget_friend_list_after', array( $this, 'friends_widget_friend_list_after' ), 10, 2 );
		add_action( 'friends_author_header', array( $this, 'friends_author_header' ) );
		add_action( 'friends_post_footer_first', array( $this, 'share_button' ) );
		add_action( 'friends_feed_table_header', array( $this, 'feed_table_header' ) );
		add_action( 'friends_feed_table_row', array( $this, 'feed_table_row' ), 10, 2 );
		add_action( 'friends_process_feed_item_submit', array( $this, 'feed_item_submit' ), 10, 2 );
		add_action( 'friends_modify_feed_item', array( $this, 'modify_feed_item' ), 10, 4 );
		add_filter( 'friends_can_update_modified_feed_posts', array( $this, 'can_update_modified_feed_posts' ), 10, 5 );
		add_action( 'friends_after_register_feed_taxonomy', array( $this, 'after_register_feed_taxonomy' ) );
		add_action( 'wp_ajax_friends-post-collection-mark-publish', array( $this, 'wp_ajax_mark_publish' ) );
		add_action( 'wp_ajax_friends-post-collection-mark-private', array( $this, 'wp_ajax_mark_private' ) );
		add_action( 'wp_ajax_friends-post-collection-change-author', array( $this, 'wp_ajax_change_author' ) );
		add_action( 'wp_ajax_friends-post-collection-fetch-full-content', array( $this, 'wp_ajax_fetch_full_content' ) );
	}

	/**
	 * Registers the custom post type
	 */
	public function register_custom_post_type() {
		$labels = array(
			'name'               => __( 'Collected Posts', 'friends' ),
			'singular_name'      => __( 'Collected Post', 'friends' ),
			'add_new'            => _x( 'Add New', 'collected post', 'friends' ),
			'add_new_item'       => __( 'Add New Collected Post', 'friends' ),
			'edit_item'          => __( 'Edit Collected Post', 'friends' ),
			'new_item'           => __( 'New Collected Post', 'friends' ),
			'all_items'          => __( 'All Collected Posts', 'friends' ),
			'view_item'          => __( 'View Collected Post', 'friends' ),
			'search_items'       => __( 'Search Collected Posts', 'friends' ),
			'not_found'          => __( 'No Collected Posts found', 'friends' ),
			'not_found_in_trash' => __( 'No Collected Posts found in the Trash', 'friends' ),
			'parent_item_colon'  => '',
			'menu_name'          => __( 'Collected Posts', 'friends' ),
		);

		$args = array(
			'labels'              => $labels,
			'description'         => __( 'A collected post', 'friends' ),
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => apply_filters( 'friends_show_cached_posts', false ),
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'show_in_rest'        => current_user_can( Friends::REQUIRED_ROLE ),
			'exclude_from_search' => true,
			'public'              => true,
			'delete_with_user'    => true,
			'menu_position'       => 5,
			'menu_icon'           => 'dashicons-pressthis',
			'supports'            => array( 'title', 'editor', 'author', 'revisions', 'thumbnail', 'excerpt', 'comments', 'post-formats' ),
			'taxonomies'          => array( 'post_tag', 'post_format' ),
			'has_archive'         => true,
		);

		if ( isset( $_GET['search'] ) && is_user_logged_in() ) {
			$args['show_in_rest'] = true;
		}

		register_post_type( self::CPT, $args );
	}

	public function friends_frontend_post_types( $post_types ) {
		$post_types[] = self::CPT;
		return $post_types;
	}

	/**
	 * Get the Friends\Template_Loader singleton
	 *
	 * @return Friends\Template_Loader A class instance.
	 */
	public static function template_loader() {
		static $template_loader;
		if ( ! isset( $template_loader ) ) {
			require_once __DIR__ . '/class-post-collection-template-loader.php';
			$template_loader = new Post_Collection_Template_Loader();
		}
		return $template_loader;
	}

	public function allow_post_editing( $link, $original ) {
		if ( $this->is_post_collection_user( get_the_author_meta( 'ID' ) ) ) {
			return $original;
		}
		return $link;
	}

	public function entry_dropdown_menu() {
		$divider = '<li class="divider" data-content="' . esc_attr__( 'Post Collection', 'friends' ) . '"></li>';
		$list_tags = array(
			'li' => array(
				'class'        => true,
				'data-content' => true,
			),
		);
		$user_id = get_the_author_meta( 'ID' );
		if ( $this->is_post_collection_user( $user_id ) ) {
			echo wp_kses( $divider, $list_tags );
			$divider = '';
			?>
			<li class="menu-item"><a href="<?php echo esc_url( get_edit_user_link( $user_id ) ); ?>"><?php esc_html_e( 'Edit Post Collection', 'friends' ); ?></a></li>
			<?php
			if ( 'private' === get_post_status() ) {
				?>
				<li class="menu-item"><a href="#" data-id="<?php echo esc_attr( get_the_ID() ); ?>" class="friends-post-collection-mark-publish"><?php esc_html_e( 'Show post in the feed', 'friends' ); ?></a></li>
				<?php
			} elseif ( 'publish' === get_post_status() ) {
				?>
					<li class="menu-item"><a href="#" data-id="<?php echo esc_attr( get_the_ID() ); ?>" class="friends-post-collection-mark-private"><?php esc_html_e( 'Hide post from the feed', 'friends' ); ?></a></li>
				<?php
			}
		}

		foreach ( $this->get_post_collection_users()->get_results() as $user ) {
			if ( intval( $user_id ) === intval( $user->ID ) ) {
				continue;
			}
			echo wp_kses( $divider, $list_tags );
			$divider = '';
			?>
			<li class="menu-item"><a href="#" data-id="<?php echo esc_attr( get_the_ID() ); ?>" data-author="<?php echo esc_attr( $user->ID ); ?>" data-first="<?php echo esc_attr( $user->ID ); ?>" class="friends-post-collection-change-author has-icon-right">
				<?php
					echo esc_html(
						sprintf(
							// translators: %s is the name of a post collection.
							_x( 'Move to %s', 'post-collection', 'friends' ),
							$user->display_name
						)
					);
				?>
				<i class="form-icon"></i></a>
			</li>
			<?php
		}

		$already_fetched = get_post_meta( get_the_ID(), 'full-content-fetched', true );
		$i_classes = 'form-icon';
		if ( $already_fetched ) {
			$i_classes = 'dashicons dashicons-saved';
		}

		?>
		<li class="menu-item"><a href="#" data-id="<?php echo esc_attr( get_the_ID() ); ?>" data-author="<?php echo esc_attr( get_the_author_meta( 'ID' ) ); ?>" class="friends-post-collection-fetch-full-content has-icon-right">
			<?php
				esc_html_e( 'Fetch full content', 'friends' );
			?>
			<i class="<?php echo esc_attr( $i_classes ); ?>"></i></a>
		</li>
		<?php
	}

	public function edit_post_collection_link( $link, $user_id ) {
		$user = new \WP_User( $user_id );
		if ( is_multisite() && is_super_admin( $user->ID ) ) {
			return $link;
		}
		if (
			! $user->has_cap( 'post_collection' )
		) {
			return $link;
		}

		return self_admin_url( 'admin.php?page=edit-post-collection&user=' . $user_id );
	}


	public function is_post_collection_user( $user_id ) {
		static $cache = array();

		if ( ! isset( $cache[ $user_id ] ) ) {
			$user = new User( $user_id );
			$cache[ $user_id ] = $user->has_cap( 'post_collection' );
		}

		return $cache[ $user_id ];
	}

	/**
	 * Process access for the Friends Edit User page
	 */
	private function check_edit_post_collection() {
		if ( ! current_user_can( Friends::REQUIRED_ROLE ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to edit this user.' ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		}

		if ( ! isset( $_GET['user'] ) || ! is_numeric( $_GET['user'] ) ) {
			wp_die( esc_html__( 'Invalid user ID.' ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		}

		$user = new User( intval( $_GET['user'] ) );
		if ( ! $user || is_wp_error( $user ) ) {
			wp_die( esc_html__( 'Invalid user ID.' ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		}

		if ( is_multisite() && is_super_admin( $_GET['user'] ) ) {
			wp_die( esc_html__( 'Invalid user ID.' ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		}

		if (
			! $user->has_cap( 'post_collection' )
		) {
			wp_die( esc_html__( 'This is not a user related to this plugin.', 'friends' ) );
		}

		return $user;
	}

	/**
	 * Process the Friends Edit Post Collection page
	 */
	public function process_edit_post_collection() {
		$user    = $this->check_edit_post_collection();
		$arg       = 'updated';
		$arg_value = 1;

		if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'edit-post-collection-' . $user->ID ) ) {

			if ( trim( $_POST['display_name'] ) ) {
				$user->display_name = trim( $_POST['display_name'] );
			}
			$user->description = trim( $_POST['description'] );
			wp_update_user( $user );

			if ( isset( $_POST['publish_post_collection'] ) && $_POST['publish_post_collection'] ) {
				update_user_option( $user->ID, 'friends_publish_post_collection', true );
			} else {
				delete_user_option( $user->ID, 'friends_publish_post_collection' );
			}
		} else {
			return;
		}

		if ( isset( $_GET['wp_http_referer'] ) ) {
			wp_safe_redirect( $_GET['wp_http_referer'] );
		} else {
			wp_safe_redirect( add_query_arg( $arg, $arg_value, remove_query_arg( array( 'wp_http_referer', '_wpnonce' ), wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) );
		}
		exit;
	}

	public function render_edit_post_collection() {
		$user = $this->check_edit_post_collection();
		$args = array(
			'user'                => $user,
			'posts'               => new \WP_Query(
				array(
					'post_type'   => self::CPT,
					'post_status' => array( 'publish', 'private' ),
					'author'      => $user->ID,
				)
			),
			'post_collection_url' => home_url( '/?user=' . $user->ID ),
			'bookmarklet_js'      => $this->get_bookmarklet_js(),

		);

		?>
		<h1><?php echo esc_html( $user->user_login ); ?></h1>
		<?php

		if ( isset( $_GET['updated'] ) ) {
			?>
			<div id="message" class="updated notice is-dismissible"><p><?php esc_html_e( 'User was updated.', 'friends' ); ?></p></div>
			<?php
		} elseif ( isset( $_GET['error'] ) ) {
			?>
			<div id="message" class="updated error is-dismissible"><p><?php esc_html_e( 'An error occurred.', 'friends' ); ?></p></div>
			<?php
		}

		$this->template_loader()->get_template_part( 'admin/edit-post-collection', null, $args );
	}

	/**
	 * Process access for the Friends create User page
	 */
	private function check_create_post_collection() {
		if ( ! current_user_can( Friends::REQUIRED_ROLE ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to create this user.' ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		}

		$user = (object) array(
			'user_login'   => null,
			'display_name' => null,
		);
		if ( isset( $_POST['display_name'] ) ) {
			$user->display_name = sanitize_text_field( $_POST['display_name'] );
		}

		if ( isset( $_POST['user_login'] ) ) {
			$user->user_login = sanitize_user( $_POST['user_login'] );
			if ( ! $user->user_login && $user->display_name ) {
				$user->user_login = User::sanitize_username( $user->display_name );
			}
		}
		return $user;
	}

	/**
	 * Process the Friends Create Post Collection page
	 */
	public function process_create_post_collection() {
		$errors = new \WP_Error;
		$user   = $this->check_create_post_collection();

		if ( ! $user->user_login ) {
			$errors->add( 'user_login', __( '<strong>Error</strong>: This username is invalid because it uses illegal characters. Please enter a valid username.' ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		} elseif ( username_exists( $user->user_login ) ) {
			$errors->add( 'user_login', __( '<strong>Error</strong>: This username is already registered. Please choose another one.' ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		} elseif ( ! $user->display_name ) {
			$errors->add( 'user_login', __( '<strong>Error</strong>: Please enter a valid display name.' ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		}

		if ( ! $errors->has_errors() ) {
			$userdata  = array(
				'user_login'   => $user->user_login,
				'display_name' => $user->display_name,
				'user_pass'    => wp_generate_password( 256 ),
				'role'         => 'post_collection',
			);
			$user_id = wp_insert_user( $userdata );
			if ( is_wp_error( $user_id ) ) {
				return $user_id;
			}
			wp_safe_redirect( self_admin_url( 'admin.php?page=edit-post-collection&user=' . $user_id ) );
			exit;
		}

		return $errors;
	}

	public function render_create_post_collection() {
		$response = null;
		$user     = $this->check_create_post_collection();

		if ( ! empty( $_POST ) ) {
			if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'create-post-collection' ) ) {
				$response = new \WP_Error( 'invalid-nonce', __( 'For security reasons, please verify the URL and click next if you want to proceed.', 'friends' ) );
			} else {
				$response = $this->process_create_post_collection();
			}
		}

		?>
		<h1><?php esc_html_e( 'Create Post Collection', 'friends' ); ?></h1>
		<?php

		if ( is_wp_error( $response ) ) {
			?>
			<div id="message" class="updated notice is-dismissible"><p>
			<?php
			echo wp_kses(
				$response->get_error_message(),
				array(
					'strong' => array(),
					'a'      => array(
						'href'   => array(),
						'rel'    => array(),
						'target' => array(),
					),
				)
			);
			?>
				</p>
			</div>
			<?php
		}

		$args = array(
			'user_login'   => $user->user_login,
			'display_name' => $user->display_name,
		);

		$this->template_loader()->get_template_part( 'admin/create-post-collection', null, $args );
	}

	public function enqueue_scripts() {
		if ( ! class_exists( 'Friends\Friends' ) ) {
			return;
		}

		if ( is_user_logged_in() && Friends::on_frontend() ) {
			wp_enqueue_script( 'send-to-e-reader', plugins_url( 'friends-post-collection.js', __FILE__ ), array( 'friends' ), 1.0 );
		}
	}

	public function admin_menu() {
		// Only show the menu if installed standalone.
		$friends_settings_exist = '' !== menu_page_url( 'friends', false );
		if ( $friends_settings_exist ) {
			$menu_title = __( 'Friends', 'friends' ) . $this->friends->admin->get_unread_badge();
			$page_type = sanitize_title( $menu_title );

			add_submenu_page(
				'friends',
				__( 'Post Collection', 'friends' ),
				__( 'Post Collection', 'friends' ),
				'edit_private_posts',
				'friends-post-collection',
				array( $this, 'about_page' )
			);
		} else {
			$menu_title = __( 'Friends Post Collection', 'friends' );
			$page_type = sanitize_title( $menu_title );

			add_menu_page( 'friends', __( 'Friends Post Collection', 'friends' ), 'edit_private_posts', 'friends', null, 'dashicons-groups', 3 );
			add_submenu_page(
				'friends',
				__( 'About', 'friends' ),
				__( 'About', 'friends' ),
				'edit_private_posts',
				'friends',
				array( $this, 'about_page_with_friends_about' )
			);

		}

		if ( isset( $_GET['page'] ) && 'create-post-collection' === $_GET['page'] ) {
			add_submenu_page( 'friends', __( 'Create Post Collection', 'friends' ), __( 'Create Post Collection', 'friends' ), Friends::REQUIRED_ROLE, 'create-post-collection', array( $this, 'render_create_post_collection' ) );
			add_action( 'load-' . $page_type . '_page_create-post-collection', array( $this, 'process_create_post_collection' ) );
		}

		if ( isset( $_GET['page'] ) && 'edit-post-collection' === $_GET['page'] ) {
			add_submenu_page( 'friends', __( 'Edit Post Collection', 'friends' ), __( 'Edit Post Collection', 'friends' ), Friends::REQUIRED_ROLE, 'edit-post-collection' . ( 'edit-post-collection' !== $_GET['page'] && isset( $_GET['user'] ) ? '&user=' . $_GET['user'] : '' ), array( $this, 'render_edit_post_collection' ) );
			add_action( 'load-' . $page_type . '_page_edit-post-collection', array( $this, 'process_edit_post_collection' ) );
		}
	}


	/**
	 * Add a Post Collection entry to the New Content admin section
	 *
	 * @param  \WP_Admin_Bar $wp_menu The admin bar to modify.
	 */
	public function admin_bar_new_content( \WP_Admin_Bar $wp_menu ) {
		if ( current_user_can( Friends::REQUIRED_ROLE ) ) {
			$wp_menu->add_menu(
				array(
					'id'     => 'new-post-collection',
					'parent' => 'new-content',
					'title'  => esc_html__( 'Post Collection', 'friends' ),
					'href'   => self_admin_url( 'admin.php?page=create-post-collection' ),
				)
			);
		}
	}
	/**
	 * Add actions to the user rows
	 *
	 * @param  array    $actions The existing actions.
	 * @param  \WP_User $user    The user in question.
	 * @return array The extended actions.
	 */
	public function user_row_actions( array $actions, \WP_User $user ) {
		if (
			! current_user_can( Friends::REQUIRED_ROLE ) ||
			(
				! $user->has_cap( 'post_collection' )
			)
		) {
			return $actions;
		}

		if ( is_multisite() ) {
			if ( is_super_admin( $user->ID ) ) {
				return $actions;
			}

			$actions = array_merge( array( 'edit' => '<a href="' . esc_url( self_admin_url( 'admin.php?page=edit-post-collection&user=' . $user->ID ) ) . '">' . __( 'Edit' ) . '</a>' ), $actions ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		}

		$friend_user = new User( $user );
		$actions['view'] = '<a href="' . esc_url( $friend_user->get_local_friends_page_url() ) . '">' . __( 'View' ) . '</a>'; // phpcs:ignore WordPress.WP.I18n.MissingArgDomain

		unset( $actions['resetpassword'] );

		return $actions;
	}

	/**
	 * Display an about page for the plugin.
	 *
	 * @param      bool $display_about_friends  The display about friends section.
	 */
	public function about_page( $display_about_friends = false ) {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Friends Post Collection', 'friends' ); ?></h1>

			<p><?php esc_html_e( 'The Friends Post Collection plugin allows you to save external posts to your WordPress, either for just collecting them for yourself as a searchable archive, or to syndicate those posts into new feeds.', 'friends' ); ?></p>

			<?php
			$this->template_loader()->get_template_part(
				'admin/settings-post-collection',
				null,
				array(
					'post_collections' => $this->get_post_collection_users()->get_results(),
					'bookmarklet_js'   => $this->get_bookmarklet_js(),
				)
			);

			if ( $display_about_friends ) :
				?>
				<p>
				<?php
				echo wp_kses(
						// translators: %s: URL to the Friends Plugin page on WordPress.org.
					sprintf( __( 'The Friends plugin is all about connecting with friends and news. Learn more on its <a href=%s>plugin page on WordPress.org</a>.', 'friends' ), '"https://wordpress.org/plugins/friends" target="_blank" rel="noopener noreferrer"' ),
					array(
						'a' => array(
							'href'   => array(),
							'rel'    => array(),
							'target' => array(),
						),
					)
				);
				?>
				</p>
			<?php endif; ?>
			<p>
				<?php
				echo wp_kses(
					// translators: %s: URL to the Embed library.
					sprintf( __( 'This plugin is uses information of the open source project <a href=%s>FTR Site Config</a>.', 'friends' ), '"https://github.com/fivefilters/ftr-site-config" target="_blank" rel="noopener noreferrer"' ),
					array(
						'a' => array(
							'href'   => array(),
							'rel'    => array(),
							'target' => array(),
						),
					)
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Display an about page for the plugin with the friends section.
	 */
	public function about_page_with_friends_about() {
		return $this->about_page( true );
	}

	public function get_post_collection_users() {
		static $users;
		if ( ! self::$cache || ! isset( $users ) ) {
			$users = new \WP_User_Query(
				array(
					'role'    => 'post_collection',
					'order'   => 'ASC',
					'orderby' => 'display_name',
				)
			);
		}
		return $users;
	}

	private function get_bookmarklet_js() {
		$js = file_get_contents( __DIR__ . '/friends-post-collection-injector.js' );
		$js = str_replace( 'text.sending_article_to_your_blog', '"' . addslashes( __( 'Sending the article to your blog...', 'friends' ) ) . '"', $js );
		$js = str_replace( 'text.do_you_want_to_send_the_article_to_your_blog', '"' . addslashes( __( 'Do you want to send the article on this page to your blog?', 'friends' ) ) . '"', $js );
		$js = str_replace( PHP_EOL, '', preg_replace( '/\s+/', ' ', $js ) );
		return $js;
	}

	/**
	 * Display the Bookmarklet at the Tools section of wp-admin
	 */
	public function toolbox_bookmarklet() {
		$post_collections = array();
		foreach ( $this->get_post_collection_users()->get_results() as $user ) {
			$url = home_url( '/?user=' . $user->ID );
			$post_collections[ $url ] = $user->display_name;
		}

		$this->template_loader()->get_template_part(
			'admin/tools-post-collection',
			null,
			array(
				'post_collections' => $post_collections,
				'bookmarklet_js'   => $this->get_bookmarklet_js(),
			)
		);
	}

	public function save_url_endpoint() {
		$delimiter = '===BODY===';
		$url = false;
		if ( isset( $_REQUEST['collect-post'] ) && isset( $_REQUEST['user'] ) ) {
			if ( ! intval( $_REQUEST['user'] ) ) {
				return;
			}
			$saved_body = get_user_option( 'friends-post-collection_last_save', $_REQUEST['user'] );
			list( $last_url, $last_body ) = explode( $delimiter, $saved_body ? $saved_body : $delimiter );
			$url = wp_unslash( $_REQUEST['collect-post'] );
			$body = false;
			if ( isset( $_POST['body'] ) ) {
				$body = wp_unslash( $_POST['body'] );
			} elseif ( $last_url === $url ) {
				$body = $last_body;
			}
		}

		if ( ! $url ) {
			return;
		}

		if ( $body ) {
			update_user_option( $_REQUEST['user'], 'friends-post-collection_last_save', $url . $delimiter . $body );
		}

		if ( ! current_user_can( Friends::REQUIRED_ROLE ) ) {
			auth_redirect();
		}

		$friend_user = new User( intval( $_REQUEST['user'] ) );
		if ( ! is_wp_error( $friend_user ) || ! $friend_user->has_cap( 'post_collection' ) ) {
			$error = $this->save_url( $url, $friend_user, $body );
			if ( is_wp_error( $error ) ) {
				echo '<pre>';
				print_r( $error );
				exit;
			}
		}
	}

	/**
	 * Download and save the URL content
	 *
	 * @param  string $url The URL to save.
	 * @param  User   $friend_user  The user.
	 * @param  string $content      The content.
	 * @return \WP_Error    Potentially an error message.
	 */
	public function save_url( $url, User $friend_user, $content = null ) {
		if ( ! is_string( $url ) || ! Friends::check_url( $url ) ) {
			return new \WP_Error( 'invalid-url', __( 'You entered an invalid URL.', 'friends' ) );
		}

		$post_id = Friends::get_instance()->feed->url_to_postid( $url, $friend_user->ID );
		if ( is_null( $post_id ) ) {
			$item = $this->download( $url, $content );
			if ( is_wp_error( $item ) ) {
				return $item;
			}

			if ( ! $item->content && ! $item->title ) {
				return new \WP_Error( 'invalid-content', __( 'No content was extracted.', 'friends' ) );
			}

			$title   = strip_tags( trim( $item->title ) );
			$content = force_balance_tags( trim( wp_kses_post( $item->content ) ) );

			$post_data = array(
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => 'private',
				'post_author'  => $friend_user->ID,
				'guid'         => $item->url,
				'post_type'    => self::CPT,
			);

			$post_id = wp_insert_post( $post_data, true );

			if ( $item->author ) {
				update_post_meta( $post_id, 'author', $item->author );
			}
		}
		wp_untrash_post( $post_id );
		wp_safe_redirect( $friend_user->get_local_friends_page_url( $post_id ) );
		exit;
	}

	/**
	 * Download the url from the URL
	 *
	 * @param  string $url The URL to download.
	 * @param  string $content      The content.
	 * @return object An item object.
	 */
	public function download( $url, $content = null ) {
		global $wp_version;
		$args = array(
			'timeout'     => 20,
			'redirection' => 5,
			'headers'     => array(
				'user-agent' => 'WordPress/' . $wp_version . '; ' . home_url( '/' ) . '; Friends/' . Friends::VERSION,
			),
		);

		if ( ! $content ) {
			$response = wp_safe_remote_get( $url, $args );
			if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
				return new \WP_Error(
					'could-not-download',
					__( 'Could not download the URL.', 'friends' ),
					array(
						'url'         => $url,
						'http_status' => wp_remote_retrieve_response_code( $response ),
						'http_body'   => wp_remote_retrieve_body( $response ),
					)
				);
			}
			$content = wp_remote_retrieve_body( $response );
		}

		$item = $this->extract_content( $content, $url );
		return $item;

	}

	/**
	 * Extract the content of a URL
	 *
	 * @param  string $html        The HTML from which to extract the content.
	 * @param  array  $url The url.
	 * @return object The parsed content.
	 */
	public function extract_content( $html, $url ) {
		$item = (object) array(
			'title'   => false,
			'content' => false,
			'url'     => $url,
		);

		$config = new \andreskrey\Readability\Configuration();
		$logger = null;
		if ( class_exists( '\\Ozh\\Log\\Logger' ) ) {
			$logger = new \Ozh\Log\Logger();
			$config->setLogger( $logger );
		}
		$config->setFixRelativeURLs( true );
		$config->setOriginalURL( $url );
		$readability = new \andreskrey\Readability\Readability( $config );

		try {
			$readability->parse( $html );
			$item->title = $readability->getTitle();
			$item->content = $readability->getContent();
			$item->author = $readability->getAuthor();

			$item->content = str_replace( '&#xD;', '', $item->content );
		} catch ( \andreskrey\Readability\ParseException $e ) {
			return new \WP_Error(
				'could-not-extract-content',
				sprintf(
				// translators: $s is an error message.
					__( 'Error processing HTML: %s', 'friends' ),
					$e->getMessage()
				),
				$logger
			);
		}

		return $item;
	}

	/**
	 * Extract the innerHTML of a node
	 *
	 * @param  object $node The DOM node or a DOMNodeList.
	 * @return string The innerHTML.
	 */
	private function get_inner_html( $node ) {
		$html = '';
		if ( $node instanceof \DOMNodeList ) {
			$nodelist = $node;
		} elseif ( isset( $node->childNodes ) ) { // @codingStandardsIgnoreLine
			$nodelist = $node->childNodes; // @codingStandardsIgnoreLine
		} else {
			return false;
		}

		foreach ( $nodelist as $child ) {
			$html .= $child->innerHTML; // @codingStandardsIgnoreLine
		}
		return $html;
		return $this->clean_html( $html );
	}

	/**
	 * Remove the node from the DOM.
	 *
	 * @param  object $node The DOM node or a DOMNodeList to remove.
	 */
	private function remove_node( $node ) {
		if ( $node instanceof \DOMNodeList ) {
			$nodelist = $node;
		} elseif ( isset( $node->childNodes ) ) { // @codingStandardsIgnoreLine
			$nodelist = $node->childNodes; // @codingStandardsIgnoreLine
		} else {
			return false;
		}

		foreach ( $nodelist as $child ) {
			$child->parentNode->removeChild( $child ); // @codingStandardsIgnoreLine
		}
	}

	/**
	 * Clean the HTML
	 *
	 * @param  string $html The HTML to be cleaned.
	 * @return string       The cleaned HTML.
	 */
	private function clean_html( $html ) {
		$html = preg_replace( '#\n\s*\n\s*#', PHP_EOL . PHP_EOL, trim( $html ) );

		return $html;
	}

	/**
	 * Overwrite the role name for a post collection user.
	 *
	 * @param      string   $name   The name.
	 * @param      \WP_User $user   The user.
	 *
	 * @return     string The potentially modified name.
	 */
	public function friend_user_role_name( $name, \WP_User $user ) {
		if ( ! $name && $user->has_cap( 'post_collection' ) ) {
			$name = _x( 'Post Collection', 'User role', 'friends' );
		}

		return $name;
	}

	/**
	 * Associate the role with the Friends plugin.
	 *
	 * @param      array $roles  The roles.
	 *
	 * @return     array  The roles with the added Post Collection.
	 */
	public function associate_friend_user_role( $roles ) {
		$roles[] = 'post_collection';
		return $roles;
	}

	/**
	 * Potentially override the post author name with metadata.
	 *
	 * @param      string $overridden_author_name  The already overridden author name.
	 * @param      string $author_name  The author name.
	 * @param      int    $post_id      The post id.
	 *
	 * @return     string  The modified author name.
	 */
	public function friends_override_author_name( $overridden_author_name, $author_name, $post_id ) {
		if ( $overridden_author_name && $overridden_author_name !== $author_name ) {
			return $overridden_author_name;
		}
		$post = get_post( $post_id );
		$author = new \WP_User( $post->post_author );
		if ( is_wp_error( $author ) ) {
			return $author_name;
		}

		if ( ! $author->has_cap( 'post_collection' ) ) {
			return $author_name;
		}

		$host = wp_parse_url( $post->guid, PHP_URL_HOST );

		return sanitize_text_field( preg_replace( '#^www\.#', '', preg_replace( '#[^a-z0-9.-]+#i', ' ', strtolower( $host ) ) ) );
	}

	/**
	 * Expose the feed for the specific user.
	 *
	 * @param      bool   $viewable      Whether it's viewable.
	 * @param      string $author_login  The author login.
	 *
	 * @return     bool    Whether it's viewable.
	 */
	function friends_friend_feed_viewable( $viewable, $author_login ) {
		$author = get_user_by( 'login', $author_login );
		if ( get_user_option( 'friends_publish_post_collection', $author->ID ) && $author->has_cap( 'post_collection' ) ) {
			add_filter( 'pre_option_rss_use_excerpt', '__return_true', 30 );
			return true;
		}
		return $viewable;
	}

	/**
	 * Amend the Friends List widget
	 *
	 * @param object $widget  The widget.
	 * @param array  $args Sidebar arguments.
	 */
	public function friends_widget_friend_list_after( $widget, $args ) {
		$post_collections = $this->get_post_collection_users();
		if ( 0 !== $post_collections->get_total() ) {
			?>
			<details class="accordion" open>
				<summary class="accordion-header">
					<?php
					echo wp_kses_post( $args['before_title'] );
					echo esc_html( _ex( 'Post Collections', 'widget-header', 'friends' ) );
					echo wp_kses_post( $args['after_title'] );
					?>
				</summary>
				<ul class="subscriptions-list menu menu-nav accordion-body">
					<?php
					$widget->get_list_items( $post_collections->get_results() );
					?>
				</ul>
			</details>
			<?php
		}
	}

	/**
	 * Amend the Friends author header.
	 *
	 * @param      \WP_User $user   The user.
	 */
	public function friends_author_header( $user ) {
		if ( $user->has_cap( 'post_collection' ) ) {
			?>
			<a class="chip" href="<?php echo esc_attr( get_edit_user_link( $user->ID ) ); ?>"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Edit' ); ?></a>
			<?php
		}
	}

	public function share_button() {
		$this->template_loader()->get_template_part(
			'frontend/share',
			null,
			array(
				'post-collections' => $this->get_post_collection_users()->get_results(),
			)
		);
	}

	public function feed_table_header() {
		?>
		<th><?php esc_html_e( 'Fetch Full Content', 'friends' ); ?></th>
		<?php
	}

	public function feed_table_row( $feed, $term_id ) {
		?>
		<td style="padding-left: 1em"><input type="checkbox" name="feeds[<?php echo esc_attr( $term_id ); ?>][fetch-full-content]" value="1" aria-label="<?php esc_attr_e( 'Fetch Full Content', 'friends' ); ?>" <?php checked( $feed->get_metadata( 'fetch-full-content' ) ); ?> /></td>
		<?php
	}

	public function after_register_feed_taxonomy() {
		register_term_meta(
			User_Feed::TAXONOMY,
			'fetch-full-content',
			array(
				'type'   => 'boolean',
				'single' => true,
			)
		);
	}

	public function feed_item_submit( $user_feed, $feed ) {
		if ( isset( $feed['fetch-full-content'] ) ) {
			$user_feed->update_metadata( 'fetch-full-content', true );
		} else {
			$user_feed->delete_metadata( 'fetch-full-content' );
		}
	}

	public function modify_feed_item( $item, $user_feed, $friend_user, $post_id ) {
		if ( $user_feed && $user_feed->get_metadata( 'fetch-full-content' ) ) {
			$already_fetched = false;

			if ( $post_id ) {
				$already_fetched = get_post_meta( $post_id, 'full-content-fetched', true );
			}

			if ( ! $already_fetched ) {
				if ( isset( $this->fetched_for_feed[ $user_feed->get_id() ] ) ) {
					// Only fetch a single item per feed per call.
					return $item;
				}
				if ( $post_id ) {
					$this->fetched_for_feed[ $user_feed->get_id() ] = $post_id;
				} else {
					// This is a new post, we just want to record that we already downloaded something.
					$this->fetched_for_feed[ $user_feed->get_id() ] = true;
				}

				$fetched_item = $this->download( $item->permalink );
				if ( is_wp_error( $fetched_item ) ) {
					return $item;
				}

				if ( ! $fetched_item->content && ! $fetched_item->title ) {
					return $item;
				}

				$item->title   = strip_tags( trim( $fetched_item->title ) );
				$item->post_content = force_balance_tags( trim( wp_kses_post( $fetched_item->content ) ) );
				$item->_full_content_fetched = true;
				if ( $post_id ) {
					// The post meta needs to be set so that even if we cannot update the article with something meaningful, we won't try it over and over.
					update_post_meta( $post_id, 'full-content-fetched', true );
				}
			}
		}
		return $item;
	}

	public function can_update_modified_feed_posts( $can_update, $item, $user_feed, $friend_user, $post_id ) {
		if ( $user_feed->get_metadata( 'fetch-full-content' ) ) {
			if ( ! $post_id || ( isset( $this->fetched_for_feed[ $user_feed->get_id() ] ) && $post_id === $this->fetched_for_feed[ $user_feed->get_id() ] ) ) {
				return true;
			}

			// Prevent updates to items after they were already fetched.
			$already_fetched = get_post_meta( $post_id, 'full-content-fetched', true );
			return ! $already_fetched;
		}
		return $can_update;
	}

	function wp_ajax_mark_private() {
		if ( ! current_user_can( Friends::REQUIRED_ROLE ) ) {
			wp_send_json_error( 'error' );
		}

		$post = get_post( $_POST['id'] );
		$post->post_status = 'private';
		wp_update_post( $post );

		wp_send_json_success(
			array(
				'new_text' => __( 'Make post public', 'friends' ),
			)
		);
	}

	function wp_ajax_mark_publish() {
		if ( ! current_user_can( Friends::REQUIRED_ROLE ) ) {
			wp_send_json_error( 'error' );
		}

		$post = get_post( $_POST['id'] );
		$post->post_status = 'publish';
		wp_update_post( $post );

		wp_send_json_success(
			array(
				'new_text' => __( 'Make post private', 'friends' ),
			)
		);
	}

	function wp_ajax_change_author() {
		if ( ! current_user_can( Friends::REQUIRED_ROLE ) ) {
			wp_send_json_error( 'error' );
		}

		$user = new User( $_POST['author'] );
		if ( is_wp_error( $user ) ) {
			wp_send_json_error( 'error' );
		}
		if ( ! User::is_friends_plugin_user( $user ) && ! $user->has_cap( 'post_collection' ) ) {
			wp_send_json_error( 'error' );
		}

		$post = get_post( $_POST['id'] );
		$old_author = $post->post_author;
		$post->post_author = $user->ID;
		wp_update_post( $post );

		$first = new User( $_POST['first'] );
		$move_to = sprintf(
			// translators: %s is the name of a post collection.
			_x( 'Move to %s', 'post-collection', 'friends' ),
			$first->display_name
		);

		wp_send_json_success(
			array(
				'new_text'   => intval( $old_author ) !== $first->ID ? __( 'Undo' ) : $move_to, // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				'old_author' => $old_author,
			)
		);
	}

	function wp_ajax_fetch_full_content() {
		if ( ! current_user_can( Friends::REQUIRED_ROLE ) ) {
			wp_send_json_error( __( 'Sorry, you are not allowed to do that' ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			exit;
		}

		$user = new User( $_POST['author'] );
		if ( ! $user || is_wp_error( $user ) ) {
			wp_send_json_error( __( 'That user does not exist.' ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			exit;
		}
		if ( ! User::is_friends_plugin_user( $user ) ) {
			wp_send_json_error( "User doesn't belong to the Friends plugin." );
			exit;
		}

		$post = get_post( $_POST['id'] );
		$url = get_permalink( $post );
		$item = $this->download( $url );
		if ( is_wp_error( $item ) ) {
			wp_send_json_error( $item );
			exit;
		}

		if ( ! $item->content && ! $item->title ) {
			wp_send_json_error( new \WP_Error( 'invalid-content', __( 'No content was extracted.', 'friends' ) ) );
			exit;
		}

		$title   = strip_tags( trim( $item->title ) );
		$content = force_balance_tags( trim( wp_kses_post( $item->content ) ) );

		$post_data = array(
			'ID'           => $post->ID,
			'post_title'   => $title,
			'post_content' => $content,
			'meta_input'   => array(
				'full-content-fetched' => true,
			),
		);

		wp_update_post( $post_data );

		wp_send_json_success(
			array(
				'post_title'   => $title,
				'post_content' => $content,
			)
		);
	}

	/**
	 * Actions to take upon plugin activation.
	 *
	 * @param      bool $network_wide  Whether the plugin has been activated network-wide.
	 */
	public static function activate_plugin( $network_wide = null ) {
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			if ( $network_wide ) {
				if ( ! is_super_admin() ) {
					return;
				}
				foreach ( get_sites() as $blog ) {
					switch_to_blog( $blog->blog_id );
					self::activate_for_blog();
					restore_current_blog();
				}
			} else {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}
				self::activate_for_blog();
			}
		} else {
			self::activate_for_blog();
		}
	}

	/**
	 * Actions to take upon plugin activation.
	 */
	public static function activate_for_blog() {
		$post_collection = get_role( 'post_collection' );
		if ( ! $post_collection ) {
			$post_collection = add_role( 'post_collection', 'Post Collection' );
		}
		$post_collection->add_cap( 'post_collection' );
		$post_collection->add_cap( 'level_0' );
		$default_user_id = get_option( 'friends-post-collection_default_user' );
		$default_user = false;
		if ( $default_user_id ) {
			$default_user = new \WP_User( $default_user_id );
			if ( ! $default_user->exists() ) {
				$default_user = false;
			}
		}
		if ( ! $default_user ) {
			$userdata  = array(
				'user_login'   => 'saved-posts',
				'display_name' => 'Saved Posts',
				'user_pass'    => wp_generate_password( 256 ),
				'role'         => 'post_collection',
			);
			$user_id = wp_insert_user( $userdata );
			update_option( 'friends-post-collection_default_user', $user_id );
		}
	}
}
