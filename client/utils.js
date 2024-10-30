import { __ } from '@wordpress/i18n';

/**
 * This function takes a time string in 12-hour format (e.g., "3 pm") and converts it to 24-hour format.
 * It splits the input string into hour and period (am/pm), then adjusts the hour based on the period.
 * For example, "3 pm" becomes 15, and "12 am" becomes 0.
 *
 * @param {string} time - The time string in 12-hour format.
 * @return {number} - The hour in 24-hour format.
 */
export const convertTo24HourFormat = ( time ) => {
	const [ hour, period ] = time.split( ' ' );
	let hour24 = parseInt( hour, 10 );
	if ( period === 'pm' && hour24 !== 12 ) {
		hour24 += 12;
	} else if ( period === 'am' && hour24 === 12 ) {
		hour24 = 0;
	}
	return hour24;
};

/**
 * This function updates the table with new publish times for queued posts so we don't have to reload the page.
 * It iterates over the updatedPosts array, finds the corresponding row in the table by post ID,
 * and updates the date column with the new publish time.
 *
 * @param {Array} updatedPosts - An array of updated post objects containing ID, new publish time, and a formatted date column.
 */
export const updateTable = ( updatedPosts ) => {
	const table = document.getElementById( 'the-list' );
	updatedPosts.forEach( ( post ) => {
		const row = document.getElementById( `post-${ post.ID }` );
		if ( row ) {
			const dateCell = row.querySelector( '.column-date' );
			if ( dateCell ) {
				dateCell.innerHTML = `${ __( 'Queued', 'post-queue' ) }<br />${
					post.date_column
				}`;
			}
			// Reorder the row in the table
			table.appendChild( row );
		}
	} );
};
