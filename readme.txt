=== Post Queue ===
Contributors: automattic, jshreve
Tags: post queue, post scheduler
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 0.2.3
Requires PHP: 7.0
License: GPLv2 or later
Text Domain: post-queue
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Effortlessly queue and manage blog posts with daily limits, custom start/end times, and controls to pause or resume publishing as needed.

== Description ==

This plugin is designed to help you manage and schedule your blog posts efficiently. It allows you to configure the number of posts to publish per day, set start and end times for publishing, and pause or resume the queue as needed.

Unlike scheduled posts, queued posts are not published at user-specific times, but rather based on the queue settings. This allows for maintaining a steady flow of content, such as regularly publishing blog posts or social media content, without needing to manually schedule each post.

**Features:**

- **Automatic Scheduling**: Automatically publish queued posts a specified number of times per day.
- **Time Configuration**: Set start and end times for publishing posts.
- **Queue Management**: Pause and resume the queue with ease.
- **Reordering**: Drag and drop posts to reorder them in the queue or shuffle the entire queue.

== Installation ==

1. Download the plugin and upload it to your WordPress site's `wp-content/plugins` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to the queue page (Posts > Queue) to configure your post queue settings.

== Usage ==

- Access the queue page (Posts > Queue) to view and manage your queued posts.
- Configure the number of posts to publish per day and set the start and end times.
- Use the "Pause Queue" button to temporarily stop the queue.
- Use the "Resume Queue" button to restart the queue.
- Click "Shuffle Queue" to randomize the order of posts.
- Add new posts to the queue by selecting "Queued" under the post status and visibility dropdown in the editor.

== Source Code and Development ==

The source code for the Post Queue plugin is publicly available and maintained on GitHub. [Post Queue GitHub Repository](https://github.com/Automattic/post-queue).

This repository includes all the necessary build tools and documentation on how to use them. We encourage developers to explore and contribute to the project!

== Changelog ==

= 0.2.3=
* Made sure settings are properly sanitized before saving.

= 0.2.2 =
* Cleaned up code and verified compatibility with WordPress 6.7.

= 0.2.1 =
* Initial release with core features for post queue management.

