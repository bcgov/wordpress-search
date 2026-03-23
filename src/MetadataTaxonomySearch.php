<?php
/**
 * MetadataTaxonomySearch Class
 *
 * Handles auto-discovery and search functionality for metadata and taxonomies.
 * Automatically finds and searches all custom fields and public taxonomies.
 *
 * @package SearchPlugin
 */

namespace Bcgov\WordpressSearch;

/**
 * MetadataTaxonomySearch class.
 */
class MetadataTaxonomySearch {

    /**
     * Cache key for metadata keys.
     */
    const CACHE_KEY_META = 'wordpress_search_meta_keys';

    /**
     * Cache key for taxonomies.
     */
    const CACHE_KEY_TAX = 'wordpress_search_taxonomies';

    /**
     * Cache duration in seconds (1 hour).
     */
    const CACHE_DURATION = 3600;

    /**
     * Maximum number of metadata keys to include.
     */
    const MAX_META_KEYS = 50;

    /**
     * Flag to track if hooks are added.
     *
     * @var bool
     */

    /**
     * Initialize the metadata and taxonomy search functionality.
     */
    public function init() {
        // Add auto-discovery search functionality.
        add_filter( 'posts_search', array( $this, 'custom_search_query' ), 10, 2 );

        // Always add join hooks for search queries so table aliases are available.
        add_filter( 'posts_join', array( $this, 'search_join_tables' ), 10, 2 );
        add_filter( 'posts_distinct', array( $this, 'search_distinct' ) );

        // Add relevance-based ordering when no explicit sort is set.
        // Use priority 30 to run after SearchResultsSort (which uses priority 20).
        add_filter( 'posts_orderby', array( $this, 'apply_relevance_ordering' ), 30, 2 );
        add_filter( 'posts_fields', array( $this, 'add_relevance_fields' ), 10, 2 );
        add_filter( 'posts_groupby', array( $this, 'add_groupby_for_relevance' ), 10, 2 );

        // Clear cache when metadata changes.
        add_action( 'added_post_meta', array( $this, 'clear_meta_cache' ) );
        add_action( 'updated_post_meta', array( $this, 'clear_meta_cache' ) );
        add_action( 'deleted_post_meta', array( $this, 'clear_meta_cache' ) );
    }

    /**
     * Custom search query that includes auto-discovered metadata and taxonomies.
     *
     * @param string    $search The search SQL.
     * @param \WP_Query $wp_query The WordPress query object.
     * @return string Modified search SQL.
     */
    public function custom_search_query( $search, $wp_query ) {
        // Only modify search queries and skip admin/ajax requests.
        if ( ! $wp_query->is_search() || is_admin() || wp_doing_ajax() ) {
            return $search;
        }

        // Skip if search query is empty.
        if ( empty( $wp_query->query_vars['s'] ) ) {
            return $search;
        }

        global $wpdb;

        $search_terms        = $wp_query->query_vars['s'];
        $terms_relation_type = apply_filters( 'wordpress_search_terms_relation_type', 'OR' );

        // Clean and prepare search terms, filtering out stop words.
        $terms = $this->filter_stop_words( $search_terms );
        if ( empty( $terms ) ) {
            return $search;
        }

        $search_clauses = array();

        foreach ( $terms as $term ) {
            $term      = $wpdb->esc_like( $term );
            $like_term = '%' . $term . '%';

            $term_clauses = array();

            // Search in post title.
            $term_clauses[] = $wpdb->prepare( "($wpdb->posts.post_title LIKE %s)", $like_term );

            // Search in post content.
            $term_clauses[] = $wpdb->prepare( "($wpdb->posts.post_content LIKE %s)", $like_term );

            // Search in post excerpt.
            $term_clauses[] = $wpdb->prepare( "($wpdb->posts.post_excerpt LIKE %s)", $like_term );

            // Add metadata search clauses.
            $meta_clauses = $this->build_metadata_clauses( $like_term );
            if ( ! empty( $meta_clauses ) ) {
                $term_clauses = array_merge( $term_clauses, $meta_clauses );
            }

            // Add taxonomy search clauses.
            $tax_clauses = $this->build_taxonomy_clauses( $like_term );
            if ( ! empty( $tax_clauses ) ) {
                $term_clauses = array_merge( $term_clauses, $tax_clauses );
            }

            if ( ! empty( $term_clauses ) ) {
                $search_clauses[] = '(' . implode( ' OR ', $term_clauses ) . ')';
            }
        }

        if ( ! empty( $search_clauses ) ) {
            $search = ' AND (' . implode( " $terms_relation_type ", $search_clauses ) . ')';

            if ( ! is_user_logged_in() ) {
                $search .= " AND ($wpdb->posts.post_password = '')";
            }
        }

        /**
         * Filter search query return by plugin.
         *
         * @param string $search SQL query.
         * @param object $wp_query global wp_query object.
         */
        return apply_filters( 'wordpress_search_posts_search', $search, $wp_query );
    }

    /**
     * Build metadata search clauses.
     *
     * @param string $like_term Prepared LIKE term.
     * @return array Array of SQL clauses.
     */
    private function build_metadata_clauses( $like_term ) {
        global $wpdb;

        $all_meta_keys = $this->get_all_metadata_keys();
        if ( empty( $all_meta_keys ) ) {
            return array();
        }

        $clauses = array();
        foreach ( $all_meta_keys as $key_slug ) {
            $clauses[] = $wpdb->prepare( '(espm.meta_key = %s AND espm.meta_value LIKE %s)', $key_slug, $like_term );
        }

        return $clauses;
    }

    /**
     * Build taxonomy search clauses.
     *
     * @param string $like_term Prepared LIKE term.
     * @return array Array of SQL clauses.
     */
    private function build_taxonomy_clauses( $like_term ) {
        global $wpdb;

        $all_taxonomies = $this->get_all_searchable_taxonomies();
        if ( empty( $all_taxonomies ) ) {
            return array();
        }

        $clauses = array();
        foreach ( $all_taxonomies as $tax ) {
            $clauses[] = $wpdb->prepare( '(estt.taxonomy = %s AND est.name LIKE %s)', $tax, $like_term );
        }

        return $clauses;
    }


    /**
     * Join necessary tables for metadata and taxonomy search.
     *
     * @param string    $join The JOIN clause of the query.
     * @param \WP_Query $wp_query The WordPress query object.
     * @return string Modified JOIN clause.
     */
    public function search_join_tables( $join, $wp_query ) {
        global $wpdb;

        // Only add joins for search queries.
        if ( ! $wp_query->is_search() || is_admin() || wp_doing_ajax() ) {
            return $join;
        }

        // Skip if search query is empty.
        if ( empty( $wp_query->query_vars['s'] ) ) {
            return $join;
        }

        // Only add joins if we have data to search.
        $all_meta_keys = $this->get_all_metadata_keys();
        if ( ! empty( $all_meta_keys ) ) {
            $join .= " LEFT JOIN $wpdb->postmeta espm ON ($wpdb->posts.ID = espm.post_id)";
        }

        $all_taxonomies = $this->get_all_searchable_taxonomies();
        if ( ! empty( $all_taxonomies ) ) {
            $join .= " LEFT JOIN $wpdb->term_relationships estr ON ($wpdb->posts.ID = estr.object_id)";
            $join .= " LEFT JOIN $wpdb->term_taxonomy estt ON (estr.term_taxonomy_id = estt.term_taxonomy_id)";
            $join .= " LEFT JOIN $wpdb->terms est ON (estt.term_id = est.term_id)";
        }

        return $join;
    }

    /**
     * Make search results distinct.
     *
     * @return string
     */
    public function search_distinct() {
        return 'DISTINCT';
    }

    /**
     * Add GROUP BY clause to prevent duplicate results when multiple taxonomy/metadata matches exist.
     *
     * @param string    $groupby The GROUP BY clause.
     * @param \WP_Query $wp_query The WordPress query object.
     * @return string Modified GROUP BY clause.
     */
    public function add_groupby_for_relevance( $groupby, $wp_query ) {
        // Only modify search queries and skip admin/ajax requests.
        if ( ! $wp_query->is_search() || is_admin() || wp_doing_ajax() ) {
            return $groupby;
        }

        // Skip if search query is empty.
        if ( empty( $wp_query->query_vars['s'] ) ) {
            return $groupby;
        }

        global $wpdb;

        // Group by post ID to prevent duplicates when multiple taxonomy/metadata rows match.
        // This ensures each post appears only once, even if it has multiple matching taxonomy terms.
        if ( empty( $groupby ) ) {
            $groupby = "{$wpdb->posts}.ID";
        } elseif ( strpos( $groupby, "{$wpdb->posts}.ID" ) === false ) {
            // If GROUP BY already exists, ensure post ID is included.
            $groupby = "{$wpdb->posts}.ID, " . $groupby;
        }

        return $groupby;
    }

    /**
     * Filter out common stop words from search terms.
     *
     * @param string $search_string The search string to filter.
     * @return array Array of filtered search terms.
     */
    private function filter_stop_words( $search_string ) {
        // Common English stop words that shouldn't affect search ranking.
        $stop_words = apply_filters(
            'wordpress_search_stop_words',
            array(
                'a',
				'an',
				'and',
				'are',
				'as',
				'at',
				'be',
				'by',
				'for',
				'from',
                'has',
				'he',
				'in',
				'is',
				'it',
				'its',
				'of',
				'on',
				'or',
				'that',
				'the',
                'to',
				'was',
				'were',
				'will',
				'with',
				'this',
				'but',
				'they',
                'have',
				'had',
				'what',
				'said',
				'each',
				'which',
				'their',
				'time',
                'if',
				'up',
				'out',
				'many',
				'then',
				'them',
				'these',
				'so',
				'some',
                'her',
				'would',
				'make',
				'like',
				'into',
				'him',
				'has',
				'two',
				'more',
                'go',
				'no',
				'way',
				'could',
				'my',
				'than',
				'first',
				'been',
				'call',
                'who',
				'oil',
				'sit',
				'now',
				'find',
				'down',
				'day',
				'did',
				'get',
                'come',
				'made',
				'may',
				'part',
            )
        );

        // Convert to lowercase for case-insensitive matching.
        $stop_words = array_map( 'strtolower', $stop_words );

        // Split search string into terms and filter.
        $terms          = array_filter( array_map( 'trim', explode( ' ', $search_string ) ) );
        $filtered_terms = array();

        foreach ( $terms as $term ) {
            $term_lower = strtolower( trim( $term ) );
            // Only include terms that are not stop words and have at least 2 characters.
            if ( ! in_array( $term_lower, $stop_words, true ) && strlen( $term_lower ) >= 2 ) {
                $filtered_terms[] = $term;
            }
        }

        return $filtered_terms;
    }

    /**
     * Add relevance score fields to the SELECT clause.
     *
     * @param string    $fields The SELECT clause.
     * @param \WP_Query $wp_query The WordPress query object.
     * @return string Modified SELECT clause.
     */
    public function add_relevance_fields( $fields, $wp_query ) {
        // Only modify search queries and skip admin/ajax requests.
        if ( ! $wp_query->is_search() || is_admin() || wp_doing_ajax() ) {
            return $fields;
        }

        // Skip if search query is empty.
        if ( empty( $wp_query->query_vars['s'] ) ) {
            return $fields;
        }

        // Always add relevance fields for search queries (even if sorting is set).
        // Relevance will be primary sort, explicit sorts will be secondary.

        global $wpdb;

        $search_terms = $wp_query->query_vars['s'];
        $terms        = $this->filter_stop_words( $search_terms );

        if ( empty( $terms ) ) {
            return $fields;
        }

        // Build relevance score calculation.
        $relevance_score = $this->build_relevance_score( $terms, $search_terms );

        if ( ! empty( $relevance_score ) ) {
            $fields .= ', ' . $relevance_score . ' AS relevance_score';
        }

        return $fields;
    }

    /**
     * Apply relevance-based ordering to search results.
     *
     * @param string    $orderby The ORDER BY clause.
     * @param \WP_Query $wp_query The WordPress query object.
     * @return string Modified ORDER BY clause.
     */
    public function apply_relevance_ordering( $orderby, $wp_query ) {
        // Only modify search queries and skip admin/ajax requests.
        if ( ! $wp_query->is_search() || is_admin() || wp_doing_ajax() ) {
            return $orderby;
        }

        // Skip if search query is empty - let title sorting handle it instead.
        $search_query = $wp_query->query_vars['s'];
        $has_keyword  = ! empty( $search_query ) && trim( $search_query ) !== '';
        if ( ! $has_keyword ) {
            return $orderby;
        }

        // Skip if meta sorting was already applied by SearchResultsSort AND there's no search query.
        // If there's a search query, we still want relevance ordering to take precedence.
        $has_search_keywords = ! empty( $search_query ) && trim( $search_query ) !== '';
        if ( ! empty( $wp_query->get( 'wordpress_search_meta_sort_applied' ) ) && ! $has_search_keywords ) {
            return $orderby;
        }

        global $wpdb;

        // Check if relevance_score was added to fields.
        $fields = $wp_query->get( 'fields' );
        if ( ! empty( $fields ) && 'ids' === $fields ) {
            return $orderby;
        }

        // Check what sort option is selected.
        // Default to relevance when searching with keywords.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for public search result sorting.
        $sort_param = isset( $_GET['sort'] ) ? sanitize_text_field( $_GET['sort'] ) : 'relevance';

        // Handle explicit sort choices.
        if ( 'title_asc' === $sort_param || 'title_desc' === $sort_param ) {
            // User chose title sorting - use relevance as primary, title as secondary.
            if ( strpos( $orderby, 'relevance_score' ) === false ) {
                $title_order = ( 'title_asc' === $sort_param ) ? 'ASC' : 'DESC';
                $orderby     = 'relevance_score DESC, ' . $wpdb->posts . '.post_title ' . $title_order;
            }
            return $orderby;
        }

        // If user chose metadata sorting, let SearchResultsSort handle it completely.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for public search result sorting.
        if ( isset( $_GET['meta_sort'] ) || isset( $_GET['sort_meta'] ) ) {
            return $orderby;
        }

        // Default behavior: Use relevance ranking (this is what users expect from search).
        // Ensure relevance_score is ALWAYS first in ORDER BY to prioritize exact matches.
        if ( strpos( $orderby, 'relevance_score' ) === false ) {
            $orderby = 'relevance_score DESC' . ( empty( trim( $orderby ) ) ? ', ' . $wpdb->posts . '.post_date DESC' : ', ' . trim( $orderby ) );
        } elseif ( strpos( $orderby, 'relevance_score DESC' ) !== 0 ) {
            // Remove existing relevance_score and put it first.
            $orderby = preg_replace( '/\brelevance_score\s+DESC,?\s*/i', '', $orderby );
            $orderby = 'relevance_score DESC, ' . trim( $orderby );
        }

        return $orderby;
    }

    /**
     * Check if explicit sorting is set via URL parameters.
     *
     * @param \WP_Query $wp_query The WordPress query object.
     * @return bool True if explicit sorting is set, false otherwise.
     */
    private function has_explicit_sorting( $wp_query ) {
        // Check for sort or meta_sort parameters.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for public search result sorting.
        if ( isset( $_GET['sort'] ) || isset( $_GET['meta_sort'] ) || isset( $_GET['sort_meta'] ) ) {
            return true;
        }

        // Check if orderby is explicitly set in query vars.
        $orderby = $wp_query->get( 'orderby' );
        if ( ! empty( $orderby ) && 'relevance' !== $orderby && 'date' !== $orderby && 'post_date' !== $orderby ) {
            return true;
        }

        return false;
    }

    /**
     * Build SQL expression for calculating relevance score.
     *
     * @param array  $terms Array of search terms.
     * @param string $original_search The original search string before filtering.
     * @return string SQL expression for relevance score.
     */
    private function build_relevance_score( $terms, $original_search = '' ) {
        global $wpdb;

        if ( empty( $terms ) ) {
            return '';
        }

        // Allow filtering of relevance weights.
        $weights = apply_filters(
            'wordpress_search_relevance_weights',
            array(
                'term_match_base'      => 10,  // Base points for each unique term that matches (anywhere).
                'title_match'          => 15,  // Bonus if term appears in title.
                'title_exact'          => 10,  // Extra bonus for exact title match.
                'title_phrase_exact'   => 10000, // Very large bonus for exact phrase match in title (case-insensitive).
                'content_match'        => 5,   // Bonus if term appears in content.
                'excerpt_match'        => 5,   // Bonus if term appears in excerpt.
                'metadata_match'       => 3,   // Bonus if term appears in metadata.
                'taxonomy_match'       => 3,   // Bonus if term appears in taxonomy.
                'title_taxonomy_bonus' => 10,  // Extra bonus for term in BOTH title AND taxonomy.
                'all_terms_bonus'      => 50,  // Large bonus if document matches ALL search terms.
            )
        );

        $total_terms = count( $terms );
        $score_parts = array();

        // Add very large bonus for exact phrase match in title (highest priority, case-insensitive).
        // This ensures exact matches always rank first, regardless of other scoring factors.
        if ( ! empty( $original_search ) ) {
            // Normalize the search string for comparison.
            $search_phrase = trim( urldecode( $original_search ) );
            $search_phrase = str_replace( '+', ' ', $search_phrase );
            $search_phrase = trim( $search_phrase );

            // Put this FIRST in score_parts so it's evaluated first and gets highest priority.
            $exact_bonus   = absint( $weights['title_phrase_exact'] );
            $search_lower  = strtolower( $search_phrase );
            $search_length = strlen( $search_lower );

            // Simple and efficient: exact match gets bonus, starts-with also gets bonus (for titles with extensions).
            $exact_match_sql = "(CASE
                WHEN LOWER(TRIM($wpdb->posts.post_title)) = " . $wpdb->prepare( '%s', $search_lower ) . " THEN $exact_bonus
                WHEN LEFT(LOWER(TRIM($wpdb->posts.post_title)), $search_length) = " . $wpdb->prepare( '%s', $search_lower ) . " THEN $exact_bonus
                ELSE 0
            END)";

            // Insert at the VERY BEGINNING of score_parts array so it's evaluated first.
            // This ensures exact matches get the highest priority in scoring.
            array_unshift( $score_parts, $exact_match_sql );
        }

        // Build conditions to check if each term matches anywhere in the document.
        $term_match_conditions      = array();
        $all_terms_match_conditions = array();

        foreach ( $terms as $term ) {
            $term      = $wpdb->esc_like( $term );
            $like_term = '%' . $term . '%';

            // Build condition to check if this term matches anywhere.
            $term_anywhere = array();

            // Check title.
            $term_anywhere[] = $wpdb->prepare( "$wpdb->posts.post_title LIKE %s", $like_term );

            // Check content.
            $term_anywhere[] = $wpdb->prepare( "$wpdb->posts.post_content LIKE %s", $like_term );

            // Check excerpt.
            $term_anywhere[] = $wpdb->prepare( "$wpdb->posts.post_excerpt LIKE %s", $like_term );

            // Check metadata.
            $all_meta_keys = $this->get_all_metadata_keys();
            if ( ! empty( $all_meta_keys ) ) {
                foreach ( $all_meta_keys as $key_slug ) {
                    $term_anywhere[] = $wpdb->prepare(
                        '(espm.meta_key = %s AND espm.meta_value LIKE %s)',
                        $key_slug,
                        $like_term
                    );
                }
            }

            // Check taxonomy.
            $all_taxonomies = $this->get_all_searchable_taxonomies();
            if ( ! empty( $all_taxonomies ) ) {
                foreach ( $all_taxonomies as $tax ) {
                    $term_anywhere[] = $wpdb->prepare(
                        '(estt.taxonomy = %s AND est.name LIKE %s)',
                        $tax,
                        $like_term
                    );
                }
            }

            // If term matches anywhere, give base points (counts unique terms, not frequency).
            $term_match_condition         = '(' . implode( ' OR ', $term_anywhere ) . ')';
            $term_match_conditions[]      = $term_match_condition;
            $all_terms_match_conditions[] = $term_match_condition;

            // Now add location-specific bonuses (these are bonuses, not the base score).
            // Title match bonus.
            $score_parts[] = $wpdb->prepare(
                "(CASE WHEN $wpdb->posts.post_title LIKE %s THEN %d ELSE 0 END)",
                $like_term,
                absint( $weights['title_match'] )
            );

            // Exact title match bonus.
            $score_parts[] = $wpdb->prepare(
                "(CASE WHEN $wpdb->posts.post_title = %s THEN %d ELSE 0 END)",
                $term,
                absint( $weights['title_exact'] )
            );

            // Content match bonus.
            $score_parts[] = $wpdb->prepare(
                "(CASE WHEN $wpdb->posts.post_content LIKE %s THEN %d ELSE 0 END)",
                $like_term,
                absint( $weights['content_match'] )
            );

            // Excerpt match bonus.
            $score_parts[] = $wpdb->prepare(
                "(CASE WHEN $wpdb->posts.post_excerpt LIKE %s THEN %d ELSE 0 END)",
                $like_term,
                absint( $weights['excerpt_match'] )
            );

            // Metadata match bonus.
            if ( ! empty( $all_meta_keys ) ) {
                $meta_conditions = array();
                $meta_values     = array();
                foreach ( $all_meta_keys as $key_slug ) {
                    $meta_conditions[] = '(espm.meta_key = %s AND espm.meta_value LIKE %s)';
                    $meta_values[]     = $key_slug;
                    $meta_values[]     = $like_term;
                }
                if ( ! empty( $meta_conditions ) ) {
                    $meta_values[] = absint( $weights['metadata_match'] );
                    $score_parts[] = call_user_func_array(
                        array( $wpdb, 'prepare' ),
                        array_merge(
                            array( '(CASE WHEN (' . implode( ' OR ', $meta_conditions ) . ') THEN %d ELSE 0 END)' ),
                            $meta_values
                        )
                    );
                }
            }

            // Taxonomy match bonus.
            if ( ! empty( $all_taxonomies ) ) {
                $tax_conditions = array();
                $tax_values     = array();
                foreach ( $all_taxonomies as $tax ) {
                    $tax_conditions[] = '(estt.taxonomy = %s AND est.name LIKE %s)';
                    $tax_values[]     = $tax;
                    $tax_values[]     = $like_term;
                }
                if ( ! empty( $tax_conditions ) ) {
                    // Taxonomy match bonus.
                    $tax_values_copy   = $tax_values;
                    $tax_values_copy[] = absint( $weights['taxonomy_match'] );
                    $score_parts[]     = call_user_func_array(
                        array( $wpdb, 'prepare' ),
                        array_merge(
                            array( '(CASE WHEN (' . implode( ' OR ', $tax_conditions ) . ') THEN %d ELSE 0 END)' ),
                            $tax_values_copy
                        )
                    );

                    // Bonus for term in BOTH title AND taxonomy.
                    $tax_values_copy2 = $tax_values;
                    array_unshift( $tax_values_copy2, $like_term );
                    $tax_values_copy2[] = absint( $weights['title_taxonomy_bonus'] );
                    $score_parts[]      = call_user_func_array(
                        array( $wpdb, 'prepare' ),
                        array_merge(
                            array( "(CASE WHEN ($wpdb->posts.post_title LIKE %s AND (" . implode( ' OR ', $tax_conditions ) . ')) THEN %d ELSE 0 END)' ),
                            $tax_values_copy2
                        )
                    );
                }
            }
        }

        // Base score: Points for each unique term that matches (regardless of how many times).
        // This ensures documents matching MORE terms rank higher than documents matching FEWER terms.
        foreach ( $term_match_conditions as $condition ) {
            // $condition contains already-prepared SQL fragments, so we build the CASE WHEN directly
            $score_parts[] = "(CASE WHEN $condition THEN " . absint( $weights['term_match_base'] ) . ' ELSE 0 END)';
        }

        // Large bonus if document matches ALL search terms.
        // This is the key: documents with "chicken 2023 fair" all matching rank much higher
        // than documents with just "chicken" repeated 50 times.
        if ( $total_terms > 1 ) {
            $all_terms_condition = '(' . implode( ' AND ', $all_terms_match_conditions ) . ')';
            // $all_terms_condition contains already-prepared SQL fragments, so we build the CASE WHEN directly
            $score_parts[] = "(CASE WHEN $all_terms_condition THEN " . absint( $weights['all_terms_bonus'] ) . ' ELSE 0 END)';
        }

        if ( empty( $score_parts ) ) {
            return '';
        }

        // Sum all score parts to get total relevance score.
        // Use MAX() to ensure consistent score per post when GROUP BY is used.
        // This prevents duplicate results when multiple taxonomy/metadata rows match.
        $score_expression = '(' . implode( ' + ', $score_parts ) . ')';
        return "MAX($score_expression)";
    }

    /**
     * Get all metadata keys for auto-discovery with caching.
     *
     * @return array Array of metadata keys.
     */
    private function get_all_metadata_keys() {
        // Try to get from cache first.
        $cached_keys = get_transient( self::CACHE_KEY_META );
        if ( false !== $cached_keys ) {
            return $cached_keys;
        }

        global $wpdb;

        // Get all meta keys excluding WordPress internal ones (prefixed with _).
        $wp_es_fields = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} pm 
                 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
                 WHERE pm.meta_key NOT LIKE %s 
                 AND p.post_status = 'publish' 
                 AND pm.meta_value != '' 
                 ORDER BY pm.meta_key ASC 
                 LIMIT %d",
                '\_%',
                self::MAX_META_KEYS
            )
        );

        $meta_keys = array();
        if ( is_array( $wp_es_fields ) && ! empty( $wp_es_fields ) ) {
            foreach ( $wp_es_fields as $field ) {
                if ( isset( $field->meta_key ) && ! empty( $field->meta_key ) ) {
                    $meta_keys[] = sanitize_key( $field->meta_key );
                }
            }
        }

        // Filter sensitive or unwanted keys.
        $meta_keys = $this->filter_metadata_keys( $meta_keys );

        /**
         * Filter all metadata keys for auto-inclusion in search.
         *
         * @param array $meta_keys Array of all metadata keys.
         */
        $meta_keys = apply_filters( 'wordpress_search_auto_meta_keys', $meta_keys );

        // Cache the results.
        set_transient( self::CACHE_KEY_META, $meta_keys, self::CACHE_DURATION );

        return $meta_keys;
    }

    /**
     * Filter out sensitive or unwanted metadata keys.
     *
     * @param array $meta_keys Raw metadata keys.
     * @return array Filtered metadata keys.
     */
    private function filter_metadata_keys( $meta_keys ) {
        $excluded_patterns = array(
            'password',
            'token',
            'secret',
            'api_key',
            'private',
            'session',
            'nonce',
            'wp_',
            'woocommerce_',
        );

        $filtered_keys = array();
        foreach ( $meta_keys as $key ) {
            $exclude = false;
            foreach ( $excluded_patterns as $pattern ) {
                if ( false !== stripos( $key, $pattern ) ) {
                    $exclude = true;
                    break;
                }
            }
            if ( ! $exclude ) {
                $filtered_keys[] = $key;
            }
        }

        return $filtered_keys;
    }

    /**
     * Get all searchable taxonomies with caching.
     *
     * @return array Array of taxonomy names.
     */
    private function get_all_searchable_taxonomies() {
        // Try to get from cache first.
        $cached_taxonomies = get_transient( self::CACHE_KEY_TAX );
        if ( false !== $cached_taxonomies ) {
            return $cached_taxonomies;
        }

        // Get public taxonomies that have UI.
        $tax_args = apply_filters(
            'wordpress_search_tax_args',
            array(
                'show_ui' => true,
                'public'  => true,
            )
        );

        $all_taxonomies = get_taxonomies( $tax_args, 'objects' );
        $all_taxonomies = apply_filters( 'wordpress_search_tax', $all_taxonomies );

        $taxonomy_names = array();
        if ( is_array( $all_taxonomies ) && ! empty( $all_taxonomies ) ) {
            foreach ( $all_taxonomies as $tax_name => $tax_obj ) {
                if ( ! empty( $tax_name ) ) {
                    $taxonomy_names[] = sanitize_key( $tax_name );
                }
            }
        }

        /**
         * Filter all taxonomies for auto-inclusion in search.
         *
         * @param array $taxonomy_names Array of all taxonomy names.
         */
        $taxonomy_names = apply_filters( 'wordpress_search_auto_taxonomies', $taxonomy_names );

        // Cache the results.
        set_transient( self::CACHE_KEY_TAX, $taxonomy_names, self::CACHE_DURATION );

        return $taxonomy_names;
    }

    /**
     * Clear metadata cache when post meta changes
     */
    public function clear_meta_cache() {
        delete_transient( self::CACHE_KEY_META );
    }
}
