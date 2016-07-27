<?php
// Don't load directly
defined( 'WPINC' ) or die;

class Tribe__Events__Aggregator__Record {
	/**
	 * Slug of the Post Type used for Event Aggregator Records
	 *
	 * @var string
	 */
	public static $post_type = 'tribe-ea-record';

	/**
	 * Base slugs for all the EA Record Post Statuses
	 *
	 * @var stdClass
	 */
	public static $status = array(
		'success'   => 'tribe-ea-success',
		'failed'    => 'tribe-ea-failed',
		'scheduled' => 'tribe-ea-scheduled',
	);


	public static $key = array(
		'source' => '_tribe_ea_source',
		'origin' => '_tribe_ea_origin',
	);

	/**
	 * Static Singleton Holder
	 *
	 * @var self
	 */
	private static $instance;

	/**
	 * Static Singleton Factory Method
	 *
	 * @return self
	 */
	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Setup all the hooks and filters
	 *
	 * @return void
	 */
	private function __construct() {
		// Make it an object for easier usage
		if ( ! is_object( self::$status ) ) {
			self::$status = (object) self::$status;
		}
		// Make it an object for easier usage
		if ( ! is_object( self::$key ) ) {
			self::$key = (object) self::$key;
		}

		// Register the Custom Post Type
		add_action( 'init', array( $this, 'get_post_type' ) );

		// Register the Custom Post Statuses
		add_action( 'init', array( $this, 'get_status' ) );

		// Setup the magical methods to fetch important meta
		add_filter( 'get_post_metadata', array( $this, 'filter_get_post_meta' ), 10, 4 );

		// Will allow us to prevent incomplete posts to be inserted
		add_filter( 'wp_insert_post_empty_content', array( $this, 'filter_maybe_empty_content' ), 10, 2 );

		// Run the Import when Hitting the Event Aggregator Endpoint
		add_action( 'tribe_ea_endpoint_insert', array( $this, 'action_do_import' ) );
	}

	/**
	 * Register and return the Aggregator Record Custom Post Status
	 * Instead of having a method for returning and another registering
	 * we do it all in one single method depending if it exists or not
	 *
	 * @param  string $status Which status object you are looking for
	 *
	 * @return stdClass|WP_Error|array
	 */
	public function get_status( $status = null ) {
		$registered = (object) array(
			'success'   => get_post_status_object( self::$status->success ),
			'failed'    => get_post_status_object( self::$status->failed ),
			'scheduled' => get_post_status_object( self::$status->scheduled ),
		);

		// Check if we already have the Status registered
		if ( isset( $registered->{ $status } ) && is_object( $registered->{ $status } ) ) {
			return $registered->{ $status };
		}

		// Register the Success post status
		$args = array(
			'label'              => esc_html_x( 'Imported', 'event aggregator status', 'the-events-calendar' ),
			'label_count'        => _nx_noop( 'Imported <span class="count">(%s)</span>', 'Imported <span class="count">(%s)</span>', 'event aggregator status', 'the-events-calendar' ),
			'public'             => true,
			'publicly_queryable' => true,
		);
		$registered->success = register_post_status( self::$status->success, $args );

		// Register the Failed post status
		$args = array(
			'label'              => esc_html_x( 'Failed', 'event aggregator status', 'the-events-calendar' ),
			'label_count'        => _nx_noop( 'Failed <span class="count">(%s)</span>', 'Failed <span class="count">(%s)</span>', 'event aggregator status', 'the-events-calendar' ),
			'public'             => true,
			'publicly_queryable' => true,
		);
		$registered->failed = register_post_status( self::$status->failed, $args );

		// Register the Scheduled post status
		$args = array(
			'label'              => esc_html_x( 'Scheduled', 'event aggregator status', 'the-events-calendar' ),
			'label_count'        => _nx_noop( 'Scheduled <span class="count">(%s)</span>', 'Scheduled <span class="count">(%s)</span>', 'event aggregator status', 'the-events-calendar' ),
			'public'             => true,
			'publicly_queryable' => true,
		);
		$registered->scheduled = register_post_status( self::$status->scheduled, $args );

		// Re-check if we have the status registered
		if ( isset( $registered->{ $status } ) && is_object( $registered->{ $status } ) ) {
			return $registered->{ $status };
		}

		return $registered;
	}

	/**
	 * Register and return the Aggregator Record Custom Post Type
	 * Instead of having a method for returning and another registering
	 * we do it all in one single method depending if it exists or not
	 *
	 * @return stdClass|WP_Error
	 */
	public function get_post_type() {
		if ( post_type_exists( self::$post_type ) ){
			return get_post_type_object( self::$post_type );
		}

		$args = array(
			'description'        => esc_html__( 'Events Aggregator Record', 'the-events-calendar' ),
			'public'             => true,
			'publicly_queryable' => false,
			'show_ui'            => false,
			'show_in_menu'       => false,
			'query_var'          => false,
			'rewrite'            => false,
			'capability_type'    => array( 'ea-record', 'ea-records' ),
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => array()
		);

		$args['labels'] = array(
			'name'               => esc_html_x( 'Aggregator Records', 'post type general name', 'the-events-calendar' ),
			'singular_name'      => esc_html_x( 'Aggregator Record', 'post type singular name', 'the-events-calendar' ),
			'menu_name'          => esc_html_x( 'Aggregator Records', 'admin menu', 'the-events-calendar' ),
			'name_admin_bar'     => esc_html_x( 'Aggregator Record', 'add new on admin bar', 'the-events-calendar' ),
			'add_new'            => esc_html_x( 'Add New', 'record', 'the-events-calendar' ),
			'add_new_item'       => esc_html__( 'Add New Aggregator Record', 'the-events-calendar' ),
			'new_item'           => esc_html__( 'New Aggregator Record', 'the-events-calendar' ),
			'edit_item'          => esc_html__( 'Edit Aggregator Record', 'the-events-calendar' ),
			'view_item'          => esc_html__( 'View Aggregator Record', 'the-events-calendar' ),
			'all_items'          => esc_html__( 'All Aggregator Records', 'the-events-calendar' ),
			'search_items'       => esc_html__( 'Search Aggregator Records', 'the-events-calendar' ),
			'parent_item_colon'  => esc_html__( 'Parent Aggregator Record:', 'the-events-calendar' ),
			'not_found'          => esc_html__( 'No Aggregator Records found.', 'the-events-calendar' ),
			'not_found_in_trash' => esc_html__( 'No Aggregator Records found in Trash.', 'the-events-calendar' )
		);

		return register_post_type( self::$post_type, $args );
	}

	public function create( $origin = false, $type = 'import', $args = array() ) {
		$defaults = array(
			'frequency' => null,
		);
		$args = wp_parse_args( $args, $defaults );

		$post = array(
			// Stores the Key under `post_title` which is a very forgiving type of column on `wp_post`
			'post_title' => wp_generate_password( 32, true, true ),
			'post_date'  => current_time( 'mysql' ),

			'meta_input' => array(

			),
		);

		if ( 'schedule' === $type ) {
			$frequency = Tribe__Events__Aggregator__Cron::instance()->get_frequency( 'id=' . $args->frequency );
			if ( ! $frequency ) {
				return new WP_Error( 'invalid-frequency', __( 'An Invalid frequency was used to try to setup a scheduled import', 'the-events-calendar' ), $args );
			}

			// Setups the post_content as the Frequency (makes it easy to fetch by frequency)
			$post['post_content'] = $frequency->id;

			// When the next scheduled import should happen
			// @todo
			// $post['post_content_filtered'] =
		}

		// wp_insert_post(  );
	}

	public function action_do_import() {
		return wp_send_json_success();
	}

	public function filter_maybe_empty_content( $maybe_empty = false, $postarr = array() ) {

	}

	public function action_check_scheduled_imports( $post_ID, $post, $update ) {
		var_dump( $post, $post_ID );
		exit;

		// If we are not in the correct Post Type we bail
		if ( $post->post_type !== self::$post_type ) {
			return;
		}

		// If we are not dealing with a Schedule we don't care about the cron
		if ( $post->post_status !== self::$status->schedule ) {
			return;
		}

	}

	public function filter_get_post_meta( $value, $id, $key, $single ) {
		$post = get_post( $id );

		if ( $post->post_type !== self::$post_type ) {
			return $value;
		}

		if ( 'record' !== $key ) {
			return $value;
		}

		return $this;
	}
}