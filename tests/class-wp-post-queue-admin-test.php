<?php

use Post_Queue\Admin;

/**
 * Test the Admin class.
 * Which is responsible for the admin UI side of the plugin.
 */
class Test_Post_Queue_Admin extends WP_UnitTestCase {
	private $admin;
	private $settings;

	/**
	 * Sets up the tests.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->settings = array(
			'publishTimes'    => 2,
			'startTime'       => '12 am',
			'endTime'         => '1 am',
			'postQueuePaused' => false,
		);
		$this->admin    = new Admin( $this->settings );
	}

	/**
	 * Test the registration of the post status "queued", making sure it's in the registered post statuses.
	 *
	 * @return void
	 */
	public function test_register_post_status() {
		$this->admin->register_post_status();
		$this->assertTrue( in_array( 'queued', get_post_stati(), true ), 'The post status "queued" should be registered.' );
	}

	/**
	 * Test the modification of post labels when the post status is "queued".
	 * On the edit page only, the post labels should be modified to "Queue" instead of "Posts".
	 *
	 * @return void
	 */
	public function test_modify_post_labels() {
		global $pagenow;
		$original_pagenow = $pagenow;

		$pagenow = 'edit.php';

		$labels                = new stdClass();
		$labels->name          = 'Posts';
		$labels->singular_name = 'Post';

		set_query_var( 'post_status', 'queued' );
		$modified_labels = $this->admin->modify_post_labels( $labels );

		$this->assertEquals( 'Queue', $modified_labels->name );
		$this->assertEquals( 'Queue', $modified_labels->singular_name );

		$pagenow = $original_pagenow;
	}

	/**
	 * Test the conditional addition of the drag handle column.
	 * The drag handle column should be added only when the post status is "queued".
	 *
	 * @return void
	 */
	public function test_conditionally_add_drag_handle_column() {
		$columns = array(
			'title'  => 'Title',
			'author' => 'Author',
			'date'   => 'Date',
		);

		set_query_var( 'post_status', 'queued' );
		$modified_columns = $this->admin->conditionally_add_drag_handle_column( $columns );

		$this->assertArrayHasKey( 'drag_handle', $modified_columns );
		$this->assertEquals( '<span class="dashicons dashicons-menu-alt"></span>', $modified_columns['drag_handle'] );
	}

	/**
	 * Test the display of post states when the post status is "queued".
	 * The post states should include "Queued" next to the post title in the admin.
	 *
	 * @return void
	 */
	public function test_display_post_states() {
		$post        = $this->factory->post->create_and_get( array( 'post_status' => 'queued' ) );
		$post_states = $this->admin->display_post_states( array(), $post );

		$this->assertContains( 'Queued', $post_states );
	}

	/**
	 * Test the post date column status when the post status is "queued".
	 * The post date column status should be "Queued" when the post status is "queued", instead of "Last Modified".
	 *
	 * @return void
	 */
	public function test_post_date_column_status() {
		$post   = $this->factory->post->create_and_get( array( 'post_status' => 'queued' ) );
		$status = $this->admin->post_date_column_status( '', $post, 'date', 'list' );

		$this->assertEquals( 'Queued', $status );
	}

	/**
	 * Test the registration of settings.
	 * The settings "post_queue_publish_times", "post_queue_start_time", "post_queue_end_time", and "post_queue_paused" should be registered.
	 *
	 * @return void
	 */
	public function test_register_settings() {
		$this->admin->register_settings();
		$this->assertNotNull( get_option( 'post_queue_publish_times' ), 'The setting "post_queue_publish_times" should be registered.' );
		$this->assertNotNull( get_option( 'post_queue_start_time' ), 'The setting "post_queue_start_time" should be registered.' );
		$this->assertNotNull( get_option( 'post_queue_end_time' ), 'The setting "post_queue_end_time" should be registered.' );
		$this->assertNotNull( get_option( 'post_queue_paused' ), 'The setting "post_queue_paused" should be registered.' );
	}

	/**
	 * Test the addition and highlighting of the queue menu item.
	 * The menu item should be added and highlighted correctly when the post status is "queued".
	 *
	 * @return void
	 */
	public function test_adds_and_highlight_queue_menu_item() {
		global $parent_file, $submenu_file, $pagenow;
		$pagenow = 'edit.php';
		set_query_var( 'post_status', 'queued' );

		$this->admin->highlight_queue_menu_item();

		$this->assertEquals( 'edit.php', $parent_file, 'The parent file should be "edit.php".' );
		$this->assertEquals( 'edit.php?post_status=queued&post_type=post', $submenu_file, 'The submenu file should be "edit.php?post_status=queued&post_type=post".' );
	}

	/**
	 * Test the set default queue order method.
	 * The method should set the default rderby and order of the query to "date" and "ASC" when the post status is "queued".
	 * This way we can see the next to be published post at the top of the list.
	 *
	 * @return void
	 */
	public function test_set_default_queue_order() {
		set_current_screen( 'edit-post' );
		set_query_var( 'post_status', 'queued' );

		$query = $this->getMockBuilder( 'WP_Query' )
						->setMethods( array( 'is_main_query', 'set' ) )
						->getMock();

		$query->expects( $this->once() )
				->method( 'is_main_query' )
				->willReturn( true );

		$query->expects( $this->exactly( 2 ) )
				->method( 'set' )
				->withConsecutive(
					array( $this->equalTo( 'orderby' ), $this->equalTo( 'date' ) ),
					array( $this->equalTo( 'order' ), $this->equalTo( 'ASC' ) )
				);

		$this->admin->set_default_queue_order( $query );

		set_current_screen( null );
	}
}
