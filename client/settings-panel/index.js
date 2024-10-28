import React, { useCallback, useEffect, useState } from 'react';

import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, SelectControl, Spinner } from '@wordpress/components';
import { compose } from '@wordpress/compose';
import { withDispatch, withSelect } from '@wordpress/data';
import { createRoot } from '@wordpress/element';
import { __, _x } from '@wordpress/i18n';
import { convertTo24HourFormat, updateTable } from '../utils';
import './index.css';
import './store';

/**
 * SettingsPanel Component
 *
 * This component provides a user interface for managing the settings of the WP Post Queue plugin.
 * It allows users to configure the number of posts to publish per day, the start time, and the end time for publishing.
 * Users can also pause or resume the queue.
 *
 * @param props
 * @param props.settings
 * @param props.settings.publishTimes
 * @param props.settings.startTime
 * @param props.settings.endTime
 * @param props.settings.wpQueuePaused
 * @param props.saveSettings
 */
const SettingsPanel = ( {
	settings: { publishTimes, startTime, endTime, wpQueuePaused },
	saveSettings,
} ) => {
	const [ localPublishTimes, setLocalPublishTimes ] =
		useState( publishTimes );
	const [ localStartTime, setLocalStartTime ] = useState( startTime );
	const [ localEndTime, setLocalEndTime ] = useState( endTime );
	const [ isPaused, setIsPaused ] = useState( wpQueuePaused );
	const [ isDirty, setIsDirty ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ isLoading, setIsLoading ] = useState( false );

	useEffect( () => {
		setLocalPublishTimes( publishTimes );
		setLocalStartTime( startTime );
		setLocalEndTime( endTime );
		setIsPaused( wpQueuePaused );
	}, [ publishTimes, startTime, endTime, wpQueuePaused ] );

	const handlePublishTimesChange = ( value ) => {
		setLocalPublishTimes( value );
		setIsDirty( true );
	};

	const handleStartTimeChange = ( value ) => {
		setLocalStartTime( value );
		setIsDirty( true );
	};

	const handleEndTimeChange = ( value ) => {
		setLocalEndTime( value );
		setIsDirty( true );
	};

	const handleSave = async () => {
		const startHour = convertTo24HourFormat( localStartTime );
		const endHour = convertTo24HourFormat( localEndTime );

		if ( startHour >= endHour ) {
			setError(
				__( 'The start time must be before end time.', 'wp-post-queue' )
			);
			return;
		}

		try {
			const response = await saveSettings( {
				publish_times: localPublishTimes,
				start_time: localStartTime,
				end_time: localEndTime,
				wp_queue_paused: !! isPaused,
			} );

			updateTable( response );

			setIsDirty( false );
		} catch ( saveError ) {
			setError(
				__(
					'Failed to save settings. Please try again.',
					'wp-post-queue'
				)
			);
		}
	};

	const handlePause = async () => {
		try {
			await saveSettings( {
				wp_queue_paused: true,
			} );
			setIsPaused( true );
		} catch ( pauseError ) {
			setError(
				__(
					'Failed to pause the queue. Please try again.',
					'wp-post-queue'
				)
			);
		}
	};

	const handleResume = useCallback( async () => {
		try {
			setIsLoading( true ); // Move this outside of the try block to ensure it's always called
			await saveSettings( {
				wp_queue_paused: false,
			} );

			// Ensure consistent hook usage
			setIsPaused( false );

			// Delay the page reload to allow for recalculations
			setTimeout( () => {
				setIsLoading( false );
				window.location.reload();
			}, 3000 ); // 3-second delay
		} catch ( resumeError ) {
			setError(
				__(
					'Failed to resume the queue. Please try again.',
					'wp-post-queue'
				)
			);
			setIsLoading( false );
		}
	}, [ saveSettings ] );

	const shuffleQueue = async () => {
		apiFetch( {
			path: '/wp-post-queue/v1/shuffle',
			method: 'POST',
		} )
			.then( ( response ) => {
				updateTable( response );
			} )
			.catch( ( shuffleError ) => {
				console.error( 'Error shuffling queue:', shuffleError );
			} );
	};

	const getTimezoneDisplay = ( timezone, gmtOffset ) => {
		if ( timezone && ! timezone.includes( 'Etc/GMT' ) ) {
			return timezone;
		}

		let tzstring = '';
		if ( ! timezone || timezone.includes( 'Etc/GMT' ) ) {
			const offset = parseFloat( gmtOffset );
			if ( offset === 0 ) {
				tzstring = 'UTC+0';
			} else if ( offset < 0 ) {
				tzstring = `UTC${ offset }`;
			} else {
				tzstring = `UTC+${ offset }`;
			}
		}

		return tzstring;
	};

	const getLocalDateTime = ( gmtOffset ) => {
		try {
			const date = new Date();
			const utcTime = date.getTime() + date.getTimezoneOffset() * 60000;
			const offsetInMilliseconds = parseFloat( gmtOffset ) * 3600 * 1000;
			const localTime = new Date( utcTime + offsetInMilliseconds );
			return localTime.toLocaleString();
		} catch ( _error ) {
			console.error( 'Error getting local date and time:', _error );
			return __( 'N/A', 'wp-post-queue' );
		}
	};

	return (
		<div className="settings-panel">
			<p>
				{ __(
					"The queue lets you stagger posts over a period of hours or days. It's an easy way to keep your blog active and consistent.",
					'wp-post-queue'
				) }
			</p>

			<div className="settings-controls">
				<SelectControl
					label={ _x(
						'Automatically publish a queued post',
						'Part of the sentence: "Automatically publish a queued post ___ times a day between ____ and ____"',
						'wp-post-queue'
					) }
					value={ localPublishTimes }
					options={ Array.from( { length: 50 }, ( _, i ) => ( {
						label: ( i + 1 ).toString(),
						value: ( i + 1 ).toString(),
					} ) ) }
					onChange={ handlePublishTimesChange }
				/>
				<SelectControl
					label={ _x(
						'times a day between',
						'Part of the sentence: "Automatically publish a queued post ___ times a day between ____ and ____"',
						'wp-post-queue'
					) }
					value={ localStartTime }
					options={ [
						'12 am',
						'1 am',
						'2 am',
						'3 am',
						'4 am',
						'5 am',
						'6 am',
						'7 am',
						'8 am',
						'9 am',
						'10 am',
						'11 am',
						'12 pm',
						'1 pm',
						'2 pm',
						'3 pm',
						'4 pm',
						'5 pm',
						'6 pm',
						'7 pm',
						'8 pm',
						'9 pm',
						'10 pm',
						'11 pm',
					].map( ( time ) => ( { label: time, value: time } ) ) }
					onChange={ handleStartTimeChange }
				/>
				<SelectControl
					label={ _x(
						'and',
						'Part of the sentence: "Automatically publish a queued post ___ times a day between ____ and ____"',
						'wp-post-queue'
					) }
					value={ localEndTime }
					options={ [
						'1 am',
						'2 am',
						'3 am',
						'4 am',
						'5 am',
						'6 am',
						'7 am',
						'8 am',
						'9 am',
						'10 am',
						'11 am',
						'12 pm',
						'1 pm',
						'2 pm',
						'3 pm',
						'4 pm',
						'5 pm',
						'6 pm',
						'7 pm',
						'8 pm',
						'9 pm',
						'10 pm',
						'11 pm',
						'12 am',
					].map( ( time ) => ( { label: time, value: time } ) ) }
					onChange={ handleEndTimeChange }
				/>
			</div>
			{ error && (
				<Notice
					status="error"
					isDismissible={ false }
					className="error-notice"
				>
					{ error }
				</Notice>
			) }

			<p>
				{ __( 'Timezone:', 'wp-post-queue' ) }{ ' ' }
				{ getTimezoneDisplay(
					wpQueuePluginData.timezone,
					wpQueuePluginData.gmtOffset
				) }{ ' ' }
				(
				<a href={ wpQueuePluginData.settingsUrl }>
					{ __( 'change', 'wp-post-queue' ) }
				</a>
				)
				<br />
				{ __( 'Local Time:', 'wp-post-queue' ) }{ ' ' }
				{ getLocalDateTime( wpQueuePluginData.gmtOffset ) }
			</p>
			<div className="settings-actions">
				<Button isSecondary onClick={ shuffleQueue }>
					{ __( 'Shuffle Queue', 'wp-post-queue' ) }
				</Button>
				{ ! isPaused && (
					<Button isSecondary onClick={ handlePause }>
						{ __( 'Pause Queue', 'wp-post-queue' ) }
					</Button>
				) }
				{ isDirty && (
					<Button isPrimary onClick={ handleSave }>
						{ __( 'Save Changes', 'wp-post-queue' ) }
					</Button>
				) }
				{ isLoading && <Spinner /> }
			</div>
			{ isPaused && (
				<Notice
					status="warning"
					isDismissible={ false }
					className="paused-notice"
				>
					<p>
						{ __(
							'Your queue is currently paused. No queued posts will be published at this time. Scheduled posts, however, will still be published. Once the queue is un-paused, queued posts will be re-calculated and resume publication.',
							'wp-post-queue'
						) }
					</p>
					<Button isPrimary onClick={ handleResume }>
						{ __( 'Resume Queue', 'wp-post-queue' ) }
					</Button>
				</Notice>
			) }
		</div>
	);
};

export const ConnectedSettingsPanel = compose(
	withSelect( ( select ) => ( {
		settings: select( 'wp-post-queue/store' ).getSettings(),
	} ) ),
	withDispatch( ( dispatch ) => ( {
		saveSettings: ( settings ) => {
			return dispatch( 'wp-post-queue/store' ).saveSettings( settings );
		},
	} ) )
)( SettingsPanel );

const rootElement = document.getElementById( 'queue-settings-panel' );
if ( rootElement ) {
	const root = createRoot( rootElement );
	root.render( <ConnectedSettingsPanel /> );
} else {
	console.error( 'Failed to find the root element for the settings panel.' );
}
