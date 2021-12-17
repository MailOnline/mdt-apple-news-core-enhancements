<?php

namespace MDT\Apple_News_Core_Enhancements\Components;

/**
 * Class Tiktok
 *
 * @package Metro\Apple_News\Components
 */
class Tiktok extends \Apple_Exporter\Components\Component {

	/**
	 * Find Tiktok markup
	 *
	 * @param \DOMElement $node The node to examine for matches.
	 * @access public
	 * @return \DOMElement|null The node on success, or null on no match.
	 */
	public static function node_matches( $node ) {

		// Search for default Gutenberg class for TikTok embeds
		if ( 'figure' === $node->nodeName
			&& false !== strpos( $node->getAttribute( 'class' ), 'wp-block-embed-tiktok' )
		) {
			return $node;
		}

		return null;
	}

	/**
	 * Register spec
	 *
	 * @access public
	 */
	public function register_specs() {

		$spec = [
			'role' => 'tiktok',
			'URL'  => '#src#',
		];

		$this->register_spec(
			'tiktok-json',
			'TikTok',
			$spec
		);

	}

	/**
	 * Build the component.
	 *
	 * @param string $html The HTML to parse into text for processing.
	 * @access protected
	 */
	protected function build( $html ) {
		// Take out $html string representing the link and turn it back into a DOMDocument object for parsing.
		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
		libxml_clear_errors( true );
		$container = $dom->getElementsByTagName( 'body' )->item( 0 )->childNodes->item( 0 );

		$src = '';

		if ( $container->getElementsByTagName( 'div' )->item( 0 ) ) {
			$blockquote = $container->getElementsByTagName( 'blockquote' )->item( 0 );

			if ( ! $blockquote || ! $blockquote->hasAttribute( 'cite' ) ) {
				return;
			}

			$src = $blockquote->getAttribute( 'cite' );
		}

		if ( ! $src ) {
			return;
		}

		$this->register_json(
			'tiktok-json',
			[
				'#src#' => $src,
			]
		);
	}
}
