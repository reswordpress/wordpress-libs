<?php

namespace Lipe\Lib\Post_Type;

class Custom_Post_Type {
	protected const REGISTRY_OPTION = 'lipe/lib/post-type/cpt_registry';
	protected const CUSTOM_CAPS_OPTION = 'lipe/lib/post-type/cpt_caps';

	protected static $registry = [];

	protected static $rewrite_checked = false;

	public $post_type_label_singular = '';

	public $post_type_label_plural = '';

	/**
	 * auto_admin_caps
	 *
	 * if true, will auto add custom capability type caps to administrator
	 * Defaults to true
	 *
	 * @var bool
	 */
	public $auto_admin_caps = true;

	/**
	 * Label used when retrieving the post type archive title
	 *
	 * @var string
	 */
	public $archive_label = '';

	/**
	 * Public properties
	 * These match the arguments used with register_post_type()
	 */
	public $description = 'A custom post type';

	public $hierarchical = false;

	public $capability_type = 'post';

	/**
	 * @link   https://codex.wordpress.org/Function_Reference/register_post_type#capabilities
	 *
	 * @notice if you set only some of these you probably want to
	 *         set $this->map_meta_cap = true
	 *
	 * @var array
	 */
	public $capabilities = [];

	/**
	 * 'title'
	 * 'editor' (content)
	 * 'author'
	 * 'thumbnail' (featured image, current theme must also support post-thumbnails)
	 * 'excerpt'
	 * 'trackbacks'
	 * 'custom-fields'
	 * 'comments' (also will see comment count balloon on edit screen)
	 * 'revisions' (will store revisions)
	 * 'page-attributes' (menu order, hierarchical must be true to show Parent option)
	 * 'post-formats' add post formats
	 *
	 * @var array
	 */
	public $supports = [ 'title', 'editor', 'author', 'thumbnail', 'excerpt' ];

	/**
	 * Provide a callback function that will be called when setting
	 * up the meta boxes for the edit form.
	 * The callback function takes one argument $post,
	 * which contains the WP_Post object for the currently edited post
	 *
	 * @default none
	 *
	 * @var callable
	 */
	public $register_meta_box_cb;

	public $map_meta_cap;

	public $menu_name;

	public $menu_icon;

	public $menu_position = 5;

	public $public = true;

	public $publicly_queryable;

	public $exclude_from_search;

	public $has_archive = true;

	public $slug = '';

	public $query_var = true;

	public $show_ui;

	public $show_in_menu;

	public $show_in_nav_menus;

	public $show_in_admin_bar;

	/**
	 * Whether to delete posts of this type when deleting a user.
	 * If true, posts of this type belonging to the user will be moved to trash
	 * when then user is deleted.
	 * If false, posts of this type belonging to the user will not be
	 * trashed or deleted.
	 * If not set (the default), posts are trashed if post_type_supports('author').
	 * Otherwise posts are not trashed or deleted.
	 *
	 * @var bool
	 */
	public $delete_with_user;

	/**
	 * Whether to expose this post type in the REST API
	 *
	 * @var bool
	 */
	public $show_in_rest = false;

	/**
	 * The base slug that this post type will use when accessed using the REST API.
	 *
	 * @default $this->post_type
	 *
	 * @var string
	 */
	public $rest_base;

	/**
	 * An optional custom controller to use instead of WP_REST_Posts_Controller.
	 * Must be a subclass of WP_REST_Controller.
	 *
	 * @default 'WP_REST_Posts_Controller'
	 *
	 * @var string
	 */
	public $rest_controller_class;

	public $rewrite;

	/**
	 * The default rewrite endpoint bitmasks
	 *
	 * @link    http://make.wordpress.org/plugins/2012/06/07/rewrite-endpoints-api/
	 *
	 * @default EP_PERMALINK
	 *
	 * @var int
	 */
	public $permalink_epmask = EP_PERMALINK;

	public $can_export = true;

	public $taxonomies = [];

	public $labels;

	/**
	 * The post type slug
	 *
	 * @var string
	 */
	protected $post_type = '';


	/**
	 * Takes care of the necessary hook and registering
	 *
	 * @notice set the class vars to edit arguments
	 *
	 * @param string $post_type
	 */
	public function __construct( $post_type ) {
		$this->post_type = $post_type;
		$this->hook();
	}


	/**
	 * Hook the post_type into WordPress
	 *
	 * @return void
	 */
	public function hook() : void {
		//allow methods added to the init hook to customize the post type
		add_action( 'wp_loaded', [ $this, 'register' ], 8, 0 );

		add_filter( 'adjust_post_updated_messages', [ $this, 'adjust_post_updated_messages' ], 10, 1 );
		add_filter( 'post_type_archive_title', [ $this, 'get_post_type_archive_label' ], 10, 1 );
		add_filter( 'bulk_post_updated_messages', [ $this, 'adjust_bulk_edit_messages' ], 10, 2 );

		if ( ! self::$rewrite_checked ) {
			add_action( 'wp_loaded', [ __CLASS__, 'check_rewrite_rules' ], 10000, 0 );
			self::$rewrite_checked = true;
		}
	}


	/**
	 * If the post types registered through this API have changed,
	 * rewrite rules need to be flushed.
	 *
	 * @static
	 *
	 * @return void
	 *
	 */
	public static function check_rewrite_rules() : void {
		$slugs = wp_list_pluck( self::$registry, 'slug' );
		if ( get_option( self::REGISTRY_OPTION ) !== $slugs ) {
			\flush_rewrite_rules();
			update_option( self::REGISTRY_OPTION, $slugs );
		}
	}


	/**
	 * Handles any calls which need to run to register this post type
	 *
	 * @since 1.6.0
	 *
	 *
	 * @return void
	 */
	public function register() : void {
		$this->register_post_type();
		self::$registry[ $this->post_type ] = $this;
		$this->add_administrator_capabilities( get_post_type_object( $this->post_type ) );
	}


	/**
	 * Register this post type with WordPress
	 *
	 * Allow using a different process for registering post types via
	 * child classes.
	 *
	 * @since 1.6.0
	 *
	 */
	protected function register_post_type() : void {
		\register_post_type( $this->post_type, $this->post_type_args() );
	}


	/**
	 * Build the args array for the post type definition
	 *
	 * @return array
	 */
	protected function post_type_args() : array {
		$args = [
			'labels'                => $this->post_type_labels(),
			'description'           => $this->description,
			'public'                => $this->public,
			'exclude_from_search'   => $this->exclude_from_search,
			'publicly_queryable'    => $this->publicly_queryable,
			'show_ui'               => $this->show_ui,
			'show_in_nav_menus'     => $this->show_in_nav_menus,
			'show_in_menu'          => $this->show_in_menu,
			'show_in_admin_bar'     => $this->show_in_admin_bar,
			'menu_position'         => $this->menu_position,
			'menu_icon'             => $this->menu_icon,
			'capability_type'       => $this->capability_type,
			'capabilities'          => $this->capabilities,
			'map_meta_cap'          => $this->map_meta_cap,
			'hierarchical'          => $this->hierarchical,
			'supports'              => $this->supports,
			'register_meta_box_cb'  => $this->register_meta_box_cb,
			'taxonomies'            => $this->taxonomies,
			'has_archive'           => $this->has_archive,
			'rewrite'               => $this->rewrites(),
			'permalink_epmask'      => $this->permalink_epmask,
			'query_var'             => $this->query_var,
			'can_export'            => $this->can_export,
			'delete_with_user'      => $this->delete_with_user,
			'show_in_rest'          => $this->show_in_rest,
			'rest_base'             => $this->rest_base,
			'rest_controller_class' => $this->rest_controller_class,

		];

		$args = apply_filters( 'lipe/lib/schema/post_type_args', $args, $this->post_type );
		$args = apply_filters( "lipe/lib/schema/post_type_args_{$this->post_type}", $args );

		return $args;
	}


	/**
	 * Post Type labels
	 *
	 * Build the labels array for the post type definition
	 *
	 * @param string $single
	 * @param string $plural
	 *
	 * @return array
	 */
	protected function post_type_labels( $single = null, $plural = null ) : array {
		$single = $single ?? $this->get_post_type_label();
		$plural = $plural ?? $this->get_post_type_label( 'plural' );

		//phpcs:ignore start
		$labels = [
			'name'                  => $plural,
			'singular_name'         => $single,
			'add_new'               => __( 'Add New' ),
			'add_new_item'          => sprintf( __( 'Add New %s' ), $single ),
			'edit_item'             => sprintf( __( 'Edit %s' ), $single ),
			'new_item'              => sprintf( __( 'New %s' ), $single ),
			'view_item'             => sprintf( __( 'View %s' ), $single ),
			'view_items'            => sprintf( __( 'View %s' ), $plural ),
			'search_items'          => sprintf( __( 'Search %s' ), $plural ),
			'not_found'             => sprintf( __( 'No %s Found' ), $plural ),
			'not_found_in_trash'    => sprintf( __( 'No %s Found in Trash' ), $plural ),
			'parent_item_colon'     => sprintf( __( 'Parent %s:' ), $single ),
			'all_items'             => sprintf( __( 'All %s' ), $plural ),
			'archives'              => sprintf( __( '%s Archives' ), $single ),
			'attributes'            => sprintf( __( '%s Attributes' ), $single ),
			'insert_into_item'      => sprintf( __( 'INSERT INTO %s' ), $single ),
			'uploaded_to_this_item' => sprintf( __( 'Uploaded to this %s' ), $single ),
			'featured_image'        => __( 'Featured Image' ),
			'set_featured_image'    => __( 'Set featured image' ),
			'remove_featured_image' => __( 'Remove featured image' ),
			'use_featured_image'    => __( 'Use as featured image' ),
			'filter_items_list'     => sprintf( __( 'Filter %s list' ), $plural ),
			'items_list_navigation' => sprintf( __( '%s list navigation' ), $plural ),
			'items_list'            => sprintf( __( '%s list' ), $plural ),
			'menu_name'             => $this->menu_name ?? $plural,
		];
		//phpcs:ignore end

		if ( ! empty( $this->labels ) ) {
			$labels = wp_parse_args( $this->labels, $labels );
		}

		$labels = apply_filters( 'lipe/lib/post-type/labels', $labels, $this->post_type );
		$labels = apply_filters( "lipe/lib/post-type/labels_{$this->post_type}", $labels );

		return $labels;
	}


	/**
	 * Get Post Type Label
	 *
	 * Get a post type label
	 *
	 * @param string $quantity - (singular,plural)
	 *
	 * @return string
	 */
	public function get_post_type_label( $quantity = 'singular' ) : string {
		if ( 'plural' === $quantity ) {
			if ( empty( $this->post_type_label_plural ) ) {
				$this->set_post_type_label( $this->post_type_label_singular );
			}

			return $this->post_type_label_plural;
		}
		if ( empty( $this->post_type_label_singular ) ) {
			$this->set_post_type_label( $this->post_type_label_singular, $this->post_type_label_plural );
		}

		return $this->post_type_label_singular;

	}


	/**
	 * Set Post Type Label
	 *
	 * Set the labels for the post type
	 *
	 * @param string $singular
	 * @param string $plural
	 *
	 * @return void
	 */
	public function set_post_type_label( $singular = '', $plural = '' ) : void {
		if ( ! $singular ) {
			$singular = str_replace( '_', ' ', $this->post_type );
			$singular = ucwords( $singular );
		}

		if ( ! $plural ) {
			$end = substr( $singular, - 1 );
			if ( 's' === $end ) {
				$plural = ucwords( $singular . 'es' );
			} elseif ( 'y' === $end ) {
				$plural = ucwords( rtrim( $singular, 'y' ) . 'ies' );
			} else {
				$plural = ucwords( $singular . 's' );
			}

		}
		$this->post_type_label_singular = $singular;
		$this->post_type_label_plural   = $plural;
	}


	/**
	 * Rewrites
	 *
	 * Build the rewrites param. Will send defaults if not set
	 *
	 * @return array|null
	 */
	protected function rewrites() : ?array {
		if ( empty( $this->rewrite ) ) {
			return [
				'slug'       => $this->get_slug(),
				'with_front' => false,
			];
		}

		return $this->rewrite;
	}


	/**
	 * Return the slug of this post type, formatted appropriately
	 *
	 * @return string
	 */
	public function get_slug() : string {
		if ( empty( $this->slug ) ) {
			$this->slug = strtolower( str_replace( ' ', '-', $this->post_type ) );
		}

		return $this->slug;
	}


	/**
	 * Add Administrator Capabilities
	 *
	 * If the capability_type is not post it has custom capabilities
	 * We need to add these to the administrators of the site
	 *
	 * This gets called during $this->register_post_type()
	 *
	 * Checks to make sure we have not done this already
	 *
	 * @param \WP_Post_Type $post_type
	 *
	 * @return void
	 */
	protected function add_administrator_capabilities( $post_type ) : void {
		if ( ! $this->auto_admin_caps || $post_type->capability_type === 'post' || is_wp_error( $post_type ) ) {
			return;
		}

		$previous = \get_option( self::CUSTOM_CAPS_OPTION, [] );
		if ( isset( $previous[ $post_type->capability_type ] ) ) {
			return;
		}
		$admin = \get_role( 'administrator' );
		if ( null === $admin ) {
			return;
		}

		foreach ( (array) $post_type->cap as $cap ) {
			$admin->add_cap( $cap );
		}

		$previous[ $post_type->capability_type ] = 1;
		update_option( self::CUSTOM_CAPS_OPTION, $previous );

	}


	/**
	 * Get a registered post type object
	 *
	 * @param string $post_type
	 *
	 * @since 1.6.0
	 *
	 * @return Custom_Post_Type|Custom_Post_Type_Extended|null
	 */
	public function get_post_type( $post_type ) {
		if ( isset( self::$registry[ $post_type ] ) ) {
			return self::$registry[ $post_type ];
		}

		return null;
	}


	/**
	 * Filters the bulk edit message to match the custom post type
	 *
	 * @filter bulk_post_updated_messages 10 2
	 *
	 * @param array $bulk_messages
	 * @param array $bulk_counts
	 *
	 * @since  1.6.0
	 *
	 * @return array
	 */
	public function adjust_bulk_edit_messages( array $bulk_messages, array $bulk_counts ) : array {
		$bulk_messages[ $this->post_type ] = [
			'updated'   => _n(
				'%s ' . $this->post_type_label_singular . ' updated.',
				'%s ' . $this->post_type_label_plural . ' updated.',
				$bulk_counts['updated']
			),
			'locked'    => _n(
				'%s ' . $this->post_type_label_singular . ' not updated, somebody is editing it.',
				'%s ' . $this->post_type_label_plural . ' not updated, somebody is editing them.',
				$bulk_counts['locked']
			),
			'deleted'   => _n(
				'%s ' . $this->post_type_label_singular . ' permanently deleted.',
				'%s ' . $this->post_type_label_plural . ' permanently deleted.',
				$bulk_counts['deleted']
			),
			'trashed'   => _n(
				'%s ' . $this->post_type_label_singular . ' moved to the Trash.',
				'%s ' . $this->post_type_label_plural . ' moved to the Trash.',
				$bulk_counts['trashed']
			),
			'untrashed' => _n(
				'%s ' . $this->post_type_label_singular . ' restored from the Trash.',
				'%s ' . $this->post_type_label_plural . ' restored from the Trash.',
				$bulk_counts['untrashed']
			),
		];

		return $bulk_messages;
	}


	/**
	 * Filter the post updated messages so they match this post type
	 * Smart enough to handle public and none public types
	 *
	 * @filter post_updated_messages 10 1
	 *
	 * @param array $messages
	 *
	 * @since  1.6.0
	 *
	 * @return array
	 *
	 */
	public function adjust_post_updated_messages( array $messages = [] ) : array {
		global $post, $post_ID;

		$lower_label = strtolower( $this->get_post_type_label() );

		if ( $this->public === false || $this->publicly_queryable === false ) {
			$view_link = $preview_link = false;
		} else {
			$url          = esc_url( get_permalink( $post_ID ) );
			$preview_url  = add_query_arg( 'preview', 'true', $url );
			$view_link    = '<a href="' . $url . '">' . sprintf( __( 'View the %s...' ), $this->get_post_type_label(), $lower_label ) . '</a>';
			$preview_link = '<a target="_blank" href="' . $preview_url . '">' . sprintf( 'Preview %s', $lower_label ) . '</a>';

		}

		$messages[ $this->post_type ] = [
			0  => null,
			1  => sprintf( __( '%s updated. %s' ), $this->get_post_type_label(), $view_link ),
			2  => __( 'Custom field updated.' ),
			3  => __( 'Custom field deleted.' ),
			4  => sprintf( __( '%s updated.' ), $this->get_post_type_label() ),
			5  => isset( $_GET['revision'] ) ? sprintf( __( '%s restored to revision from %s' ),
				$this->get_post_type_label(), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6  => sprintf( __( '%s published. %s' ), $this->get_post_type_label(),
				$view_link ),
			7  => sprintf( __( '%s saved.' ), $this->get_post_type_label() ),
			8  => sprintf( __( '%s submitted. %s' ), $this->get_post_type_label(), $preview_link ),
			9  => sprintf( __( '%3$s scheduled for: %1$s. %2$s' ),
				'<strong>' . date_i18n( __( 'M j, Y @ G:i' ) . '</strong>', strtotime( $post->post_date ) ), $preview_link, $this->get_post_type_label() ),
			10 => sprintf( __( '%s draft updated. %s' ), $this->get_post_type_label(), $preview_link ),

		];

		return $messages;

	}


	/**
	 * Set capabilities for the post type using the methods
	 * of the Capabilities class
	 *
	 * @return Capabilities
	 *
	 */
	public function capabilities() : Capabilities {
		return new Capabilities( $this );
	}


	/**
	 * Get Post Type Archive Label
	 *
	 * Used when retrieving the post type archive title
	 * Makes it match any customization done here
	 *
	 * Automatically added to the get_post_type_archive_label filter
	 *
	 * @param $title
	 *
	 * @return string
	 */
	public function get_post_type_archive_label( $title ) : string {
		if ( is_post_type_archive( $this->post_type ) ) {
			if ( $this->archive_label ) {
				$title = $this->archive_label;
			} else {
				$title = $this->get_post_type_label( 'plural' );
			}
		}

		return $title;
	}


	/**
	 * Adds post type support.
	 * Send a single feature or array of features
	 *
	 * Must be called before the post type is registered
	 *
	 * @param array|string $features
	 *
	 * @return void
	 */
	public function add_support( $features ) : void {
		$features       = (array) $features;
		$this->supports = array_unique( array_merge( $this->supports, $features ) );

	}


	/**
	 * Removes post type support.
	 * Send a single feature or array of features
	 *
	 * Must be called before the post type is registered
	 *
	 * @param array|string $features
	 *
	 * @return void
	 */
	public function remove_support( $features ) : void {
		$features       = (array) $features;
		$this->supports = array_diff( $this->supports, $features );
	}


	/**
	 * @param string $post_type
	 *
	 * @static
	 *
	 * @return Custom_Post_Type|Custom_Post_Type_Extended
	 */
	public static function factory( string $post_type ) : Custom_Post_Type {
		return new static( $post_type );
	}
}
