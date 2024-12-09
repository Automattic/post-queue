import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Hooks into the UI of the Classic Editor plugin.
 *
 * "Queued" is added as a post status option, and the publish button is updated
 * accordingly based on the post status. We also want to show the next queued time
 * for queued posts, and fetch that from the server.
 */
document.addEventListener( 'DOMContentLoaded', function () {
	const postStatusSelect = document.querySelector(
		'select[name="post_status"]'
	);

	if ( ! postStatusSelect ) {
		return;
	}

	addQueuedStatusOption( postStatusSelect );

	const hiddenPostStatus = document.querySelector(
		'input[name="hidden_post_status"]'
	);

	if ( hiddenPostStatus && hiddenPostStatus.value ) {
		postStatusSelect.value = hiddenPostStatus.value;
	}

	updatePostStatusDisplay( postStatusSelect );
	if ( postStatusSelect.value === 'queued' ) {
		updatePublishButton( 'Save' );
	}

	document
		.querySelector( '.save-post-status' )
		.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			updatePostStatusDisplay( postStatusSelect );
			hiddenPostStatus.value = postStatusSelect.value;
			setTimeout( () => {
				updatePublishButton(
					postStatusSelect.value === 'queued' ? 'Queue' : 'Save'
				);
				updateTimestampDisplay( postStatusSelect );
			}, 100 );
		} );

	document
		.querySelector( '#publish' )
		.addEventListener( 'click', function () {
			const originalPostStatus = document.querySelector(
				'input[name="original_post_status"]'
			);
			originalPostStatus.value = postStatusSelect.value;
			hiddenPostStatus.value = postStatusSelect.value;
			updatePostStatusDisplay( postStatusSelect );
		} );

	updatePublishButton();
	updatePostStatusDisplay( postStatusSelect );

	if ( postQueuePluginData.isNewPost ) {
		updateTimestampDisplay( postStatusSelect );
	}
} );

/**
 * Adds a "Queued" option to the post status dropdown.
 *
 * @param {HTMLElement} selectElement - The select element for the post status.
 */
const addQueuedStatusOption = ( selectElement ) => {
	const queuedOption = document.createElement( 'option' );
	queuedOption.value = 'queued';
	queuedOption.textContent = __( 'Queued', 'post-queue' );

	// Append the "Queued" option to the dropdown
	selectElement.appendChild( queuedOption );
};

/**
 * Updates the post status display to hide the edit timestamp link when the post is queued.
 *
 * @param {HTMLElement} postStatusSelect - The select element for the post status.
 */
const updatePostStatusDisplay = ( postStatusSelect ) => {
	document.getElementById( 'post-status-display' ).textContent =
		postStatusSelect.options[ postStatusSelect.selectedIndex ].text;

	const editTimestamp = document.querySelector( '.edit-timestamp' );
	editTimestamp.style.display =
		postStatusSelect.value === 'queued' ? 'none' : '';
};

/**
 * Updates the publish button text and name (action).
 *
 * @param {string} text - The text to set for the publish button.
 */
const updatePublishButton = ( text ) => {
	if ( ! text ) {
		return;
	}

	const publishButton = document.querySelector( '#publish' );
	if ( publishButton ) {
		const label =
			text === 'Save'
				? __( 'Save', 'post-queue' )
				: __( 'Queue', 'post-queue' );
		publishButton.value = label;
		publishButton.name = 'save';
	}
};

/**
 * Updates the timestamp display to show the scheduled time for queued posts.
 *
 * @param {HTMLElement} postStatusSelect - The select element for the post status.
 */
const updateTimestampDisplay = async ( postStatusSelect ) => {
	if ( postStatusSelect.value === 'queued' ) {
		let queuedTime = document.querySelector( '#timestamp b' ).textContent;
		if ( postQueuePluginData.isNewPost ) {
			queuedTime = await fetchNextQueueTime();
		}
		if ( queuedTime ) {
			const timestampElement = document.querySelector( '#timestamp' );
			if ( timestampElement ) {
				timestampElement.innerHTML = sprintf(
					/* translators: %s: The scheduled time. */
					__( 'Schedule for: <b>%s</b>', 'post-queue' ),
					queuedTime
				);
			}
		}
	}
};

/**
 * Fetches the next queue time from the API.
 *
 * @return {Promise<string|null>} The next queue time or null if there's an error.
 */
const fetchNextQueueTime = async () => {
	try {
		const data = await apiFetch( {
			path: '/post-queue/v1/next-queue-time',
		} );
		return data.nextQueueTime;
	} catch ( error ) {
		console.error( 'Error fetching next queue time:', error );
		return null;
	}
};
