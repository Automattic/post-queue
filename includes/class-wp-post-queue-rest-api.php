<?php

namespace Post_Queue;

/**
 * This class is responsible for the REST API side of the plugin.
 * It registers the REST routes and handles the requests to the API.
 */
class REST_API {
	/**
	 * The settings for the plugin.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * The valid times for the queue.
	 *
	 * @var array
	 */
	private $valid_times = array(
		'1 am',
		'2 am',
		'3 am',
		'4 am',
		'5 am',
		'6 am',
		'7 am',
		'8 am',
		'9 am',
		'10 am',
		'11 am',
		'12 pm',
		'1 pm',
		'2 pm',
		'3 pm',
		'4 pm',
		'5 pm',
		'6 pm',
		'7 pm',
		'8 pm',
		'9 pm',
		'10 pm',
		'11 pm',
		'12 am',
	);

	/**
	 * Constructor for the REST_API class.
	 *
	 * @param array $settings The settings for the plugin.
	 *
	 * @return void
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Register all of the REST routes.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route(
			'post-queue/v1',
			'/settings',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_settings' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			'post-queue/v1',
			'/settings',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_settings' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'args'                => array(
					'publish_times'   => array(
						'required'          => false,
						'type'              => 'integer',
						'validate_callback' => function ( $param, $request, $key ) {
							return is_numeric( $param ) && $param >= 0 && $param <= 50;
						},
					),
					'start_time'      => array(
						'required'          => false,
						'type'              => 'string',
						'validate_callback' => function ( $param, $request, $key ) {
							return is_string( $param ) && in_array( $param, $this->valid_times, true );
						},
					),
					'end_time'        => array(
						'required'          => false,
						'type'              => 'string',
						'validate_callback' => function ( $param, $request, $key ) {
							return is_string( $param ) && in_array( $param, $this->valid_times, true );
						},
					),
					'post_queue_paused' => array(
						'required'          => false,
						'type'              => 'boolean',
						'validate_callback' => function ( $param, $request, $key ) {
							return is_bool( $param );
						},
					),
				),
			)
		);

		register_rest_route(
			'post-queue/v1',
			'/recalculate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'recalculate_publish_times_rest_callback' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'order' => array(
						'required'          => true,
						'type'              => 'array',
						'validate_callback' => function ( $param, $request, $key ) {
							if ( ! is_array( $param ) ) {
								return false;
							}
							foreach ( $param as $id ) {
								if ( ! is_numeric( $id ) ) {
									return false;
								}
							}
							return true;
						},
					),
				),
			)
		);

		register_rest_route(
			'post-queue/v1',
			'/shuffle',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'shuffle_queue' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_rest_route(
			'post-queue/v1',
			'/next-queue-time',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_next_queue_time' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	/**
	 * Get the settings for the queue.
	 *
	 * @return array The settings for the queue.
	 */
	public function get_settings() {
		return $this->settings;
	}

	/**
	 * Update the settings for the queue.
	 *
	 * Endpoint: /post-queue/v1/settings
	 * Method: POST
	 * Params:
	 * - publish_times: int
	 * - start_time: string
	 * - end_time: string
	 * - post_queue_paused: bool
	 *
	 * If the queue is paused, it will be resumed and vice versa.
	 * When settings are updated, the queue is recalculated and the publish times are updated.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return array The updated settings.
	 */
	public function update_settings( \WP_REST_Request $request ) {
		$publish_times = $request->get_param( 'publish_times' );
		$start_time    = $request->get_param( 'start_time' );
		$end_time      = $request->get_param( 'end_time' );
		$queue_paused  = $request->get_param( 'post_queue_paused' );

		$current_settings = $this->settings;

		if ( null !== $publish_times ) {
			update_option( 'post_queue_publish_times', $publish_times );
		}
		if ( null !== $start_time ) {
			update_option( 'post_queue_start_time', $start_time );
		}
		if ( null !== $end_time ) {
			update_option( 'post_queue_end_time', $end_time );
		}
		if ( null !== $queue_paused ) {
			update_option( 'post_queue_paused', $queue_paused );
		}

		$this->settings = array(
			'publishTimes'    => get_option( 'post_queue_publish_times' ),
			'startTime'       => get_option( 'post_queue_start_time' ),
			'endTime'         => get_option( 'post_queue_end_time' ),
			'postQueuePaused' => get_option( 'post_queue_paused' ),
		);

		$queue_manager = new Manager( $this->settings );

		// Only execute pause/resume if the setting has changed
		if ( null !== $queue_paused && $queue_paused !== $current_settings['postQueuePaused'] ) {
			if ( $queue_paused ) {
				$queue_manager->pause_queue();
			} else {
				$queue_manager->resume_queue();
			}
		}

		// Recalculate publish times regardless of pause setting change
		$current_queue = $queue_manager->get_current_order();
		$updated_order = $queue_manager->recalculate_publish_times( array_column( $current_queue, 'ID' ) );

		return new \WP_REST_Response( $updated_order, 200 );
	}

	/**
	 * Recalculate the publish times for all posts in the queue.
	 *
	 * Endpoint: /post-queue/v1/recalculate
	 * Method: POST
	 * Params:
	 * - order: array of post IDs
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return array The updated publish times.
	 */
	public function recalculate_publish_times_rest_callback( \WP_REST_Request $request ) {
		$new_order = $request->get_param( 'order' );

		$queue_manager = new Manager( $this->settings );
		$updated_order = $queue_manager->recalculate_publish_times( $new_order );

		return new \WP_REST_Response( $updated_order, 200 );
	}

	/**
	 * Shuffle the queue of posts.
	 *
	 * Endpoint: /post-queue/v1/shuffle
	 * Method: POST
	 *
	 * @return array The new order of the posts.
	 */
	public function shuffle_queue() {
		$manager   = new Manager( $this->settings );
		$new_order = $manager->shuffle_queued_posts();

		return new \WP_REST_Response( $new_order, 200 );
	}

	/**
	 * Get the next estimated queue time.
	 *
	 * @return \WP_REST_Response The next queue time.
	 */
	public function get_next_queue_time() {
		$queue_manager   = new Manager( $this->settings );
		$next_queue_time = $queue_manager->get_next_queue_time();

		return new \WP_REST_Response( array( 'nextQueueTime' => $next_queue_time ), 200 );
	}
}
