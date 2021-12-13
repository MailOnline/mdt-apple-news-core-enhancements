/**
 * WordPress dependencies
 */
import { registerPlugin } from '@wordpress/plugins';

/**
 * Internal dependencies
 */
import render from './publish-to-apple-news'

registerPlugin( 'mdt-publish-apple-news', { render } );

