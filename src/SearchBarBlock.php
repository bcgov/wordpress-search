<?php
/**
 * Helpers for the Search Bar block render template.
 *
 * @package WordpressSearchWordPressPlugin
 */

namespace Bcgov\WordpressSearch;

/**
 * Static helpers to keep `Blocks/.../Search/render.php` minimal.
 */
final class SearchBarBlock {

	/**
	 * Attributes for render logic: prefer WP_Block’s merged attrs (defaults + block.json) when present.
	 *
	 * @param array|null     $attributes Passed to the render template (same as block attrs when `$block` is a WP_Block).
	 * @param \WP_Block|null $block      Block instance, if any.
	 */
	public static function normalized_attributes( $attributes, $block ): array {
		if ( $block instanceof \WP_Block ) {
			return $block->attributes;
		}

		return wp_parse_args( is_array( $attributes ) ? $attributes : array(), array( 'className' => '' ) );
	}

	/**
	 * Outline style from block.json `styles` (`is-style-outline` on className) or legacy `buttonStyle`.
	 *
	 * @param array $attributes Normalized attributes (see `normalized_attributes()`).
	 */
	private static function is_outline( array $attributes ): bool {
		$class = isset( $attributes['className'] ) ? (string) $attributes['className'] : '';
		if ( '' !== $class && 1 === preg_match( '/\bis-style-outline\b/', $class ) ) {
			return true;
		}

		return isset( $attributes['buttonStyle'] ) && 'outline' === $attributes['buttonStyle'];
	}

	/**
	 * Submit button `class` value: align with core Button / Search patterns — `wp-block-button__link`,
	 * `wp-element-button` (global `styles.elements.button`), BEM modifiers, optional `is-style-outline`.
	 *
	 * @param array $attributes Normalized attributes.
	 */
	public static function submit_button_class( array $attributes ): string {
		$outline = self::is_outline( $attributes );
		$extra   = $outline
			? array( 'dswp-search-bar__button--outline', 'is-style-outline' )
			: array( 'dswp-search-bar__button--fill' );

		return implode(
			' ',
			array_merge(
				array(
					'dswp-search-bar__button',
					'wp-element-button',
					'wp-block-button__link',
				),
				$extra
			)
		);
	}

	/**
	 * Current URL query params to preserve as hidden inputs (excluding search/pagination keys).
	 */
	public static function preserved_query_params(): array {
		$params     = array();
		$parsed     = wp_parse_url( add_query_arg( null, null ) );
		$url_params = array();
		if ( ! empty( $parsed['query'] ) ) {
			parse_str( $parsed['query'], $url_params );
		}

		$excluded = array( 's', 'paged', 'posts_per_page' );
		foreach ( $url_params as $key => $value ) {
			if ( in_array( $key, $excluded, true ) || empty( $value ) ) {
				continue;
			}
			$sanitized_key = sanitize_key( $key );
			if ( is_array( $value ) ) {
				$sanitized_values = array_filter( array_map( 'sanitize_text_field', $value ) );
				if ( ! empty( $sanitized_values ) ) {
					$params[ $sanitized_key ] = $sanitized_values;
				}
			} else {
				$params[ $sanitized_key ] = sanitize_text_field( $value );
			}
		}

		return apply_filters( 'wordpress_search_filter_parameters', $params );
	}
}
