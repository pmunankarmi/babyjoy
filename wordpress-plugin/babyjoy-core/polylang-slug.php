<?php
/**
 * Shared translated slugs, adapted from the user-supplied Polylang Slug 0.2.3.
 * Copyright Ulrich Pogson. GPL-2.0-or-later.
 * https://github.com/grappler/polylang-slug
 * Integration reused from SAB: guard duplicate loading in the caller; load after Polylang; preserve reserved slugs and check SQL parsing.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Duplicate loading is guarded by BabyJoy Core before this file is included.

// Built using code from: https://wordpress.org/support/topic/plugin-polylang-identical-page-names-in-different-languages?replies=8#post-2669927

// Check if PLL exists & the minimum version is correct.
if ( ! defined( 'POLYLANG_VERSION' ) || version_compare( POLYLANG_VERSION, '1.7', '<=' ) || version_compare( $GLOBALS[ 'wp_version' ], '4.0', '<=' ) ) {
	add_action( 'admin_notices', 'polylang_slug_admin_notices' );
	return;
}

/**
 * Minimum version admin notice.
 *
 * @since 0.2.0
 */
function polylang_slug_admin_notices() {
	echo '<div class="error"><p>' . __( 'Polylang Slug requires at the minimum Polylang v1.7 and WordPress 4.0', 'polylang-slug') . '</p></div>';
}

/**
 * Checks if the slug is unique within language.
 *
 * @since 0.1.0
 *
 * @global  wpdb  $wpdb        WordPress database abstraction object.
 *
 * @param  string $slug        The desired slug (post_name).
 * @param  int    $post_ID     Post ID.
 * @param  string $post_status No uniqueness checks are made if the post is still draft or pending.
 * @param  string $post_type   Post type.
 * @param  int    $post_parent Post parent ID.
 *
 * @return string              Unique slug for the post within language, based on $post_name (with a -1, -2, etc. suffix).
 */
function polylang_slug_unique_slug_in_language( $slug, $post_ID, $post_status, $post_type, $post_parent, $original_slug ) {

	// Return slug if it was not changed.
	if ( $original_slug === $slug ) {
		return $slug;
	}

	global $wpdb, $wp_rewrite;

	// Keep WordPress restrictions for media, feeds and pagination routes.
	$reserved_slugs = array_merge( (array) $wp_rewrite->feeds, array( 'embed' ) );
	$is_number = preg_match( '/^[0-9]+$/', $original_slug );
	$is_pagination = preg_match( '/^' . preg_quote( $wp_rewrite->pagination_base, '/' ) . '[0-9]+$/', $original_slug );

	if ( 'attachment' === $post_type || in_array( $original_slug, $reserved_slugs, true ) || $is_number || $is_pagination ) {
		return $slug;
	}

	// Only allow duplication when the URL identifies a translated language.
	$lang = pll_get_post_language( $post_ID );
	$options = get_option( 'polylang' );

	if ( empty( $lang ) || 0 === (int) ( $options['force_lang'] ?? 0 ) || ! pll_is_translated_post_type( $post_type ) ) {
		return $slug;
	}

	if ( is_post_type_hierarchical( $post_type ) ) {
		$check_sql = "SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type IN ( %s, 'attachment' ) AND ID != %d AND post_parent = %d";
		$candidate_ids = $wpdb->get_col( $wpdb->prepare( $check_sql, $original_slug, $post_type, $post_ID, $post_parent ) );
	} else {
		$check_sql = "SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type = %s AND ID != %d";
		$candidate_ids = $wpdb->get_col( $wpdb->prepare( $check_sql, $original_slug, $post_type, $post_ID ) );
	}

	// Public Polylang APIs are stable across versions; its internal SQL aliases are not.
	foreach ( $candidate_ids as $candidate_id ) {
		$candidate_id = (int) $candidate_id;

		if ( 'attachment' === get_post_type( $candidate_id ) ) {
			return $slug;
		}

		$candidate_lang = pll_get_post_language( $candidate_id );

		// An untranslated collision or one in the same language must remain unique.
		if ( empty( $candidate_lang ) || $candidate_lang === $lang ) {
			return $slug;
		}
	}

	return $original_slug;
}
add_filter( 'wp_unique_post_slug', 'polylang_slug_unique_slug_in_language', 10, 6 );

/**
 * Modify the sql query to include checks for the current language.
 *
 * @since 0.1.0
 *
 * @global wpdb   $wpdb  WordPress database abstraction object.
 *
 * @param  string $query Database query.
 *
 * @return string        The modified query.
 */
function polylang_slug_filter_queries( $query ) {
	global $wpdb;

	// Query for posts page, pages, attachments and hierarchical CPT. This is the only possible place to make the change. The SQL query is set in get_page_by_path()
	$is_pages_sql = preg_match(
		"#SELECT ID, post_name, post_parent, post_type FROM {$wpdb->posts} .*#",
		polylang_slug_standardize_query( $query ),
		$matches
	);

	// WooCommerce products are intentionally shared records without a language term.
	if ( preg_match("/post_type\s+(?:IN\s*\(|=)\s*'product'/i", $query ) ) {
		return $query;
	}
	if ( ! $is_pages_sql ) {
		return $query;
	}

	// This hook receives SQL, not a WP_Query object.
	if ( ! polylang_slug_should_run() ) {
		return $query;
	}

	$lang = pll_current_language();
	$join_clause  = polylang_slug_model_post_join_clause();
	$where_clause = polylang_slug_model_post_where_clause( $lang );

	$matched = preg_match(
		"#(SELECT .* (?=FROM))(FROM .* (?=WHERE))(?:(WHERE .*(?=ORDER))|(WHERE .*$))(.*)#",
		polylang_slug_standardize_query( $query ),
		$matches
	);

	if ( ! $matched || count( $matches ) < 5 ) {
		return $query;
	}

	// Keep the SELECT, FROM and WHERE fragments in their original order.
	$matches = array_values( $matches );

	// Add Polylang's language constraint without changing the original lookup.
	$sql_query = $matches[1] . $matches[2] . $join_clause . $matches[3] . $where_clause . $matches[4];

	/**
	 * Disable front end query modification.
	 *
	 * Allows disabling front end query modification if not needed.
	 *
	 * @since 0.2.0
	 *
	 * @param string $sql_query    Database query.
	 * @param array  $matches {
	 *     @type string $matches[1] SELECT SQL Query.
	 *     @type string $matches[2] FROM SQL Query.
	 *     @type string $matches[3] WHERE SQL Query.
	 *     @type string $matches[4] End of SQL Query (Possibly ORDER BY).
	 * }
	 * @param string $join_clause  INNER JOIN Polylang clause.
	 * @param string $where_clause Additional Polylang WHERE clause.
	 */
	return apply_filters( 'polylang_slug_sql_query', $sql_query, $matches, $join_clause, $where_clause );
}
add_filter( 'query', 'polylang_slug_filter_queries' );

/**
 * Resolve page paths again when WordPress cached the other translation.
 *
 * The core get_page_by_path() cache key does not contain a language. Its cached
 * result can therefore be English even when the next query requests Arabic.
 * Query by slug and language, then verify the full parent path before using it.
 */
function polylang_slug_resolve_page_query( $query ) {
	if ( ! polylang_slug_should_run( $query ) || ! $query->get( 'pagename' ) ) {
		return;
	}

	$language = $query->get( 'lang' ) ?: pll_current_language();
	$path = trim( urldecode( $query->get( 'pagename' ) ), '/' );
	$pages = get_posts( array(
		'post_type'        => 'page',
		'post_status'      => 'publish',
		'name'             => basename( $path ),
		'lang'             => $language,
		'numberposts'      => -1,
		'suppress_filters' => false,
	) );

	foreach ( $pages as $page ) {
		if ( urldecode( get_page_uri( $page ) ) === $path ) {
			$query->queried_object = $page;
			$query->queried_object_id = $page->ID;
			return;
		}
	}
}
add_action( 'parse_query', 'polylang_slug_resolve_page_query', 100 );

/**
 * Extend the WHERE clause of the query.
 *
 * This allows the query to return only the posts of the current language
 *
 * @since 0.1.0
 *
 * @param  string   $where The WHERE clause of the query.
 * @param  WP_Query $query The WP_Query instance (passed by reference).
 *
 * @return string          The WHERE clause of the query.
 */
function polylang_slug_posts_where_filter( $where, $query ) {
	// Check whether this request needs language filtering.
	if ( ! polylang_slug_should_run( $query ) ) {
		return $where;
	}

	$lang = empty( $query->query['lang'] ) ? pll_current_language() : $query->query['lang'];

	$where .= polylang_slug_model_post_where_clause( $lang  );

	return $where;
}
add_filter( 'posts_where', 'polylang_slug_posts_where_filter', 10, 2 );

/**
 * Extend the JOIN clause of the query.
 *
 * This allows the query to return only the posts of the current language
 *
 * @since 0.1.0
 *
 * @param  string   $join  The JOIN clause of the query.
 * @param  WP_Query $query The WP_Query instance (passed by reference).
 *
 * @return string          The JOIN clause of the query.
 */
function polylang_slug_posts_join_filter( $join, $query ) {

	// Check whether this request needs language filtering.
	if ( ! polylang_slug_should_run( $query ) ) {
		return $join;
	}

	$join .= polylang_slug_model_post_join_clause();

	return $join;
}
add_filter( 'posts_join', 'polylang_slug_posts_join_filter', 10, 2 );

/**
 * Check if the query needs to be adapted.
 *
 * @since 0.2.0
 *
 * @param  WP_Query $query The WP_Query instance (passed by reference).
 *
 * @return bool
 */
function polylang_slug_should_run( $query = '' ) {

	/**
	 * Disable front end query modification.
	 *
	 * Allows disabling front end query modification if not needed.
	 *
	 * @since 0.2.0
	 *
	 * @param bool     false  Not disabling run.
	 * @param WP_Query $query The WP_Query instance (passed by reference).
	 */

	// Do not run in admin or if Polylang is disabled
	$disable = apply_filters( 'polylang_slug_disable', false, $query );
	if ( is_admin() || is_feed() || ! function_exists( 'pll_current_language' ) || empty( PLL()->options['force_lang'] ) || $disable ) {
		return false;
	}
	// The lang query should be defined if the URL contains the language
	$lang          = empty( $query->query['lang'] ) ? pll_current_language() : $query->query['lang'];
	// Checks if the post type is translated when doing a custom query with the post type defined
	$is_translated = ! empty( $query->query['post_type'] ) && ! pll_is_translated_post_type( $query->query['post_type'] );

	return ! ( empty( $lang ) || $is_translated );
}

/**
 * Standardize the query.
 *
 * This makes the standardized and simpler to run regex on
 *
 * @since 0.2.0
 *
 * @param  string $query Database query.
 *
 * @return string        The standardized query.
 */
function polylang_slug_standardize_query( $query ) {
	// Strip tabs, newlines and multiple spaces.
	$query = str_replace(
		array( "\t", " \n", "\n", " \r", "\r", "   ", "  " ),
		array( '', ' ', ' ', ' ', ' ', ' ', ' ' ),
		$query
	);
	return trim( $query );
}

/**
 * Fetch the polylang join clause.
 *
 * @since 0.2.0
 *
 * @return string
 */
function polylang_slug_model_post_join_clause() {
	if ( function_exists( 'PLL' ) ) {
		return PLL()->model->post->join_clause();
	} elseif ( array_key_exists( 'polylang', $GLOBALS ) ) {
		global $polylang;
		return $polylang->model->join_clause( 'post' );
	}
	return '';
}
/**
 * Fetch the polylang where clause.
 *
 * @since 0.2.0
 *
 * @param  string $lang The current language slug.
 *
 * @return string
 */
function polylang_slug_model_post_where_clause( $lang = '' ) {
	if ( function_exists( 'PLL' ) ) {
		return PLL()->model->post->where_clause( $lang );
	} elseif ( array_key_exists( 'polylang', $GLOBALS ) ) {
		global $polylang;
		return $polylang->model->where_clause( $lang, 'post' );
	}
	return '';
}
