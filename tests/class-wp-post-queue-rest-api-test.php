<?php

use WP_Post_Queue\REST_API;

/**
 * Test the REST_API class.
 * Which is responsible for the REST API side of the plugin.
 */
class Test_WP_Post_Queue_REST_API extends WP_UnitTestCase {
	private $rest_api;
	private $settings;

	/**
	 * Sets up the tests.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->settings = array(
			'publishTimes'  => 2,
			'startTime'     => '12 am',
			'endTime'       => '1 am',
			'wpQueuePaused' => false,
		);
		$this->rest_api = new REST_API( $this->settings );

		// Set up an authenticated user
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
	}
	/**
	 * Test that the get_settings method returns the correct settings from the database.
	 *
	 * @return void
	 */
	public function test_get_settings() {
		$fetched_response = rest_do_request( new WP_REST_Request( 'GET', '/wp-post-queue/v1/settings' ) );
		$settings         = $fetched_response->get_data();

		$this->assertIsArray( $settings );
		$this->assertArrayHasKey( 'publishTimes', $settings );
		$this->assertArrayHasKey( 'startTime', $settings );
		$this->assertArrayHasKey( 'endTime', $settings );
		$this->assertArrayHasKey( 'wpQueuePaused', $settings );
	}

	/**
	 * Test that the update_settings method updates the settings in the database correctly.
	 *
	 * @return void
	 */
	public function test_update_settings() {
		$request = new WP_REST_Request( 'POST', '/wp-post-queue/v1/settings' );
		$request->set_param( 'publish_times', 3 );
		$request->set_param( 'start_time', '1 am' );
		$request->set_param( 'end_time', '2 am' );
		$request->set_param( 'wp_queue_paused', true );

		$response = rest_do_request( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$updated_settings = $this->rest_api->get_settings();
		$fetched_response = rest_do_request( new WP_REST_Request( 'GET', '/wp-post-queue/v1/settings' ) );
		$fetched_settings = $fetched_response->get_data();

		$this->assertEquals( 3, $fetched_settings['publishTimes'] );
		$this->assertEquals( '1 am', $fetched_settings['startTime'] );
		$this->assertEquals( '2 am', $fetched_settings['endTime'] );
		$this->assertTrue( $fetched_settings['wpQueuePaused'] );
	}

	/**
	 * Data provider for invalid settings.
	 *
	 * @return array
	 */
	public function invalidSettingsProvider() {
		return array(
			'invalid publish_times'           => array( 'publish_times', -1 ),
			'invalid publish_times, past max' => array( 'publish_times', 51 ),
			'invalid start_time'              => array( 'start_time', '25 am' ),
			'invalid end_time'                => array( 'end_time', '13 pm' ),
			'invalid end_time, random string' => array( 'end_time', 'random string' ),
			'invalid wp_queue_paused'         => array( 'wp_queue_paused', 'not_a_boolean' ),
		);
	}

	/**
	 * Test that the update_settings method returns an error for invalid parameters.
	 *
	 * @dataProvider invalidSettingsProvider
	 *
	 * @param string $param The parameter name.
	 * @param mixed  $value The invalid value.
	 *
	 * @return void
	 */
	public function test_update_settings_invalid_parameters( $param, $value ) {
		$request = new WP_REST_Request( 'POST', '/wp-post-queue/v1/settings' );
		$request->set_param( $param, $value );

		$response = rest_do_request( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * Test that the recalculate_publish_times_rest_callback method recalculates the publish times for all posts in the queue correctly.
	 *
	 * @return void
	 */
	public function test_recalculate_publish_times_rest_callback() {
		$post_ids = array(
			$this->factory->post->create( array( 'post_status' => 'queued' ) ),
			$this->factory->post->create( array( 'post_status' => 'queued' ) ),
			$this->factory->post->create( array( 'post_status' => 'queued' ) ),
		);

		$request = new WP_REST_Request( 'POST', '/wp-post-queue/v1/recalculate' );
		$request->set_param( 'order', array_reverse( $post_ids ) );

		$response = rest_do_request( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertCount( 3, $data );
		$this->assertEquals( $post_ids[2], $data[0]['ID'] );
	}

	/**
	 * Test that the recalculate_publish_times_rest_callback method returns an error for invalid order.
	 *
	 * @return void
	 */
	public function test_recalculate_publish_times_invalid_order() {
		$request = new WP_REST_Request( 'POST', '/wp-post-queue/v1/recalculate' );
		$request->set_param( 'order', 'not_an_array' ); // Invalid value

		$response = rest_do_request( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * Test that the endpoint returns a 200 status code and data when called correctly
	 * Note that we check the actual sorting logic in the Manager class, instead of trying to test it twice.
	 *
	 * @return void
	 */
	public function test_shuffle_queue() {
		$post_ids = array(
			$this->factory->post->create( array( 'post_status' => 'queued' ) ),
			$this->factory->post->create( array( 'post_status' => 'queued' ) ),
			$this->factory->post->create( array( 'post_status' => 'queued' ) ),
		);

		$request = new WP_REST_Request( 'POST', '/wp-post-queue/v1/shuffle' );

		$response = rest_do_request( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertCount( 3, $data );
	}


	/**
	 * Test that unauthenticated requests return a 401 Unauthorized status.
	 *
	 * @return void
	 */
	public function test_unauthenticated_requests() {
		// Clear the current user to simulate an unauthenticated request
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'POST', '/wp-post-queue/v1/settings' );
		$response = rest_do_request( $request );

		$this->assertErrorResponse( 'rest_forbidden', $response, 401 );

		$post_ids = array(
			$this->factory->post->create( array( 'post_status' => 'queued' ) ),
			$this->factory->post->create( array( 'post_status' => 'queued' ) ),
			$this->factory->post->create( array( 'post_status' => 'queued' ) ),
		);

		$request = new WP_REST_Request( 'POST', '/wp-post-queue/v1/recalculate' );
		$request->set_param( 'order', array_reverse( $post_ids ) );

		$response = rest_do_request( $request );

		$this->assertErrorResponse( 'rest_forbidden', $response, 401 );

		$request  = new WP_REST_Request( 'POST', '/wp-post-queue/v1/shuffle' );
		$response = rest_do_request( $request );

		$this->assertErrorResponse( 'rest_forbidden', $response, 401 );
	}

	/**
	 * Asserts that a response is an error response.
	 *
	 * @param string           $code     The error code.
	 * @param WP_REST_Response $response The response.
	 * @param integer|null     $status   The status code.
	 *
	 * @return void
	 */
	protected function assertErrorResponse( $code, $response, $status = null ) {
		if ( is_a( $response, 'WP_REST_Response' ) ) {
			$response = $response->as_error();
		}

		$this->assertInstanceOf( 'WP_Error', $response );
		$this->assertEquals( $code, $response->get_error_code() );

		if ( null !== $status ) {
			$data = $response->get_error_data();
			$this->assertArrayHasKey( 'status', $data );
			$this->assertEquals( $status, $data['status'] );
		}
	}
}
