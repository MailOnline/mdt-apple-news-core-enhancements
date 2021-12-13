/**
 * WordPress dependencies
 */

import { PluginPostStatusInfo } from '@wordpress/edit-post'
import { FormToggle } from '@wordpress/components'
import { select, withSelect, withDispatch } from '@wordpress/data'
import { compose, withInstanceId } from '@wordpress/compose'

const REST_FIELD = 'publish_to_apple_news'

/**
 * Returns the current version of the publish_to_apple_news rest field
 *
 * @returns {boolean}
 */
const getMeta = () => {
	const { getEditedPostAttribute } = select( 'core/editor' )
	const meta = getEditedPostAttribute( REST_FIELD )

	if ( typeof(meta) === 'boolean' ) {
		return meta
	}
	return true
}

/**
 * The JSX that displays the 'Auto-publish to Apple News' checkbox
 *
 * @param autoPublish boolean
 * @param onChange function
 * @returns {*} JSX
 */
const autoPublishOutput = ( {autoPublish, onChange} ) => {
	return(
		<PluginPostStatusInfo>
			<label htmlFor="auto-publish-apple-news">Publish to Apple News</label>
			<FormToggle
				checked={ autoPublish }
				onChange={ () => onChange( ! autoPublish ) }
				id="auto-publish-apple-news"
			/>
		</PluginPostStatusInfo>
	)
}

/**
 * Wrapping our output method in the needed select/dispatch higher order components
 */
const render = compose(
	withSelect( () => {
		return {
			autoPublish: getMeta(),
		};
	} ),
	withDispatch( ( dispatch ) => {

		return {
			onChange: ( autoPublish ) => dispatch( 'core/editor' ).editPost( { [REST_FIELD]: autoPublish } ),
		};
	} ),
	withInstanceId,
)( autoPublishOutput );

export default render
