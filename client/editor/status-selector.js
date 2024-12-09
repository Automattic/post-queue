import { compose } from '@wordpress/compose';
import { select, withDispatch, withSelect } from '@wordpress/data';
import { useCallback, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';

const QueueEditorPanel = ( { onUpdateQueuedStatus } ) => {
	const currentStatusRef = useRef(
		select( 'core/editor' ).getEditedPostAttribute( 'status' )
	);

	const addQueueStatusOption = useCallback( () => {
		const statusSelect = document.querySelector(
			'.editor-change-status__content'
		);
		if (
			statusSelect &&
			! statusSelect.querySelector( 'input[value="queued"]' )
		) {
			const options = statusSelect.querySelectorAll(
				'.components-radio-control__option'
			);
			options.forEach( ( option ) => {
				const input = option.querySelector( 'input' );
				if ( input && input.value !== 'queued' ) {
					input.addEventListener( 'click', () => {
						const queuedInput = statusSelect.querySelector(
							'input[value="queued"]'
						);
						if ( queuedInput ) {
							queuedInput.checked = false;
							onUpdateQueuedStatus( false, input.value );
						}
					} );
				}
			} );

			const lastOption = options[ options.length - 1 ];
			if ( lastOption ) {
				const queuedOption = lastOption.cloneNode( true );
				const input = queuedOption.querySelector( 'input' );
				const label = queuedOption.querySelector( 'label' );
				const description =
					queuedOption.querySelector( 'span' ) ||
					queuedOption.querySelector( 'p' );

				input.id = `inspector-radio-control-0-${ options.length }`;
				input.value = 'queued';
				label.setAttribute( 'for', input.id );
				label.childNodes[ 0 ].nodeValue = __( 'Queued', 'post-queue' );
				description.textContent = __(
					'Added to your publishing queue.',
					'post-queue'
				);
				input.checked = currentStatusRef.current === 'queued';
				input.removeAttribute( 'name' );

				input.addEventListener( 'change', ( event ) => {
					onUpdateQueuedStatus( event.target.checked );
				} );

				lastOption.parentNode.appendChild( queuedOption );

				return () => {
					input.removeEventListener( 'change', onUpdateQueuedStatus );
				};
			}
		}
	}, [ onUpdateQueuedStatus ] );

	useEffect( () => {
		/* global MutationObserver */
		const bodyObserver = new MutationObserver( () => {
			if ( document.querySelector( '.editor-change-status__content' ) ) {
				const cleanup = addQueueStatusOption();
				return () => cleanup && cleanup();
			}
		} );

		bodyObserver.observe( document.body, {
			childList: true,
			subtree: true,
		} );

		return () => {
			bodyObserver.disconnect();
		};
	}, [ addQueueStatusOption ] );

	const updateStatusLabel = useCallback( () => {
		const postStatusButton = document.querySelector(
			'.editor-post-status button'
		);
		if ( ! postStatusButton ) {
			return;
		}

		const postStatus =
			select( 'core/editor' ).getEditedPostAttribute( 'status' );
		const isPostQueued = postStatus === 'queued';
		const needsUpdate = postStatus !== currentStatusRef.current;

		if (
			needsUpdate ||
			( postStatusButton.textContent === '' &&
				currentStatusRef.current === 'queued' )
		) {
			currentStatusRef.current = postStatus;
			if ( isPostQueued ) {
				setTimeout( () => {
					postStatusButton.textContent = __( 'Queued', 'post-queue' );
				}, 100 );
			} else {
				postStatusButton.textContent = '';
			}
		}
	}, [] );

	useEffect( () => {
		const { subscribe } = wp.data;
		updateStatusLabel();
		const unsubscribe = subscribe( updateStatusLabel );
		return () => unsubscribe();
	}, [ updateStatusLabel ] );
};

const mapSelectToProps = ( _select ) => ( {
	isQueued:
		_select( 'core/editor' ).getEditedPostAttribute( 'status' ) ===
		'queued',
} );

const mapDispatchToProps = ( _dispatch ) => ( {
	onUpdateQueuedStatus: ( isQueued, status = 'draft' ) => {
		_dispatch( 'core/editor' ).editPost( {
			status: isQueued ? 'queued' : status,
		} );
	},
} );

const EnhancedQueuePluginPanel = compose(
	withSelect( mapSelectToProps ),
	withDispatch( mapDispatchToProps )
)( QueueEditorPanel );

registerPlugin( 'post-queue', {
	render: EnhancedQueuePluginPanel,
	icon: 'calendar',
} );
