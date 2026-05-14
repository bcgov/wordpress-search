<?php
/**
 * Search Post Type Filter Block - Frontend Render
 *
 * @package SearchPlugin
 * @subpackage SearchPosTypetFilter
 */

namespace Bcgov\WordpressSearch\SearchPosTypetFilter;

$selected_post_types = $attributes['selectedPostTypes'] ?? [];
$underline_color     = $attributes['underlineColor'] ?? 'var(--wp--preset--color--bar-color)';

$excluded = array(
	'attachment',
	'revision',
	'nav_menu_item',
	'custom_css',
	'customize_changeset',
	'oembed_cache',
	'user_request',
	'wp_block',
	'wp_template',
	'wp_template_part',
	'wp_navigation',
	'wp_font_face',
	'wp_font_family',
	'wp_global_styles',
);

$all_post_types = array();
foreach ( get_post_types( array(), 'objects' ) as $slug => $obj ) {
	if ( ! in_array( $slug, $excluded, true ) ) {
		$all_post_types[ $slug ] = $obj;
	}
}

$post_types = array();
if ( empty( $selected_post_types ) ) {
	$post_types = $all_post_types;
} else {
	foreach ( $selected_post_types as $slug ) {
		if ( isset( $all_post_types[ $slug ] ) ) {
			$post_types[ $slug ] = $all_post_types[ $slug ];
		}
	}
}

if ( empty( $post_types ) ) {
	return;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$base = remove_query_arg( 'post_type' );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
foreach ( $_GET as $key => $value ) {
	if ( 'post_type' === $key ) {
		continue;
	}
	$sk = sanitize_key( $key );
	if ( is_array( $value ) ) {
		$sv = array_filter( array_map( 'sanitize_text_field', $value ) );
		if ( $sv ) {
			$base = add_query_arg( $sk, $sv, $base );
		}
	} else {
		$base = add_query_arg( $sk, sanitize_text_field( (string) $value ), $base );
	}
}

$req = array();
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( isset( $_GET['post_type'] ) ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$raw = $_GET['post_type'];
	if ( is_array( $raw ) ) {
		foreach ( $raw as $slug ) {
			$slug = sanitize_key( sanitize_text_field( (string) $slug ) );
			if ( '' !== $slug ) {
				$req[] = $slug;
			}
		}
		$req = array_values( array_unique( $req ) );
	} else {
		$one = sanitize_key( sanitize_text_field( (string) $raw ) );
		if ( '' !== $one ) {
			$req[] = $one;
		}
	}
}

$all_active = empty( $req );
?>

<div class="wp-block-wordpress-search-search-post-type-filter">
	<div class="dswp-search-post-type-filter__container" style="--underline-color: <?php echo esc_attr( $underline_color ); ?>;">
		<?php
		$all_class = 'dswp-search-post-type-filter__button';
		if ( $all_active ) {
			$all_class .= ' dswp-search-post-type-filter__button--active';
		}
		?>
		<a href="<?php echo esc_url( $base ); ?>" class="<?php echo esc_attr( $all_class ); ?>">
			<span class="dswp-search-post-type-filter__text"><?php echo esc_html( __( 'All', 'wordpress-search' ) ); ?></span>
		</a>
		<?php
		foreach ( $post_types as $post_type_item ) :
			$is_active    = in_array( $post_type_item->name, $req, true );
			$button_class = 'dswp-search-post-type-filter__button';
			if ( $is_active ) {
				$button_class .= ' dswp-search-post-type-filter__button--active';
			}
			$href = $is_active ? $base : add_query_arg( 'post_type', $post_type_item->name, $base );
			?>
			<a href="<?php echo esc_url( $href ); ?>" class="<?php echo esc_attr( $button_class ); ?>">
				<span class="dswp-search-post-type-filter__text"><?php echo esc_html( $post_type_item->labels->name ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
</div>
