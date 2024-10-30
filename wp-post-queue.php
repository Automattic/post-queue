<?php
/*
 * Plugin Name: WP Post Queue
 * Description: A plugin to add a Tumblr-like queue feature for WordPress posts.
 * Version: 0.2.0
 * Author: Automattic
 * Text Domain: wp-post-queue
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

define( 'WP_POST_QUEUE_VERSION', '0.2.0' );

require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-post-queue.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-post-queue-rest-api.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-post-queue-admin.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-post-queue-manager.php';

use WP_Post_Queue\WP_Post_Queue;

$wp_post_queue = new WP_Post_Queue();
$wp_post_queue->run();

register_deactivation_hook( __FILE__, 'wp_post_queue_update_queued_posts_status' );

/**
 * Update the status of queued posts to 'scheduled' on plugin deactivation.
 *
 * @return void
 */
function wp_post_queue_update_queued_posts_status() {
	$queued_posts = get_posts(
		array(
			'post_status' => 'queued',
			'post_type'   => 'post',
			'numberposts' => -1,
		)
	);

	foreach ( $queued_posts as $post ) {
		wp_update_post(
			array(
				'ID'          => $post->ID,
				'post_status' => 'future',
			)
		);
	}
}
