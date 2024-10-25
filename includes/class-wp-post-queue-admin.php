<?php

namespace WP_Post_Queue;

/**
 * The Admin class is responsible for the admin UI side of the plugin.
 * It registers the admin menu, enqueues the admin scripts and styles, and adds the settings to the edit page.
 * It also does a number of UI updates to make the queue more user-friendly (such as drag and drop reordering).
 */
class Admin {
	/**
	 * The settings for the queue.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor for the Admin class.
	 *
	 * @param array $settings The settings for the queue.
	 *
	 * @return void
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Hook into WordPress to customize the admin UI.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
		add_action( 'init', array( $this, 'register_post_status' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_management_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_management_styles' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_notices', array( $this, 'add_settings_to_edit_page' ) );
		add_action( 'admin_menu', array( $this, 'add_queue_menu_item' ) );
		add_action( 'admin_head', array( $this, 'highlight_queue_menu_item' ) );
		add_filter( 'post_type_labels_post', array( $this, 'modify_post_labels' ) );
		add_filter( 'manage_posts_columns', array( $this, 'conditionally_add_drag_handle_column' ) );
		add_action( 'manage_posts_custom_column', array( $this, 'conditionally_populate_drag_handle_column' ), 10, 2 );
		add_filter( 'display_post_states', array( $this, 'display_post_states' ), 10, 2 );
		add_filter( 'post_date_column_status', array( $this, 'post_date_column_status' ), 10, 4 );
		add_action( 'pre_get_posts', array( $this, 'set_default_queue_order' ) );
		add_action( 'update_option_timezone_string', array( $this, 'handle_timezone_or_gmt_offset_update' ), 10, 2 );
		add_action( 'update_option_gmt_offset', array( $this, 'handle_timezone_or_gmt_offset_update' ), 10, 2 );
	}

	/**
	 * Enqueue the block editor assets.
	 * editor.js is the main script that is used to render the queue UI in the block editor,
	 * under the status and visibility modal
	 *
	 * @return void
	 */
	public function enqueue_block_editor_assets() {
		wp_enqueue_script(
			'wp-queue-plugin',
			plugins_url( '/build/editor.js', __DIR__ ),
			array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-compose' ),
			WP_POST_QUEUE_VERSION,
			true
		);

		wp_enqueue_style(
			'wp-post-queue-editor-css',
			plugins_url( '/client/editor/index.css', __DIR__ ),
			array(),
			WP_POST_QUEUE_VERSION
		);
	}

	/**
	 * Register the queued post status with WordPress.
	 *
	 * @return void
	 */
	public function register_post_status() {
		register_post_status(
			'queued',
			array(
				'label'                     => __( 'Queued', 'wp-post-queue' ),
				'public'                    => false,
				'private'                   => true,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				// translators: %s is the number of posts.
				'label_count'               => _n_noop( 'Queued <span class="count">(%s)</span>', 'Queued <span class="count">(%s)</span>', 'wp-post-queue' ),
			)
		);
	}

	/**
	 * Enqueue the admin scripts for the queue management page.
	 * This includes the settings panel script, and the drag and drop reorder script.
	 *
	 * @param string $hook The current admin page.
	 *
	 * @return void
	 */
	public function enqueue_management_scripts( $hook ) {
		if ( 'edit.php' === $hook && 'queued' === get_query_var( 'post_status' ) ) {
			wp_enqueue_script(
				'wp-queue-settings-panel-script',
				plugins_url( '/build/settings-panel.js', __DIR__ ),
				array( 'wp-element', 'wp-components', 'wp-data', 'wp-api', 'wp-api-fetch', 'wp-redux-routine' ),
				WP_POST_QUEUE_VERSION,
				true
			);

			wp_localize_script(
				'wp-queue-settings-panel-script',
				'wpQueuePluginData',
				array(
					'settingsUrl'   => admin_url( 'options-general.php' ),
					'timezone'      => get_option( 'timezone_string' ),
					'gmtOffset'     => get_option( 'gmt_offset' ),
					'nonce'         => wp_create_nonce( 'wp_rest' ),
					'publishTimes'  => $this->settings['publishTimes'],
					'startTime'     => $this->settings['startTime'],
					'endTime'       => $this->settings['endTime'],
					'wpQueuePaused' => $this->settings['wpQueuePaused'],
				)
			);

			wp_enqueue_script(
				'drag-drop-reorder',
				plugins_url( '/build/drag-drop-reorder.js', __DIR__ ),
				array( 'wp-data', 'wp-api', 'wp-api-fetch' ),
				WP_POST_QUEUE_VERSION,
				true
			);
		}
	}

	/**
	 * Enqueue the admin styles for the queue management page.
	 * This includes the styles for the settings panel, and the drag and drop reorder styles.
	 *
	 * @param string $hook The current admin page.
	 *
	 * @return void
	 */
	public function enqueue_management_styles( $hook ) {
		if ( 'edit.php' === $hook && 'queued' === get_query_var( 'post_status' ) ) {
			wp_enqueue_style(
				'wp-queue-settings-panel-css',
				plugins_url( '/build/settings-panel.css', __DIR__ ),
				array( 'wp-components', 'wp-preferences' ),
				WP_POST_QUEUE_VERSION
			);

			wp_enqueue_style(
				'wp-queue-drag-drop-css',
				plugins_url( '/build/drag-drop-reorder.css', __DIR__ ),
				array(),
				WP_POST_QUEUE_VERSION
			);
		}
	}

	/**
	 * Registers all of the settings for the queue with WordPress.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting( 'wp_queue_settings', 'wp_queue_publish_times' );
		register_setting( 'wp_queue_settings', 'wp_queue_start_time' );
		register_setting( 'wp_queue_settings', 'wp_queue_end_time' );
		register_setting( 'wp_queue_settings', 'wp_queue_paused' );

		$this->settings = $this->get_settings();
	}

	/**
	 * Get the settings for the queue.
	 *
	 * @return array The settings for the queue.
	 */
	public function get_settings() {
		$default_settings = array(
			'publishTimes'  => 2,
			'startTime'     => '12 am',
			'endTime'       => '1 am',
			'wpQueuePaused' => false,
		);

		return array(
			'publishTimes'  => get_option( 'wp_queue_publish_times', $default_settings['publishTimes'] ),
			'startTime'     => get_option( 'wp_queue_start_time', $default_settings['startTime'] ),
			'endTime'       => get_option( 'wp_queue_end_time', $default_settings['endTime'] ),
			'wpQueuePaused' => get_option( 'wp_queue_paused', $default_settings['wpQueuePaused'] ),
		);
	}

	/**
	 * Adds a div so that we can render the settings on the queue management page.
	 *
	 * @return void
	 */
	public function add_settings_to_edit_page() {
		$screen = get_current_screen();
		if ( 'edit-post' === $screen->id && 'queued' === get_query_var( 'post_status' ) ) {
			$this->render_settings_panel();
		}
	}

	/**
	 * Div placeholder for the settings panel. React will render this.
	 *
	 * @return void
	 */
	public function render_settings_panel() {
		?>
		<div id="queue-settings-panel"></div>
		<?php
	}

	/**
	 * Add the queue menu item to the admin menu.
	 *
	 * @return void
	 */
	public function add_queue_menu_item() {
		add_submenu_page(
			'edit.php',
			__( 'Queue', 'wp-post-queue' ),
			__( 'Queue', 'wp-post-queue' ),
			'edit_posts',
			'edit.php?post_status=queued&post_type=post'
		);
	}

	/**
	 * Properly highlights the queue menu item when the current page is the queue management page.
	 *
	 * @return void
	 */
	public function highlight_queue_menu_item() {
		global $parent_file, $submenu_file, $pagenow;

		if ( 'edit.php' === $pagenow && 'queued' === get_query_var( 'post_status' ) ) {
			// We ignore the global variables override here because we want to set the parent file and submenu file, and
			// this seems to be the only way to do it.

			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$parent_file = 'edit.php';
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$submenu_file = 'edit.php?post_status=queued&post_type=post';
		}
	}

	/**
	 * Modify the post labels for the queue. This puts the word "Queue" at the top of the admin page,
	 * instead of "Posts".
	 *
	 * @param object $labels The labels for the posts.
	 *
	 * @return object The modified labels.
	 */
	public function modify_post_labels( $labels ) {
		global $pagenow, $post_type;

		if ( 'edit.php' === $pagenow && 'queued' === get_query_var( 'post_status' ) ) {
			$labels->name          = __( 'Queue', 'wp-post-queue' );
			$labels->singular_name = __( 'Queue', 'wp-post-queue' );
		}

		return $labels;
	}

	/**
	 * Adds the drag handle column to the queue management page.
	 *
	 * @param array $columns The columns for the posts.
	 *
	 * @return array The modified columns.
	 */
	public function conditionally_add_drag_handle_column( $columns ) {
		if ( 'queued' === get_query_var( 'post_status' ) ) {
			$new_columns = array( 'drag_handle' => '<span class="dashicons dashicons-menu-alt"></span>' );
			return array_merge( $new_columns, $columns );
		}
		return $columns;
	}

	/**
	 * Populates the drag handle column with the drag handle for each post in the queue.
	 *
	 * @param string  $column  The column name.
	 * @param integer $post_id The ID of the post.
	 *
	 * @return void
	 */
	public function conditionally_populate_drag_handle_column( $column, $post_id ) {
		if ( 'drag_handle' === $column && 'queued' === get_query_var( 'post_status' ) ) {
			echo '<span class="drag-handle"><span class="dashicons dashicons-menu-alt"></span></span>';
		}
	}

	/**
	 * Adds the queued post state to the end of the post title in the admin. Like how
	 * 'Scheduled' is added to the post title when a post is scheduled.
	 *
	 * @param array  $post_states The post states for the post.
	 * @param object $post        The post object.
	 *
	 * @return array The modified post states.
	 */
	public function display_post_states( $post_states, $post ) {
		if ( get_post_status( $post->ID ) === 'queued' ) {
			$post_states[] = __( 'Queued', 'wp-post-queue' );
		}
		return $post_states;
	}

	/**
	 * Overrides the post date column in the admin to show "Queued" instead of "Last Modified".
	 *
	 * @param string $status      The status of the post.
	 * @param object $post        The post object.
	 * @param string $column_name The name of the column.
	 * @param string $mode        The display mode of the post date column.
	 *
	 * @return string The modified status.
	 */
	public function post_date_column_status( $status, $post, $column_name, $mode ) {
		if ( 'queued' === $post->post_status ) {
			$status = __( 'Queued', 'wp-post-queue' );
		}
		return $status;
	}

	/**
	 * Sets the default order for the queue, so that the next to be published are at the top.
	 *
	 * @param object $query The query object.
	 *
	 * @return void
	 */
	public function set_default_queue_order( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$screen = get_current_screen();
		if ( 'edit-post' === $screen->id && 'queued' === get_query_var( 'post_status' ) ) {
			$orderby = get_query_var( 'orderby' );
			$order   = get_query_var( 'order' );

			$query->set( 'orderby', $orderby ? $orderby : 'date' );
			$query->set( 'order', $order ? $order : 'ASC' );
		}
	}

	/**
	 * Handle recalculating the queue times when the timezone or GMT offset is updated.
	 *
	 * @param string $old_value The old value of the setting.
	 * @param string $new_value The new value of the setting.
	 *
	 * @return void
	 */
	public function handle_timezone_or_gmt_offset_update( $old_value, $new_value ) {
		static $has_run = false;

		if ( $has_run || empty( $new_value ) ) {
			return;
		}

		$has_run = true;

		$this->settings = $this->get_settings();

		$gmt_offset = null;
		if ( is_numeric( $new_value ) ) {
			$gmt_offset = $new_value;
		}

		$manager = new Manager( $this->settings );
		$manager->recalculate_publish_times( array_column( $manager->get_current_order(), 'ID' ) );
	}
}
