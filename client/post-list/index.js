import apiFetch from '@wordpress/api-fetch';
import Sortable from 'sortablejs';
import { updateTable } from '../utils';
import './index.css';

/**
 * This file handles the drag-and-drop reordering functionality for the WP Post Queue plugin,
 * as well as other interactions with the post list like quick editing and bulk editing.
 *
 * It uses the SortableJS library to enable drag-and-drop sorting of posts in the queue.
 * When the order of posts is changed, it sends the new order to the server to recalculate publish times and update the table without reloading the page.
 */

document.addEventListener( 'DOMContentLoaded', function () {
	const list = document.getElementById( 'the-list' );
	if ( list && postQueuePluginPostListData.isQueuePage ) {
		Sortable.create( list, {
			animation: 150,
			onEnd() {
				const order = Array.from( list.children ).map(
					( item ) => item.id
				);

				recalculatePublishTimes( order );
			},
		} );
	}

	// Add 'Queued' status to the quick edit dropdown
	function addQueuedStatusToQuickEdit() {
		const statusSelects = document.querySelectorAll(
			'select[name="_status"]'
		);
		statusSelects.forEach( ( select ) => {
			if ( ! select.querySelector( 'option[value="queued"]' ) ) {
				const option = document.createElement( 'option' );
				option.value = 'queued';
				option.textContent = 'Queued';
				option.selected = postQueuePluginPostListData.isQueuePage;
				select.appendChild( option );
			}
		} );
	}

	// Hook into the quick edit events
	document.addEventListener( 'click', function ( event ) {
		if ( event.target.classList.contains( 'editinline' ) ) {
			addQueuedStatusToQuickEdit();

			document
				.querySelectorAll( '.inline-edit-post' )
				.forEach( ( post ) => {
					const inlineEditSaveButtons = post.querySelectorAll(
						'.inline-edit-save .button'
					);
					inlineEditSaveButtons.forEach( ( button ) => {
						button.addEventListener( 'click', function ( _event ) {
							_event.preventDefault();
							// Reload the page to refresh the table, all the new queue times, etc.
							window.location.reload();
						} );
					} );
				} );
		}
	} );

	// Add 'Queued' status to the bulk edit dropdown
	function addQueuedStatusToBulkEdit() {
		const bulkStatusSelects = document.querySelectorAll(
			'#bulk-edit select[name="_status"]'
		);
		bulkStatusSelects.forEach( ( select ) => {
			if ( ! select.querySelector( 'option[value="queued"]' ) ) {
				const option = document.createElement( 'option' );
				option.value = 'queued';
				option.textContent = 'Queued';
				select.appendChild( option );
			}
		} );
	}

	// Hook into the bulk edit events
	document.addEventListener( 'click', function ( event ) {
		if ( event.target.id === 'doaction' ) {
			const bulkActionSelector = document.querySelector(
				'#bulk-action-selector-top'
			);
			if ( bulkActionSelector && bulkActionSelector.value === 'edit' ) {
				addQueuedStatusToBulkEdit();
			}
		}
	} );
} );

function recalculatePublishTimes( newOrder ) {
	newOrder = newOrder
		? newOrder.map( ( key ) => key.replace( 'post-', '' ) )
		: [];
	apiFetch( {
		path: '/post-queue/v1/recalculate',
		method: 'POST',
		data: { order: newOrder },
	} )
		.then( ( response ) => {
			updateTable( response );
		} )
		.catch( ( error ) => {
			console.error( 'Error recalculating publish times:', error );
		} );
}
