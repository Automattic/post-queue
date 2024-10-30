<?php

use WP_Post_Queue\Manager;

/**
 * Test the Manager class.
 * Which is responsible for the queueing and publishing logic.
 */
class Test_WP_Post_Queue_Manager extends WP_UnitTestCase {
	private $manager;
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

		$this->manager = $this->getMockBuilder( Manager::class )
			->setConstructorArgs( array( $this->settings ) )
			->onlyMethods( array( 'get_current_time', 'get_current_date' ) )
			->getMock();

		// Mock the get_current_date method to return a DateTime object
		$current_date = new \DateTime( 'now', new \DateTimeZone( 'UTC' ) );
		$this->manager->method( 'get_current_date' )
			->willReturn( $current_date );
	}

	/**
	 * Test that handle_post_status_change queues a post when transitioning to queued status.
	 *
	 * @return void
	 */
	public function test_handle_post_status_change_queues_post() {
		$post_id = $this->factory->post->create( array( 'post_status' => 'draft' ) );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'queued',
			)
		);

		do_action( 'transition_post_status', 'queued', 'draft', get_post( $post_id ) );

		$next_scheduled = wp_next_scheduled( 'publish_queued_post', array( $post_id ) );
		$this->assertNotFalse( $next_scheduled, 'The post should be scheduled for publishing.' );
	}

	/**
	 * Data provider for test_calculate_next_publish_time.
	 * We want to test a variety of scenarios to ensure the publish times are calculated correctly.
	 *
	 * @return array
	 */
	public function publishTimeProvider() {
		return array(
			'single post scheduled for next day' => array(
				'settings'       => array(
					'publishTimes' => 1,
					'startTime'    => '12:00 AM',
					'endTime'      => '1:00 AM',
				),
				'expected_times' => array(
					strtotime( 'tomorrow 00:30:00' ),
				),
				'queued_posts'   => array( 1 ),
			),
			'two posts evenly distributed'       => array(
				'settings'       => array(
					'publishTimes' => 2,
					'startTime'    => '12:00 AM',
					'endTime'      => '1:00 AM',
				),
				'expected_times' => array(
					strtotime( 'tomorrow 00:20:00' ),
					strtotime( 'tomorrow 00:40:00' ),
				),
				'queued_posts'   => array( 1, 2 ),
			),
			'three posts evenly distributed'     => array(
				'settings'       => array(
					'publishTimes' => 3,
					'startTime'    => '12:00 AM',
					'endTime'      => '1:00 AM',
				),
				'expected_times' => array(
					strtotime( 'tomorrow 00:15:00' ),
					strtotime( 'tomorrow 00:30:00' ),
					strtotime( 'tomorrow 00:45:00' ),
				),
				'queued_posts'   => array( 1, 2, 3 ),
			),
			'four posts evenly distributed'      => array(
				'settings'       => array(
					'publishTimes' => 4,
					'startTime'    => '12:00 AM',
					'endTime'      => '1:00 AM',
				),
				'expected_times' => array(
					strtotime( 'tomorrow 00:12:00' ),
					strtotime( 'tomorrow 00:24:00' ),
					strtotime( 'tomorrow 00:36:00' ),
					strtotime( 'tomorrow 00:48:00' ),
				),
				'queued_posts'   => array( 1, 2, 3, 4 ),
			),
			'wrapping to next day'               => array(
				'settings'       => array(
					'publishTimes' => 4,
					'startTime'    => '9:00 AM',
					'endTime'      => '5:00 PM',
				),
				'expected_times' => array(
					strtotime( 'tomorrow 10:36:00' ),
					strtotime( 'tomorrow 12:12:00' ),
					strtotime( 'tomorrow 13:48:00' ),
					strtotime( 'tomorrow 15:24:00' ),
				),
				'queued_posts'   => array( 1, 2, 3, 4 ),
			),
			'open slots available today'         => array(
				'settings'       => array(
					'publishTimes' => 3,
					'startTime'    => '12:00 PM',
					'endTime'      => '6:00 PM',
				),
				'expected_times' => array(
					strtotime( 'today 13:30:00' ),
					strtotime( 'today 15:00:00' ),
					strtotime( 'today 16:30:00' ),
				),
				'queued_posts'   => array( 1, 2, 3 ),
				'current_time'   => strtotime( 'today 01:00:00' ),
			),
			'queue post at start of day'         => array(
				'settings'       => array(
					'publishTimes' => 2,
					'startTime'    => '12:00 AM',
					'endTime'      => '1:00 AM',
				),
				'expected_times' => array(
					strtotime( 'today 00:20:00' ),
				),
				'queued_posts'   => array( 1 ),
				'current_time'   => strtotime( 'today 00:00:00' ),
			),
			'overflow to next day'               => array(
				'settings'       => array(
					'publishTimes' => 3,
					'startTime'    => '12:00 PM',
					'endTime'      => '6:00 PM',
				),
				'expected_times' => array(
					strtotime( 'today 13:30:00' ),
					strtotime( 'today 15:00:00' ),
					strtotime( 'today 16:30:00' ),
					strtotime( 'tomorrow 13:30:00' ),
					strtotime( 'tomorrow 15:00:00' ),
				),
				'queued_posts'   => array( 1, 2, 3, 4, 5 ),
				'current_time'   => strtotime( 'today 00:00:00' ),
			),
			'midday with open slots'             => array(
				'settings'       => array(
					'publishTimes' => 3,
					'startTime'    => '9:00 AM',
					'endTime'      => '5:00 PM',
				),
				'expected_times' => array(
					strtotime( 'today 13:00:00' ),
					strtotime( 'today 15:00:00' ),
					strtotime( 'tomorrow 11:00:00' ),
				),
				'queued_posts'   => array( 1, 2, 3 ),
				'current_time'   => strtotime( 'today 12:00:00' ),
			),
			'midday with no open slots'          => array(
				'settings'       => array(
					'publishTimes' => 3,
					'startTime'    => '9:00 AM',
					'endTime'      => '5:00 PM',
				),
				'expected_times' => array(
					strtotime( 'tomorrow 11:00:00' ),
					strtotime( 'tomorrow 13:00:00' ),
					strtotime( 'tomorrow 15:00:00' ),
				),
				'queued_posts'   => array( 1, 2, 3 ),
				'current_time'   => strtotime( 'today 18:00:00' ),
			),
			'late day wrapping to next day'      => array(
				'settings'       => array(
					'publishTimes' => 4,
					'startTime'    => '9:00 AM',
					'endTime'      => '5:00 PM',
				),
				'expected_times' => array(
					strtotime( 'tomorrow 10:36:00' ),
					strtotime( 'tomorrow 12:12:00' ),
					strtotime( 'tomorrow 13:48:00' ),
					strtotime( 'tomorrow 15:24:00' ),
				),
				'queued_posts'   => array( 1, 2, 3, 4 ),
				'current_time'   => strtotime( 'today 16:00:00' ),
			),
		);
	}

	/**
	 * Test that the calculate_next_publish_time method calculates the next publish time for a post in the queue
	 * and is the core logic for the queue and plugin.
	 *
	 * @dataProvider publishTimeProvider
	 *
	 * @param array        $settings       The settings for the manager.
	 * @param array        $expected_times The expected times for the posts to be published.
	 * @param array        $queued_posts   The IDs of the posts in the queue.
	 * @param integer|null $current_time   The current time.
	 *
	 * @return void
	 */
	public function test_calculate_next_publish_time( $settings, $expected_times, $queued_posts, $current_time = null ) {
		$this->manager = $this->getMockBuilder( Manager::class )
			->setConstructorArgs( array( $settings ) )
			->onlyMethods( array( 'get_current_time', 'get_current_date' ) )
			->getMock();

		$current_time = $current_time ?? strtotime( 'today 23:59:59' );
		$this->manager->method( 'get_current_time' )
			->willReturn( $current_time );

		$current_date = new \DateTime( 'now', new \DateTimeZone( 'UTC' ) );
		$this->manager->method( 'get_current_date' )
			->willReturn( $current_date );

		$calculated_times = array();
		$post_mocks       = array();

		// Create a mock for each post
		foreach ( $queued_posts as $post_id ) {
			$post_mock              = new stdClass();
			$post_mock->ID          = $post_id;
			$post_mocks[ $post_id ] = $post_mock;
		}

		foreach ( $queued_posts as $index => $post_id ) {
			$last_publish_time = strtotime( $settings['startTime'] );
			if ( $index > 0 ) {
				$previous_post = $post_mocks[ $queued_posts[ $index - 1 ] ];
				if ( $previous_post ) {
					$last_publish_time = strtotime( $previous_post->post_date );
				}
			}

			$next_publish_time                 = $this->invokeMethod( $this->manager, 'calculate_next_publish_time', array( $index, $last_publish_time ) );
			$post_mocks[ $post_id ]->post_date = gmdate( 'Y-m-d H:i:s', $next_publish_time );

			$calculated_times[] = $next_publish_time;
			$this->assertEquals( $expected_times[ $index ], $next_publish_time, "The post should be scheduled correctly for index $index." );
		}
	}

	/**
	 * Test that getting the current order of posts in the queue works correctly.
	 *
	 * @return void
	 */
	public function test_get_current_order() {
		$post_ids = array(
			$this->factory->post->create( array( 'post_status' => 'queued' ) ),
			$this->factory->post->create( array( 'post_status' => 'queued' ) ),
			$this->factory->post->create( array( 'post_status' => 'queued' ) ),
		);

		// Sort the expected post IDs by their creation date
		usort(
			$post_ids,
			function ( $a, $b ) {
				return get_post( $a )->post_date <=> get_post( $b )->post_date;
			}
		);

		$current_order = $this->manager->get_current_order();

		// Extract only the IDs from the current order
		$current_order_ids = array_column( $current_order, 'ID' );

		$this->assertIsArray( $current_order_ids );
		$this->assertCount( 3, $current_order_ids );
		$this->assertEquals( $post_ids, $current_order_ids );
	}

	/**
	 * Test that recalculating the publish times for a new order works correctly.
	 *
	 * @return void
	 */
	public function test_recalculate_publish_times() {
		$post_ids = array(
			$this->factory->post->create( array( 'post_status' => 'queued' ) ),
			$this->factory->post->create( array( 'post_status' => 'queued' ) ),
			$this->factory->post->create( array( 'post_status' => 'queued' ) ),
		);

		$new_order     = array_reverse( $post_ids );
		$updated_posts = $this->manager->recalculate_publish_times( $new_order );

		$this->assertIsArray( $updated_posts );
		$this->assertCount( 3, $updated_posts );
		$this->assertEquals( $new_order[0], $updated_posts[0]['ID'] );
	}

	/**
	 * Test that shuffling the queue changes the order of the posts.
	 *
	 * @return void
	 */
	public function test_shuffle_queued_posts() {
		$post_ids = array(
			$this->factory->post->create( array( 'post_status' => 'queued' ) ),
			$this->factory->post->create( array( 'post_status' => 'queued' ) ),
			$this->factory->post->create( array( 'post_status' => 'queued' ) ),
			$this->factory->post->create( array( 'post_status' => 'queued' ) ),
			$this->factory->post->create( array( 'post_status' => 'queued' ) ),
			$this->factory->post->create( array( 'post_status' => 'queued' ) ),
			$this->factory->post->create( array( 'post_status' => 'queued' ) ),
			$this->factory->post->create( array( 'post_status' => 'queued' ) ),
			$this->factory->post->create( array( 'post_status' => 'queued' ) ),
			$this->factory->post->create( array( 'post_status' => 'queued' ) ),
		);

		$original_order = $post_ids;
		$shuffled       = false;
		$attempts       = 10;

		// Attempt to shuffle multiple times to ensure a change in order
		// Otherwise this test can fail randomly due to the order being the same sometimes for a small number of posts
		for ( $i = 0; $i < $attempts; $i++ ) {
			$shuffled_posts = $this->manager->shuffle_queued_posts();
			$shuffled_order = wp_list_pluck( $shuffled_posts, 'ID' );

			if ( $original_order !== $shuffled_order ) {
				$shuffled = true;
				break;
			}
		}

		$this->assertTrue( $shuffled, 'The post order should be shuffled.' );
	}

	/**
	 * Test recalculation of publish times when a post is removed from the queue.
	 *
	 * @return void
	 */
	public function test_recalculate_publish_times_on_post_removal() {
		// Create queued posts
		$post_ids = array(
			$this->factory->post->create( array( 'post_status' => 'queued' ) ),
			$this->factory->post->create( array( 'post_status' => 'queued' ) ),
			$this->factory->post->create( array( 'post_status' => 'queued' ) ),
		);

		// Simulate removing the second post from the queue
		wp_update_post(
			array(
				'ID'          => $post_ids[1],
				'post_status' => 'draft',
			)
		);
		do_action( 'transition_post_status', 'draft', 'queued', get_post( $post_ids[1] ) );

		// Fetch the updated queue
		$updated_queue = $this->manager->get_current_order();

		// Assert that the queue has been recalculated
		$this->assertCount( 2, $updated_queue, 'There should be 2 posts remaining in the queue.' );

		// Check that the publish times have been recalculated
		$first_post_time  = strtotime( $updated_queue[0]['post_date'] );
		$second_post_time = strtotime( $updated_queue[1]['post_date'] );
		$this->assertLessThan( $second_post_time, $first_post_time, 'The first post should be scheduled before the second post.' );
	}

	/**
	 * Test that pausing the queue removes all scheduled events for queued posts.
	 *
	 * @return void
	 */
	public function test_pause_queue() {
		$post_ids = array(
			$this->factory->post->create( array( 'post_status' => 'queued' ) ),
			$this->factory->post->create( array( 'post_status' => 'queued' ) ),
		);

		$this->manager->pause_queue();

		foreach ( $post_ids as $post_id ) {
			$next_scheduled = wp_next_scheduled( 'publish_queued_post', array( $post_id ) );
			$this->assertFalse( $next_scheduled, 'The post should not be scheduled for publishing when paused.' );
		}
	}

	/**
	 * Test that resuming the queue schedules the next publish time for queued posts.
	 *
	 * @return void
	 */
	public function test_resume_queue() {
		$post_ids = array(
			$this->factory->post->create( array( 'post_status' => 'queued' ) ),
			$this->factory->post->create( array( 'post_status' => 'queued' ) ),
		);

		$this->manager->pause_queue();
		$this->manager->resume_queue();

		foreach ( $post_ids as $post_id ) {
			$next_scheduled = wp_next_scheduled( 'publish_queued_post', array( $post_id ) );
			$this->assertNotFalse( $next_scheduled, 'The post should be rescheduled for publishing when resumed.' );
		}
	}

	/**
	 * Data provider for test_calculate_next_publish_time_with_gmt_offset.
	 *
	 * @return array
	 */
	public function gmtOffsetProvider() {
		return array(
			'GMT offset 0'              => array(
				'settings'      => array(
					'publishTimes' => 2,
					'startTime'    => '12:00 AM',
					'endTime'      => '1:00 AM',
				),
				'gmt_offset'    => 0,
				'current_date'  => '2023-10-01',
				'current_time'  => '05:00:00',
				// Expected time is the next day at 12:20am, since the time has passed for today
				'expected_time' => strtotime( '2023-10-02 00:20:00' ),
			),
			'GMT offset 0 but its 12am' => array(
				'settings'      => array(
					'publishTimes' => 2,
					'startTime'    => '12:00 AM',
					'endTime'      => '1:00 AM',
				),
				'gmt_offset'    => 0,
				'current_date'  => '2023-10-01',
				'current_time'  => '00:00:00',
				// Expected time is today at 12:20am, since the time has not passed for today
				'expected_time' => strtotime( '2023-10-01 00:20:00' ),
			),
			'GMT offset 5'              => array(
				'settings'      => array(
					'publishTimes' => 2,
					'startTime'    => '12:00 AM',
					'endTime'      => '1:00 AM',
				),
				'gmt_offset'    => 5,
				'current_date'  => '2023-10-01',
				'current_time'  => '05:00:00',
				// Expected time is the next day at 12:20am, since the time has passed for today
				'expected_time' => strtotime( '2023-10-02 00:20:00' ),
			),
			'GMT offset -3'             => array(
				'settings'         => array(
					'publishTimes' => 2,
					'startTime'    => '12:00 AM',
					'endTime'      => '1:00 AM',
				),
				'gmt_offset'       => -3,
				'current_date'     => '2023-10-01',
				'current_time_utc' => '18:00:00',
				// Expected time is the next day at 12:20am, since the time has passed for today
				'expected_time'    => strtotime( '2023-10-02 00:20:00' ),
			),
			'GMT offset -4 but its 1am' => array(
				'settings'         => array(
					'publishTimes' => 2,
					'startTime'    => '12:00 AM',
					'endTime'      => '1:00 AM',
				),
				'gmt_offset'       => -4,
				'current_date'     => '2023-10-01',
				'current_time_utc' => '01:00:00',
				// Expected time is today at 12:20am, since the time has not passed for today
				'expected_time'    => strtotime( '2023-10-01 00:20:00' ),
			),
			'GMT offset -4 but its 5am' => array(
				'settings'         => array(
					'publishTimes' => 2,
					'startTime'    => '12:00 AM',
					'endTime'      => '1:00 AM',
				),
				'gmt_offset'       => -4,
				'current_date'     => '2023-10-01',
				'current_time_utc' => '05:00:00',
				// Expected time is the next day at 12:20am, since the time has passed for today
				'expected_time'    => strtotime( '2023-10-02 00:20:00' ),
			),
			'GMT offset 5 but its 11pm' => array(
				'settings'         => array(
					'publishTimes' => 2,
					'startTime'    => '12:00 AM',
					'endTime'      => '1:00 AM',
				),
				'gmt_offset'       => 5,
				'current_date'     => '2023-10-01',
				'current_time_utc' => '23:00:00',
				// Expected time is the next day at 12:20am, since the time has passed for today
				'expected_time'    => strtotime( '2023-10-02 00:20:00' ),
			),
		);
	}

	/**
	 * Test that the calculate_next_publish_time method uses the current start or end date when a GMT offset is passed.
	 *
	 * @dataProvider gmtOffsetProvider
	 *
	 * @param array   $settings         The settings for the manager.
	 * @param integer $gmt_offset       The GMT offset.
	 * @param string  $current_date     The current date.
	 * @param string  $current_time_utc The current time in UTC.
	 * @param integer $expected_time    The expected publish time.
	 *
	 * @return void
	 */
	public function test_calculate_next_publish_time_with_gmt_offset( $settings, $gmt_offset, $current_date, $current_time_utc, $expected_time ) {
		$this->manager = $this->getMockBuilder( Manager::class )
			->setConstructorArgs( array( $settings ) )
			->onlyMethods( array( 'get_current_time', 'get_current_date' ) )
			->getMock();

		$fixed_time = strtotime( $current_date . ' ' . $current_time_utc );
		$this->manager->method( 'get_current_time' )
			->willReturn( $fixed_time );

		$fixed_date = new \DateTime( $current_date . ' ' . $current_time_utc, new \DateTimeZone( 'UTC' ) );
		$this->manager->method( 'get_current_date' )
			->willReturn( $fixed_date );

		$index             = 0;
		$last_publish_time = null;

		if ( null !== $last_publish_time ) {
			$last_publish_time += $gmt_offset * 3600;
		}

		$next_publish_time = $this->invokeMethod( $this->manager, 'calculate_next_publish_time', array( $index, $last_publish_time, $gmt_offset ) );

		$this->assertEquals( $expected_time, $next_publish_time, 'The start time should be adjusted for the GMT offset.' );
	}

	/**
	 * Test the get_next_queue_time method returns the correct next queue time.
	 *
	 * @return void
	 */
	public function test_get_next_queue_time() {
		$this->manager = $this->getMockBuilder( Manager::class )
			->setConstructorArgs( array( $this->settings ) )
			->onlyMethods( array( 'get_current_time', 'get_current_date' ) )
			->getMock();

		$current_time = strtotime( '10/24/2024 00:00:00' );
		$this->manager->method( 'get_current_time' )
			->willReturn( $current_time );

		$current_date = new \DateTime( '10/24/2024 00:00:00', new \DateTimeZone( 'UTC' ) );
		$this->manager->method( 'get_current_date' )
			->willReturn( $current_date );

		$post_ids = array(
			$this->factory->post->create(
				array(
					'post_status'   => 'queued',
					'post_date'     => '2024-10-24 00:20:00',
					'post_date_gmt' => get_gmt_from_date( '2024-10-24 00:20:00' ),
				)
			),
			$this->factory->post->create(
				array(
					'post_status' => 'queued',
				)
			),
		);

		// Set the post dates in the past
		$past_date_1 = '2024-10-24 00:20:00';
		$past_date_2 = '2024-10-24 00:40:00';

		wp_update_post(
			array(
				'ID'            => $post_ids[0],
				'post_date'     => $past_date_1,
				'post_date_gmt' => get_gmt_from_date( $past_date_1 ),
			)
		);

		wp_update_post(
			array(
				'ID'            => $post_ids[1],
				'post_date'     => $past_date_2,
				'post_date_gmt' => get_gmt_from_date( $past_date_2 ),
			)
		);

		$expected_next_queue_time = 'Oct 25, 2024 at 00:20';
		$actual_next_queue_time   = $this->manager->get_next_queue_time();
		$this->assertNotEmpty( $actual_next_queue_time );
		$this->assertEquals( $expected_next_queue_time, $actual_next_queue_time );
	}

	/**
	 * Helper method to invoke private or protected methods.
	 *
	 * @param object $instance    The object to invoke the method on.
	 * @param string $method_name The name of the method to invoke.
	 * @param array  $parameters  The parameters to pass to the method.
	 *
	 * @return mixed
	 */
	protected function invokeMethod( &$instance, $method_name, array $parameters = array() ) {
		$reflection = new \ReflectionClass( get_class( $instance ) );
		$method     = $reflection->getMethod( $method_name );
		$method->setAccessible( true );

		return $method->invokeArgs( $instance, $parameters );
	}
}
