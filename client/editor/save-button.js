import { compose, useViewportMatch } from '@wordpress/compose';
import { dispatch, withDispatch, withSelect } from '@wordpress/data';
import { PluginSidebar } from '@wordpress/edit-post';
import { store as editorStore } from '@wordpress/editor';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import useInterceptPluginSidebar from './hooks/use-intercept-plugin-sidebar';
import './index.css';

export const pluginName = 'wp-post-queue-save';
export const sidebarName = 'wp-post-queue-sidebar';

const CustomInnerSaveButton = ( {
	buttonText,
	isSavingPost,
	isDisabled,
	isTinyViewport,
} ) => {
	const classNames = `wp-post-queue-save-button ${
		isSavingPost ? 'is-busy' : ''
	} ${ isDisabled ? 'is-disabled' : '' } ${
		isTinyViewport ? 'is-tiny' : ''
	}`;
	return <div className={ classNames }>{ buttonText }</div>;
};

const CustomSaveButton = ( {
	postType,
	savedStatus,
	editedStatus,
	isUnsavedPost,
	isSavingPost,
} ) => {
	const [ wasQueued, setWasQueued ] = useState( false );
	const isTinyViewport = useViewportMatch( 'small', '<' );
	const isCustomSaveButtonVisible = useMemo(
		() =>
			isCustomSaveButtonEnabled(
				isUnsavedPost,
				postType,
				savedStatus,
				editedStatus
			),
		[ isUnsavedPost, postType, savedStatus, editedStatus ]
	);
	const isCustomSaveButtonDisabled = isSavingPost;

	useEffect( () => {
		const updateButtonText = () => {
			const publishButton = document.querySelector(
				'.editor-post-publish-button__button'
			);
			if ( publishButton ) {
				if ( isUnsavedPost && editedStatus === 'queued' ) {
					publishButton.textContent = __( 'Queue', 'post-queue' );
					setWasQueued( true );
				} else if (
					wasQueued &&
					isUnsavedPost &&
					( editedStatus === 'draft' || editedStatus === 'publish' )
				) {
					publishButton.textContent = __( 'Publish', 'post-queue' );
				} else if ( wasQueued ) {
					publishButton.textContent = __( 'Save', 'post-queue' );
				}
			}
		};

		updateButtonText();
	}, [ isUnsavedPost, editedStatus, wasQueued ] );

	useEffect( () => {
		const editor = document.querySelector( '#editor' );
		if ( isCustomSaveButtonVisible ) {
			editor.classList.add( 'disable-native-save-button' );
		} else {
			editor.classList.remove( 'disable-native-save-button' );
		}
	}, [ isCustomSaveButtonVisible ] );

	useInterceptPluginSidebar( `${ pluginName }/${ sidebarName }`, () => {
		if ( ! isCustomSaveButtonDisabled ) {
			dispatch( editorStore ).savePost();
		}
	} );

	const buttonText = __( 'Save', 'post-queue' );
	const InnerSaveButton = (
		<CustomInnerSaveButton
			buttonText={ buttonText }
			isSavingPost={ isSavingPost }
			isDisabled={ isCustomSaveButtonDisabled }
			isTinyViewport={ isTinyViewport }
		/>
	);

	return (
		<>
			{ isCustomSaveButtonVisible && (
				<PluginSidebar
					name={ sidebarName }
					title={ buttonText }
					icon={ InnerSaveButton }
				>
					{ null }
				</PluginSidebar>
			) }
		</>
	);
};

const isCustomSaveButtonEnabled = (
	isUnsavedPost,
	postType,
	statusSlug,
	editedStatus
) => {
	if ( isUnsavedPost ) {
		return false;
	}
	return (
		statusSlug === 'queued' ||
		( statusSlug !== 'queued' && editedStatus === 'queued' )
	);
};

const mapSelectProps = ( _select ) => {
	const {
		getEditedPostAttribute,
		getCurrentPostAttribute,
		isSavingPost,
		getCurrentPost,
		getCurrentPostType,
	} = _select( editorStore );
	const post = getCurrentPost();
	const isUnsavedPost = post?.status === 'auto-draft';

	return {
		savedStatus: getCurrentPostAttribute( 'status' ),
		editedStatus: getEditedPostAttribute( 'status' ),
		postType: getCurrentPostType(),
		isSavingPost: isSavingPost(),
		isUnsavedPost,
	};
};

const mapDispatchStatusToProps = ( _dispatch ) => ( {
	onUpdateStatus( status ) {
		_dispatch( editorStore ).editPost( { status }, { undoIgnore: true } );
	},
} );

registerPlugin( pluginName, {
	render: compose(
		withSelect( mapSelectProps ),
		withDispatch( mapDispatchStatusToProps )
	)( CustomSaveButton ),
} );
