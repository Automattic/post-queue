<?php

namespace WP_Post_Queue;

/**
 * Manages the post queue for scheduling and publishing posts in WordPress.
 */
class Manager {
	/**
	 * Queue Settings
	 *
	 * @var array $settings Configuration settings for the post queue manager.
	 */
	private $settings;

	/**
	 * Manager constructor.
	 *
	 * @param array $settings Configuration settings for the post queue manager.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Initializes the manager by setting up WordPress hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'transition_post_status', array( $this, 'handle_post_status_change' ), 10, 3 );
		add_action( 'publish_queued_post', array( $this, 'publish_post_now' ) );
	}

	/**
	 * Handles changes in post status to manage the queue.
	 * If a post becomes queued, it is added to the queue.
	 * If a post is removed from the queue, the scheduled event is removed and the queue is recalculated.
	 *
	 * @param string   $new_status The new status of the post.
	 * @param string   $old_status The old status of the post.
	 * @param \WP_Post $post       The post object.
	 *
	 * @return void
	 */
	public function handle_post_status_change( $new_status, $old_status, $post ) {
		if ( 'queued' === $new_status && 'queued' !== $old_status ) {
			$this->queue_post( $post->ID );
		} elseif ( 'queued' === $old_status && 'queued' !== $new_status && 'publish' !== $new_status ) {
			$this->remove_scheduled_event( $post->ID );

			// Reset the post's scheduled time
			$post_data = array(
				'ID'            => $post->ID,
				'post_date'     => current_time( 'mysql' ),
				'post_date_gmt' => get_gmt_from_date( current_time( 'mysql' ) ),
			);
			wp_update_post( $post_data );

			$current_queue = $this->get_current_order();
			$this->recalculate_publish_times( array_column( $current_queue, 'ID' ) );
		}
	}

	/**
	 * Gets the current time as a timestamp.
	 * This is separated out so it can be overridden for testing easier.
	 *
	 * @return integer The current timestamp.
	 */
	public function get_current_time() {
		return current_time( 'timestamp' );
	}

	/**
	 * Gets the current date as a DateTime object.
	 * This is separated out so it can be overridden for testing easier.
	 *
	 * @return \DateTime The current date.
	 */
	public function get_current_date() {
		return new \DateTime( 'now', new \DateTimeZone( 'UTC' ) );
	}

	/**
	 * Calculates the next publish time for a post in the queue.
	 *
	 * @param integer      $index             The index of the post in the queue.
	 * @param integer|null $last_publish_time The last publish time.
	 * @param integer|null $gmt_offset        The GMT offset.
	 *
	 * @return integer The timestamp of the next publish time.
	 */
	private function calculate_next_publish_time( $index, $last_publish_time = null, $gmt_offset = null ) {
		$posts_per_day = $this->settings['publishTimes'];

		$gmt_offset = $gmt_offset ? $gmt_offset : get_option( 'gmt_offset' );

		$current_date = $this->get_current_date();
		if ( $gmt_offset ) {
			// Convert the GMT offset to seconds, so we can support half hours
			$gmt_offset_seconds = $gmt_offset * 3600;
			$current_date->modify( $gmt_offset_seconds . ' seconds' );
		}

		$start_time = strtotime( $current_date->format( 'Y-m-d' ) . ' ' . $this->settings['startTime'] );
		$end_time   = strtotime( $current_date->format( 'Y-m-d' ) . ' ' . $this->settings['endTime'] );

		$interval     = ( $end_time - $start_time ) / ( $posts_per_day + 1 );
		$current_time = $this->get_current_time();

		if ( null === $last_publish_time ) {
			$last_publish_time = $start_time;
		}

		$next_publish_time = $last_publish_time + $interval;

		if ( $next_publish_time <= $current_time ) {
			$remaining_slots_today = floor( ( $end_time - $current_time ) / $interval );
			$slots_needed          = ( $index % $posts_per_day ) + 1;
			if ( $remaining_slots_today >= $slots_needed ) {
				$used_slots        = $posts_per_day - $remaining_slots_today;
				$next_publish_time = $start_time + ( $interval * ( $used_slots + $slots_needed ) );
			} else {
				$days_ahead        = ceil( ( $index + 1 ) / $posts_per_day );
				$next_publish_time = strtotime( "+$days_ahead days", $start_time ) + ( $interval * ( ( $index % $posts_per_day ) + 1 ) );
			}
		}

		if ( $next_publish_time >= $end_time && gmdate( 'Y-m-d', $next_publish_time ) === gmdate( 'Y-m-d', $current_time ) ) {
			$start_time_only   = gmdate( 'H:i:s', $start_time );
			$next_publish_time = strtotime( $current_date->format( 'Y-m-d' ) . ' +1 day ' . $start_time_only ) + $interval;
		} else {
			$next_day_end_time = strtotime( gmdate( 'Y-m-d', $next_publish_time ) . ' ' . gmdate( 'H:i:s', $end_time ) );
			if ( $next_publish_time >= $next_day_end_time ) {
				$days_ahead        = ceil( ( $next_publish_time - $start_time ) / ( 24 * 60 * 60 ) );
				$start_time_only   = gmdate( 'H:i:s', $start_time );
				$next_publish_time = strtotime( "+$days_ahead days " . $start_time_only ) + $interval;
			}
		}

		return $next_publish_time;
	}

	/**
	 * Queues a post by updating its publish time and scheduling the publish event.
	 *
	 * @param integer $post_id The ID of the post to queue.
	 *
	 * @return void
	 */
	public function queue_post( $post_id ) {
		$current_queue = $this->get_current_order();

		if ( end( $current_queue )['ID'] !== $post_id ) {
			$post_index = array_search( $post_id, array_column( $current_queue, 'ID' ), true );
			if ( false !== $post_index ) {
				$post_to_move = $current_queue[ $post_index ];
				unset( $current_queue[ $post_index ] );
				$current_queue   = array_values( $current_queue );
				$current_queue[] = $post_to_move;
			}
		}

		$target_post       = count( $current_queue ) === 2 ? $current_queue[0] : ( count( $current_queue ) > 1 ? $current_queue[ count( $current_queue ) - 2 ] : null );
		$last_publish_time = $target_post ? strtotime( $target_post['post_date'] ) : null;
		$next_publish_time = $this->calculate_next_publish_time( count( $current_queue ) - 1, $last_publish_time );

		$local_time = gmdate( 'Y-m-d H:i:s', $next_publish_time );
		$gmt_time   = get_gmt_from_date( $local_time );

		$post_data = array(
			'ID'            => $post_id,
			'post_date'     => $local_time,
			'post_date_gmt' => $gmt_time,
		);
		wp_update_post( $post_data );

		// Only schedule the post if the queue is not paused
		if ( ! $this->settings['wpQueuePaused'] ) {
			$this->schedule_queued_post( $post_id, $next_publish_time );
		}
	}

	/**
	 * Schedules a queued post in WordPress's cron system to be published at a specific time.
	 *
	 * @param integer $post_id      The ID of the post to schedule.
	 * @param integer $publish_time The timestamp of the publish time.
	 *
	 * @return void
	 */
	public function schedule_queued_post( $post_id, $publish_time ) {
		$gmt_publish_time = get_gmt_from_date( gmdate( 'Y-m-d H:i:s', $publish_time ), 'U' );
		if ( ! wp_next_scheduled( 'publish_queued_post', array( $post_id ) ) ) {
			wp_schedule_single_event( $gmt_publish_time, 'publish_queued_post', array( $post_id ) );
		}
	}

	/**
	 * Publishes a post immediately. This is the method that is called when the publish_queued_post event is triggered.
	 *
	 * @param integer $post_id The ID of the post to publish.
	 *
	 * @return void
	 */
	public function publish_post_now( $post_id ) {
		if ( $this->settings['wpQueuePaused'] ) {
			return; // Exit if the queue is paused
		}
		$post_data = array(
			'ID'          => $post_id,
			'post_status' => 'publish',
		);
		wp_update_post( $post_data );
	}

	/**
	 * Gets the current order of queued posts.
	 *
	 * @return array An array of post objects in the current order.
	 */
	public function get_current_order() {
		$queued_posts = get_posts(
			array(
				'post_status' => 'queued',
				'numberposts' => -1,
				'orderby'     => 'date',
				'order'       => 'ASC',
			)
		);

		return array_map(
			function ( $post ) {
				return array(
					'ID'        => $post->ID,
					'post_date' => $post->post_date,
				);
			},
			$queued_posts
		);
	}

	/**
	 * Recalculates the publish times for queued posts based on the new order.
	 *
	 * @param array        $new_order  The new order of the queued posts.
	 * @param integer|null $gmt_offset The GMT offset.
	 *
	 * @return array An array of updated posts with their new publish times.
	 */
	public function recalculate_publish_times( $new_order, $gmt_offset = null ) {
		$queued_posts = get_posts(
			array(
				'post_status' => 'queued',
				'numberposts' => -1,
				'orderby'     => 'date',
				'order'       => 'ASC',
			)
		);

		$new_order = array_map( 'intval', $new_order );

		// Separate posts into those that need rearranging and those that don't
		// This is because $new_order may be a partial order, where only some posts are rearranged.
		// For example when you drag and drop posts in the post list and have pagination enabled.
		$rearranged_posts = array_filter(
			$queued_posts,
			function ( $post ) use ( $new_order ) {
				return in_array( $post->ID, $new_order, true );
			}
		);

		$remaining_posts = array_filter(
			$queued_posts,
			function ( $post ) use ( $new_order ) {
				return ! in_array( $post->ID, $new_order, true );
			}
		);

		usort(
			$rearranged_posts,
			function ( $a, $b ) use ( $new_order ) {
				return array_search( $a->ID, $new_order, true ) <=> array_search( $b->ID, $new_order, true );
			}
		);

		$sorted_posts = array_merge( $rearranged_posts, $remaining_posts );

		$updated_posts     = array();
		$last_publish_time = null;

		foreach ( $sorted_posts as $index => $post ) {
			$new_publish_time  = $this->calculate_next_publish_time( $index, $last_publish_time, $gmt_offset );
			$last_publish_time = $new_publish_time;

			if ( ! $this->settings['wpQueuePaused'] ) {
				$this->update_scheduled_event( $post->ID, $new_publish_time );
			}

			$post_data = array(
				'ID'            => $post->ID,
				'post_date'     => gmdate( 'Y-m-d H:i:s', $new_publish_time ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $new_publish_time ),
			);

			if ( ! $this->settings['wpQueuePaused'] ) {
				wp_update_post( $post_data );
			}

			$updated_posts[] = array(
				'ID'               => $post->ID,
				'new_publish_time' => gmdate( 'Y-m-d H:i:s', $new_publish_time ),
				'date_column'      => sprintf(
					// translators: %1$s is the date, %2$s is the time.
					__( '%1$s at %2$s', 'post-queue' ),
					date_i18n( 'Y/m/d', $new_publish_time ),
					date_i18n( 'g:i a', $new_publish_time )
				),
			);
		}

		return $updated_posts;
	}

	/**
	 * Shuffles the queued posts randomly and recalculates their publish times.
	 *
	 * @return array An array of updated posts with their new publish times.
	 */
	public function shuffle_queued_posts() {
		$queued_posts = get_posts(
			array(
				'post_status' => 'queued',
				'numberposts' => -1,
				'orderby'     => 'date',
				'order'       => 'ASC',
			)
		);

		shuffle( $queued_posts );

		$new_order = wp_list_pluck( $queued_posts, 'ID' );

		$updated_posts = $this->recalculate_publish_times( $new_order );

		if ( ! $this->settings['wpQueuePaused'] ) {
			foreach ( $updated_posts as $post ) {
				$this->update_scheduled_event( $post['ID'], strtotime( $post['new_publish_time'] ) );
			}
		}

		return $updated_posts;
	}

	/**
	 * Updates the scheduled event for a post to publish at a new time.
	 *
	 * @param integer $post_id          The ID of the post to update.
	 * @param integer $new_publish_time The timestamp of the new publish time.
	 *
	 * @return void
	 */
	private function update_scheduled_event( $post_id, $new_publish_time ) {
		$this->remove_scheduled_event( $post_id );
		$this->schedule_queued_post( $post_id, $new_publish_time );
	}

	/**
	 * Removes the scheduled event for a post.
	 *
	 * @param integer $post_id The ID of the post to remove the scheduled event for.
	 *
	 * @return void
	 */
	private function remove_scheduled_event( $post_id ) {
		$timestamp = wp_next_scheduled( 'publish_queued_post', array( $post_id ) );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'publish_queued_post', array( $post_id ) );
		}
	}

	/**
	 * Pauses the queue by removing all scheduled events for queued posts.
	 *
	 * @return void
	 */
	public function pause_queue() {
		$queued_posts = $this->get_current_order();
		foreach ( $queued_posts as $post ) {
			$this->remove_scheduled_event( $post['ID'] );
		}
	}

	/**
	 * Resumes the queue by recalculating the publish times for all queued posts.
	 *
	 * @return void
	 */
	public function resume_queue() {
		$queued_posts = $this->get_current_order();
		$this->recalculate_publish_times( array_column( $queued_posts, 'ID' ) );
	}
	/**
	 * Gets the next estimated queue time for the last post in the queue.
	 *
	 * @return string The next queue time in a human-readable format.
	 */
	public function get_next_queue_time() {
		$current_queue = $this->get_current_order();

		// Check if the queue is empty
		if ( empty( $current_queue ) ) {
			$index             = 0;
			$last_publish_time = null;
		} else {
			$last_post         = end( $current_queue );
			$index             = count( $current_queue ) - 1;
			$last_publish_time = strtotime( $last_post['post_date'] );
		}

		$next_publish_time = $this->calculate_next_publish_time( $index, $last_publish_time );

		return date_i18n( 'M j, Y \a\t H:i', $next_publish_time );
	}
}
