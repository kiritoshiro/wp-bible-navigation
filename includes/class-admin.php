<?php
/**
 * Admin screens: the link editor box, the grouped book checklist, list columns
 * and filter, "Quick add" and the one-time "Import from page".
 */
defined( 'ABSPATH' ) || exit;

final class Bible_Navigation_Admin {
	/** Most lines handled by one Quick add submission. */
	const MAX_LINES = 100;
	/** Seconds one request may spend fetching titles; later links get a title from their URL. */
	const TITLE_BUDGET = 25;

	private static $deadline = 0;

	public static function register() {
		add_action( 'admin_init', array( 'Bible_Navigation', 'seed_books' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'add_meta_boxes_' . Bible_Navigation::ITEM, array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . Bible_Navigation::ITEM, array( __CLASS__, 'save_item' ), 10, 2 );
		add_filter( 'manage_' . Bible_Navigation::ITEM . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . Bible_Navigation::ITEM . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'filter' ) );
		add_action( 'admin_post_bnav_quick_add', array( __CLASS__, 'quick_add' ) );
		add_action( 'admin_post_bnav_import', array( __CLASS__, 'import' ) );
	}

	public static function menu() {
		$parent = 'edit.php?post_type=' . Bible_Navigation::ITEM;
		add_submenu_page( $parent, __( 'Quick add', 'wp-bible-navigation' ), __( 'Quick add', 'wp-bible-navigation' ), 'publish_posts', 'bnav-quick-add', array( __CLASS__, 'quick_page' ) );
		add_submenu_page( $parent, __( 'Import from page', 'wp-bible-navigation' ), __( 'Import from page', 'wp-bible-navigation' ), 'publish_posts', 'bnav-import', array( __CLASS__, 'import_page' ) );
	}

	public static function assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && ( Bible_Navigation::ITEM === $screen->post_type || in_array( $screen->post_type, Bible_Navigation::post_types(), true ) ) ) {
			wp_enqueue_style( 'bible-navigation-admin', plugins_url( 'assets/admin.css', BNAV_FILE ), array(), BNAV_VERSION );
		}
	}

	/* ------------------------------------------------------------ link editor */

	public static function meta_boxes() {
		add_meta_box( 'bnav-link', __( 'Link', 'wp-bible-navigation' ), array( __CLASS__, 'link_box' ), Bible_Navigation::ITEM, 'normal', 'high' );
	}

	public static function link_box( $post ) {
		$url   = (string) get_post_meta( $post->ID, '_bnav_url', true );
		$link  = (string) get_post_meta( $post->ID, '_bnav_link', true );
		$type  = (string) get_post_meta( $post->ID, '_bnav_type', true );
		$entry = '' !== $url ? Bible_Navigation::detect( $url, $type ) : null;
		wp_nonce_field( 'bnav_item', 'bnav_item_nonce' );
		echo '<p><label for="bnav-url"><strong>' . esc_html__( 'Video, audio or article URL', 'wp-bible-navigation' ) . '</strong></label><br>';
		echo '<input type="url" class="large-text" id="bnav-url" name="bnav_url" value="' . esc_attr( $url ) . '" placeholder="https://www.youtube.com/watch?v=…" required></p>';
		echo '<p class="description">' . esc_html__( 'YouTube links become videos, audio files (.mp3, .m4a…) become audio, anything else is an article. An empty title is filled in from the link when you save.', 'wp-bible-navigation' ) . '</p>';
		echo '<p><label for="bnav-type"><strong>' . esc_html__( 'Type', 'wp-bible-navigation' ) . '</strong></label><br>' . self::type_select( 'bnav_type', 'bnav-type', $type ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- type_select() escapes every part.
		if ( $entry ) {
			echo ' <span class="description">' . esc_html( sprintf( /* translators: %s: video, audio or article */ __( 'Shown as: %s', 'wp-bible-navigation' ), self::type_label( $entry['type'] ) ) ) . '</span>';
		} elseif ( '' !== $url ) {
			echo ' <span class="bnav-warning">' . esc_html__( 'This URL is not valid; the link is hidden.', 'wp-bible-navigation' ) . '</span>';
		}
		echo '</p><p><label for="bnav-link-page"><strong>' . esc_html__( 'Source page (optional)', 'wp-bible-navigation' ) . '</strong></label><br>';
		echo '<input type="url" class="large-text" id="bnav-link-page" name="bnav_link" value="' . esc_attr( $link ) . '" placeholder="https://"></p>';
		echo '<p class="description">' . esc_html__( 'For audio: the page of the episode, shown as a "source" link next to the player.', 'wp-bible-navigation' ) . '</p>';
	}

	public static function save_item( $post_id, $post ) {
		if ( ! isset( $_POST['bnav_item_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bnav_item_nonce'] ) ), 'bnav_item' ) ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$url  = isset( $_POST['bnav_url'] ) ? self::clean_url( sanitize_url( wp_unslash( $_POST['bnav_url'] ) ) ) : '';
		$link = isset( $_POST['bnav_link'] ) ? self::clean_url( sanitize_url( wp_unslash( $_POST['bnav_link'] ) ) ) : '';
		$type = isset( $_POST['bnav_type'] ) ? sanitize_key( wp_unslash( $_POST['bnav_type'] ) ) : '';
		update_post_meta( $post_id, '_bnav_url', $url );
		update_post_meta( $post_id, '_bnav_link', $link );
		update_post_meta( $post_id, '_bnav_type', in_array( $type, Bible_Navigation::TYPES, true ) ? $type : '' );
		if ( '' === trim( $post->post_title ) && '' !== $url ) {
			$title = Bible_Navigation::fetch_title( $url );
			remove_action( 'save_post_' . Bible_Navigation::ITEM, array( __CLASS__, 'save_item' ), 10 );
			wp_update_post( array( 'ID' => $post_id, 'post_title' => '' !== $title ? $title : Bible_Navigation::fallback_title( $url ) ) );
			add_action( 'save_post_' . Bible_Navigation::ITEM, array( __CLASS__, 'save_item' ), 10, 2 );
		}
		// A title typed or kept by hand is no longer a stand-in.
		delete_post_meta( $post_id, '_bnav_auto_title' );
		Bible_Navigation::flush();
	}

	/**
	 * Fetching titles stops this many seconds from now, and well before PHP's
	 * own time limit, so a long list never ends in a fatal timeout.
	 */
	private static function start_budget( $seconds ) {
		$limit = (int) ini_get( 'max_execution_time' );
		if ( $limit > 0 ) {
			$seconds = min( $seconds, max( 0, $limit - 10 ) );
		}
		self::$deadline = microtime( true ) + $seconds;
	}

	/** A fetched title while time is left, else ''. */
	private static function title_in_budget( $url ) {
		return microtime( true ) < self::$deadline ? Bible_Navigation::fetch_title( $url ) : '';
	}

	/** An http(s) URL with a host, else ''. */
	private static function clean_url( $url ) {
		return Bible_Navigation::detect( $url ) ? (string) $url : '';
	}

	private static function type_label( $type ) {
		$labels = array(
			''        => __( 'Automatic', 'wp-bible-navigation' ),
			'video'   => __( 'Video', 'wp-bible-navigation' ),
			'audio'   => __( 'Audio', 'wp-bible-navigation' ),
			'article' => __( 'Article', 'wp-bible-navigation' ),
		);
		return isset( $labels[ $type ] ) ? $labels[ $type ] : $labels[''];
	}

	private static function type_select( $name, $id, $selected ) {
		$html = '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $id ) . '">';
		foreach ( array_merge( array( '' ), Bible_Navigation::TYPES ) as $type ) {
			$html .= '<option value="' . esc_attr( $type ) . '"' . selected( $selected, $type, false ) . '>' . esc_html( self::type_label( $type ) ) . '</option>';
		}
		return $html . '</select>';
	}

	/* ------------------------------------------------------------ book checklist */

	/** Book terms by slug; unknown (added by hand) terms come last. */
	private static function terms() {
		$terms = get_terms(
			array(
				'taxonomy'   => Bible_Navigation::BOOK,
				'hide_empty' => false,
				'orderby'    => 'term_id',
			)
		);
		$by_slug = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$by_slug[ $term->slug ] = $term;
		}
		return $by_slug;
	}

	/** Replaces the alphabetical category box: books in Bible order, grouped (classic editor screens). */
	public static function book_box( $post ) {
		$taxonomy = get_taxonomy( Bible_Navigation::BOOK );
		$disabled = $taxonomy && current_user_can( $taxonomy->cap->assign_terms ) ? '' : ' disabled';
		$terms    = self::terms();
		$current  = wp_get_object_terms( $post->ID, Bible_Navigation::BOOK, array( 'fields' => 'ids' ) );
		$current  = is_array( $current ) ? array_map( 'intval', $current ) : array();
		$name     = 'tax_input[' . Bible_Navigation::BOOK . '][]';
		echo '<div class="bnav-books-box"><input type="hidden" name="' . esc_attr( $name ) . '" value="0">';
		foreach ( Bible_Navigation_Books::tree() as $t ) {
			echo '<p class="bnav-books-testament">' . esc_html( $t['name'] ) . '</p>';
			foreach ( $t['groups'] as $g ) {
				echo '<fieldset><legend>' . esc_html( $g['name'] ) . '</legend>';
				foreach ( array_keys( $g['books'] ) as $slug ) {
					if ( isset( $terms[ $slug ] ) ) {
						self::book_checkbox( $terms[ $slug ], $name, $current, $disabled );
						unset( $terms[ $slug ] );
					}
				}
				echo '</fieldset>';
			}
		}
		if ( $terms ) {
			echo '<fieldset><legend>' . esc_html__( 'Other', 'wp-bible-navigation' ) . '</legend>';
			foreach ( $terms as $term ) {
				self::book_checkbox( $term, $name, $current, $disabled );
			}
			echo '</fieldset>';
		}
		echo '</div>';
	}

	private static function book_checkbox( $term, $name, array $current, $disabled ) {
		echo '<label><input type="checkbox" name="' . esc_attr( $name ) . '" value="' . (int) $term->term_id . '"' . checked( in_array( (int) $term->term_id, $current, true ), true, false ) . esc_attr( $disabled ) . '> ' . esc_html( $term->name ) . '</label>';
	}

	/** <select> of books in Bible order, grouped by testament. */
	private static function book_select( $name, $selected = '' ) {
		$terms = self::terms();
		$html  = '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" required><option value="">' . esc_html__( '— Choose a book —', 'wp-bible-navigation' ) . '</option>';
		foreach ( Bible_Navigation_Books::tree() as $t ) {
			$html .= '<optgroup label="' . esc_attr( $t['name'] ) . '">';
			foreach ( $t['groups'] as $g ) {
				foreach ( $g['books'] as $slug => $book ) {
					if ( isset( $terms[ $slug ] ) ) {
						$html .= '<option value="' . esc_attr( $slug ) . '"' . selected( $selected, $slug, false ) . '>' . esc_html( $terms[ $slug ]->name ) . '</option>';
					}
				}
			}
			$html .= '</optgroup>';
		}
		return $html . '</select>';
	}

	/* ------------------------------------------------------------ list screen */

	public static function columns( $columns ) {
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out['bnav_type'] = __( 'Type', 'wp-bible-navigation' );
				$out['bnav_url']  = __( 'Link', 'wp-bible-navigation' );
			}
		}
		return $out;
	}

	public static function column( $column, $post_id ) {
		if ( 'bnav_type' !== $column && 'bnav_url' !== $column ) {
			return;
		}
		$url   = (string) get_post_meta( $post_id, '_bnav_url', true );
		$entry = Bible_Navigation::detect( $url, (string) get_post_meta( $post_id, '_bnav_type', true ) );
		if ( 'bnav_type' === $column && $entry ) {
			echo esc_html( self::type_label( $entry['type'] ) );
		} elseif ( 'bnav_type' === $column ) {
			echo '<span class="bnav-warning">' . esc_html__( 'Invalid link', 'wp-bible-navigation' ) . '</span>';
		} elseif ( $entry ) {
			echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $entry['host'] ) . '</a>';
		}
	}

	/** "All books" filter above the link and post lists. */
	public static function filter( $post_type ) {
		if ( Bible_Navigation::ITEM !== $post_type && ! in_array( $post_type, Bible_Navigation::post_types(), true ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.
		$selected = isset( $_GET[ Bible_Navigation::BOOK ] ) ? sanitize_title( wp_unslash( $_GET[ Bible_Navigation::BOOK ] ) ) : '';
		wp_dropdown_categories(
			array(
				'taxonomy'        => Bible_Navigation::BOOK,
				'name'            => Bible_Navigation::BOOK,
				'value_field'     => 'slug',
				'selected'        => $selected,
				'show_option_all' => __( 'All books', 'wp-bible-navigation' ),
				'hide_empty'      => false,
				'orderby'         => 'term_id',
				'show_count'      => true,
			)
		);
	}

	/* ------------------------------------------------------------ quick add */

	public static function quick_page() {
		if ( ! current_user_can( 'publish_posts' ) ) {
			return;
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Quick add', 'wp-bible-navigation' ) . '</h1>';
		self::result_notice();
		echo '<p>' . esc_html__( 'Choose a book and paste one link per line. A line can also be "Title | URL". Links to posts on this site put that post under the book; other links (YouTube, audio, dievozodis.lt articles…) are added as links. Titles are filled in automatically.', 'wp-bible-navigation' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="bnav-quick">';
		echo '<input type="hidden" name="action" value="bnav_quick_add">';
		wp_nonce_field( 'bnav_quick_add' );
		echo '<p><label for="bnav_book"><strong>' . esc_html__( 'Bible book', 'wp-bible-navigation' ) . '</strong></label><br>' . self::book_select( 'bnav_book' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- book_select() escapes every part.
		echo '<p><label for="bnav-links"><strong>' . esc_html__( 'Links', 'wp-bible-navigation' ) . '</strong></label><br><textarea id="bnav-links" name="bnav_links" rows="10" class="large-text code" required placeholder="https://www.youtube.com/watch?v=…&#10;Pradžia (2022/II) | https://dievozodis.lt/…"></textarea></p>';
		echo '<p><label for="bnav-quick-type"><strong>' . esc_html__( 'Type', 'wp-bible-navigation' ) . '</strong></label><br>' . self::type_select( 'bnav_type', 'bnav-quick-type', '' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- type_select() escapes every part.
		submit_button( __( 'Add links', 'wp-bible-navigation' ) );
		echo '</form>';
		echo '<h2>' . esc_html__( 'Showing the list on a page', 'wp-bible-navigation' ) . '</h2><p>' . esc_html__( 'Add the "Bible navigation" block to a page, or the shortcode [bible_navigation]. Regular posts can be put under a book from the "Bible books" panel in the post editor.', 'wp-bible-navigation' ) . '</p></div>';
	}

	public static function quick_add() {
		check_admin_referer( 'bnav_quick_add' );
		if ( ! current_user_can( 'publish_posts' ) ) {
			wp_die( esc_html__( 'You are not allowed to add links.', 'wp-bible-navigation' ), 403 );
		}
		$slug = isset( $_POST['bnav_book'] ) ? sanitize_title( wp_unslash( $_POST['bnav_book'] ) ) : '';
		$term = get_term_by( 'slug', $slug, Bible_Navigation::BOOK );
		$type = isset( $_POST['bnav_type'] ) ? sanitize_key( wp_unslash( $_POST['bnav_type'] ) ) : '';
		$type = in_array( $type, Bible_Navigation::TYPES, true ) ? $type : '';
		// Each line is split and its title and URL are sanitized separately in parse_line();
		// sanitize_textarea_field() would delete the %xx escapes inside URLs.
		$raw    = isset( $_POST['bnav_links'] ) ? (string) wp_unslash( $_POST['bnav_links'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$counts = self::counts();
		if ( ! $term ) {
			$counts['invalid'] = 1;
		} else {
			self::start_budget( self::TITLE_BUDGET );
			$lines = array_slice( array_filter( array_map( 'trim', (array) preg_split( '/\R/u', $raw ) ) ), 0, self::MAX_LINES );
			foreach ( $lines as $line ) {
				$parsed = self::parse_line( $line );
				$result = $parsed ? self::add( $parsed['url'], $parsed['title'], $type, $term, '', $counts ) : 'invalid';
				++$counts[ $result ];
			}
			Bible_Navigation::flush();
		}
		self::finish( 'bnav-quick-add', $counts );
	}

	/** "URL" or "Title | URL" → array( url, title ), or null. */
	public static function parse_line( $line ) {
		$title = '';
		$url   = trim( (string) $line );
		$bar   = strrpos( $url, '|' );
		if ( false !== $bar ) {
			$title = sanitize_text_field( substr( $url, 0, $bar ) );
			$url   = trim( substr( $url, $bar + 1 ) );
		}
		$url = sanitize_url( $url );
		return Bible_Navigation::detect( $url ) ? array( 'url' => $url, 'title' => $title ) : null;
	}

	private static function counts() {
		return array( 'added' => 0, 'linked' => 0, 'tagged' => 0, 'skipped' => 0, 'invalid' => 0, 'untitled' => 0 );
	}

	/**
	 * Put one URL under a book: tag a local post, add the book to an existing
	 * link with the same URL, or create a link. Returns the count key; links
	 * left with a stand-in title are counted in $counts['untitled'].
	 */
	public static function add( $url, $title, $type, $term, $link, array &$counts ) {
		$entry = Bible_Navigation::detect( $url, $type );
		if ( ! $entry ) {
			return 'invalid';
		}
		$post_id = 'article' === $entry['type'] ? url_to_postid( $url ) : 0;
		if ( $post_id && in_array( get_post_type( $post_id ), Bible_Navigation::post_types(), true ) ) {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return 'invalid';
			}
			if ( has_term( (int) $term->term_id, Bible_Navigation::BOOK, $post_id ) ) {
				return 'skipped';
			}
			wp_set_object_terms( $post_id, (int) $term->term_id, Bible_Navigation::BOOK, true );
			return 'tagged';
		}
		$stored   = 'audio' === $entry['type'] ? $entry['src'] : $url;
		$existing = get_posts(
			array(
				'post_type'   => Bible_Navigation::ITEM,
				'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'numberposts' => 1,
				'fields'      => 'ids',
				'meta_key'    => '_bnav_url', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Admin-only duplicate check.
				'meta_value'  => $stored, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Admin-only duplicate check.
			)
		);
		$title = Bible_Navigation::clean_title( $title );
		if ( $existing ) {
			// Running the import or Quick add again fills in stand-in titles.
			if ( get_post_meta( $existing[0], '_bnav_auto_title', true ) ) {
				$better = '' !== $title ? $title : self::title_in_budget( $url );
				if ( '' !== $better ) {
					wp_update_post( array( 'ID' => $existing[0], 'post_title' => $better ) );
					delete_post_meta( $existing[0], '_bnav_auto_title' );
				} else {
					++$counts['untitled'];
				}
			}
			if ( has_term( (int) $term->term_id, Bible_Navigation::BOOK, $existing[0] ) ) {
				return 'skipped';
			}
			wp_set_object_terms( $existing[0], (int) $term->term_id, Bible_Navigation::BOOK, true );
			return 'linked';
		}
		$auto = false;
		if ( '' === $title ) {
			$title = self::title_in_budget( $url );
		}
		if ( '' === $title ) {
			$title = Bible_Navigation::fallback_title( $url );
			$auto  = true;
		}
		$id = wp_insert_post(
			array(
				'post_type'   => Bible_Navigation::ITEM,
				'post_status' => 'publish',
				'post_title'  => $title,
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return 'invalid';
		}
		if ( $auto ) {
			update_post_meta( $id, '_bnav_auto_title', '1' );
			++$counts['untitled'];
		}
		update_post_meta( $id, '_bnav_url', $stored );
		update_post_meta( $id, '_bnav_type', $type );
		update_post_meta( $id, '_bnav_link', '' !== $link && Bible_Navigation::detect( $link ) ? $link : '' );
		wp_set_object_terms( $id, (int) $term->term_id, Bible_Navigation::BOOK );
		return 'added';
	}

	private static function finish( $page, array $counts ) {
		$args = array( 'post_type' => Bible_Navigation::ITEM, 'page' => $page );
		foreach ( $counts as $key => $count ) {
			$args[ 'bnav_' . $key ] = (int) $count;
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'edit.php' ) ) );
		exit;
	}

	private static function result_notice() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only counts after a redirect.
		if ( ! isset( $_GET['bnav_added'] ) ) {
			return;
		}
		$count = static function ( $key ) {
			return isset( $_GET[ 'bnav_' . $key ] ) ? absint( $_GET[ 'bnav_' . $key ] ) : 0;
		};
		// phpcs:enable
		$message = sprintf(
			/* translators: 1: new links, 2: existing links put under the book too, 3: posts put under the book, 4: already there, 5: invalid lines */
			__( 'Added: %1$d. Existing links added to the book: %2$d. Posts added to the book: %3$d. Already there: %4$d. Not valid: %5$d.', 'wp-bible-navigation' ),
			$count( 'added' ),
			$count( 'linked' ),
			$count( 'tagged' ),
			$count( 'skipped' ),
			$count( 'invalid' )
		);
		if ( $count( 'untitled' ) ) {
			$message .= ' ' . sprintf(
				/* translators: %d: links still showing a stand-in title */
				__( 'Titles not fetched yet: %d. Submit the same links (or run the import) again to fetch them, or edit them by hand.', 'wp-bible-navigation' ),
				$count( 'untitled' )
			);
		}
		$class = $count( 'invalid' ) || $count( 'untitled' ) ? 'notice-warning' : 'notice-success';
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	/* ------------------------------------------------------------ import from page */

	public static function import_page() {
		if ( ! current_user_can( 'publish_posts' ) ) {
			return;
		}
		$page_id = 0;
		if ( isset( $_GET['bnav_page'], $_GET['_bnav_preview'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_bnav_preview'] ) ), 'bnav_preview' ) ) {
			$page_id = absint( $_GET['bnav_page'] );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Import from page', 'wp-bible-navigation' ) . '</h1>';
		self::result_notice();
		echo '<p>' . esc_html__( 'Reads a page built by hand with Bible book headings (such as "Pradžios knyga") and the videos, audio and links under them, and creates the same links here. Links that already exist are not duplicated, so it is safe to run again. The page itself is not changed.', 'wp-bible-navigation' ) . '</p>';
		echo '<form method="get" action="' . esc_url( admin_url( 'edit.php' ) ) . '">';
		echo '<input type="hidden" name="post_type" value="' . esc_attr( Bible_Navigation::ITEM ) . '"><input type="hidden" name="page" value="bnav-import">';
		wp_nonce_field( 'bnav_preview', '_bnav_preview', false );
		echo '<label for="bnav_page">' . esc_html__( 'Page', 'wp-bible-navigation' ) . '</label> ';
		wp_dropdown_pages(
			array(
				'name'             => 'bnav_page',
				'id'               => 'bnav_page',
				'selected'         => (int) $page_id,
				'show_option_none' => esc_html__( '— Choose a page —', 'wp-bible-navigation' ),
				'post_status'      => array( 'publish', 'draft', 'private' ),
			)
		);
		submit_button( __( 'Preview', 'wp-bible-navigation' ), 'secondary', '', false );
		echo '</form>';

		if ( $page_id && current_user_can( 'edit_post', $page_id ) ) {
			$found = self::collect( (string) get_post_field( 'post_content', $page_id ) );
			self::preview( $found );
			if ( $found ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				echo '<input type="hidden" name="action" value="bnav_import"><input type="hidden" name="bnav_page" value="' . esc_attr( (string) $page_id ) . '">';
				wp_nonce_field( 'bnav_import' );
				/* translators: %d: number of links */
				submit_button( sprintf( __( 'Import %d links', 'wp-bible-navigation' ), count( $found ) ) );
				echo '</form>';
			}
		}
		echo '</div>';
	}

	private static function preview( array $found ) {
		if ( ! $found ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'No links under Bible book headings were found on this page.', 'wp-bible-navigation' ) . '</p></div>';
			return;
		}
		$books = Bible_Navigation_Books::books();
		$rows  = array();
		foreach ( $found as $item ) {
			$entry = Bible_Navigation::detect( $item['url'] );
			$rows[ $item['book'] ][ $entry['type'] ] = ( isset( $rows[ $item['book'] ][ $entry['type'] ] ) ? $rows[ $item['book'] ][ $entry['type'] ] : 0 ) + 1;
		}
		echo '<table class="widefat striped bnav-preview"><thead><tr><th>' . esc_html__( 'Bible book', 'wp-bible-navigation' ) . '</th>';
		foreach ( Bible_Navigation::TYPES as $type ) {
			echo '<th>' . esc_html( self::type_label( $type ) ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $slug => $types ) {
			echo '<tr><td>' . esc_html( $books[ $slug ]['name'] ) . '</td>';
			foreach ( Bible_Navigation::TYPES as $type ) {
				echo '<td>' . ( isset( $types[ $type ] ) ? (int) $types[ $type ] : '' ) . '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	public static function import() {
		check_admin_referer( 'bnav_import' );
		$page_id = isset( $_POST['bnav_page'] ) ? absint( $_POST['bnav_page'] ) : 0;
		if ( ! current_user_can( 'publish_posts' ) || ! $page_id || ! current_user_can( 'edit_post', $page_id ) ) {
			wp_die( esc_html__( 'You are not allowed to import from this page.', 'wp-bible-navigation' ), 403 );
		}
		$counts = self::counts();
		$terms  = self::terms();
		self::start_budget( self::TITLE_BUDGET );
		foreach ( self::collect( (string) get_post_field( 'post_content', $page_id ) ) as $item ) {
			$result = isset( $terms[ $item['book'] ] ) ? self::add( $item['url'], $item['title'], '', $terms[ $item['book'] ], $item['link'], $counts ) : 'invalid';
			++$counts[ $result ];
		}
		Bible_Navigation::flush();
		self::finish( 'bnav-import', $counts );
	}

	/**
	 * Links on a hand-built page, each under the nearest Bible book heading
	 * before it: array( book, url, title, link ), in page order, without
	 * duplicates. Reads embeds, audio/video blocks, [embedyt]/[embed]/[audio]
	 * shortcodes, bare URL lines and links in text.
	 */
	public static function collect( $content ) {
		$found = array();
		$book  = '';
		self::walk( parse_blocks( (string) $content ), $book, $found );
		return array_values( $found );
	}

	private static function walk( array $blocks, &$book, array &$found ) {
		$names = array();
		foreach ( Bible_Navigation_Books::tree() as $t ) {
			$names[ Bible_Navigation_Books::fold( $t['name'] ) ] = true;
			foreach ( $t['groups'] as $g ) {
				$names[ Bible_Navigation_Books::fold( $g['name'] ) ] = true;
			}
		}
		foreach ( $blocks as $block ) {
			$name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
			$html = isset( $block['innerHTML'] ) ? (string) $block['innerHTML'] : '';
			if ( 'core/heading' === $name ) {
				$anchor = isset( $block['attrs']['anchor'] ) ? (string) $block['attrs']['anchor'] : '';
				$slug   = Bible_Navigation_Books::find( $anchor );
				$slug   = '' !== $slug ? $slug : Bible_Navigation_Books::find( $html );
				if ( '' !== $slug ) {
					$book = $slug;
				} elseif ( isset( $names[ Bible_Navigation_Books::fold( wp_strip_all_tags( $html ) ) ] ) ) {
					$book = '';
				}
				continue;
			}
			if ( '' !== $book ) {
				foreach ( self::block_links( $name, $block, $html ) as $link ) {
					$key = $book . '|' . $link['url'];
					if ( ! isset( $found[ $key ] ) ) {
						$found[ $key ] = array( 'book' => $book ) + $link;
					}
				}
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				self::walk( $block['innerBlocks'], $book, $found );
			}
		}
	}

	/** Links in one block (not its inner blocks): array( url, title, link ). */
	private static function block_links( $name, array $block, $html ) {
		$caption = '';
		$link    = '';
		if ( preg_match( '#<figcaption[^>]*>(.*?)</figcaption>#is', $html, $m ) ) {
			$caption = Bible_Navigation::clean_title( $m[1] );
			if ( preg_match( '#href=(["\'])(https?://[^"\']+)\1#i', $m[1], $href ) ) {
				$link = html_entity_decode( $href[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			}
		}
		$urls = array();
		if ( 'core/embed' === $name ) {
			$urls[] = isset( $block['attrs']['url'] ) ? (string) $block['attrs']['url'] : '';
		} elseif ( 'core/audio' === $name || 'core/video' === $name ) {
			if ( preg_match( '#\ssrc=(["\'])(https?://[^"\']+)\1#i', $html, $m ) ) {
				$urls[] = $m[2];
			}
		} elseif ( in_array( $name, array( '', 'core/paragraph', 'core/shortcode', 'core/html', 'core/freeform', 'core/list', 'core/list-item', 'core/file' ), true ) ) {
			return self::text_links( $html );
		}
		$out = array();
		foreach ( $urls as $url ) {
			$url = html_entity_decode( trim( $url ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			if ( Bible_Navigation::detect( $url ) ) {
				$out[] = array( 'url' => $url, 'title' => $caption, 'link' => $link );
			}
		}
		return $out;
	}

	/** Shortcode media, bare URL lines and <a> links in a text block. */
	private static function text_links( $html ) {
		$out = array();
		if ( preg_match_all( '#\[(embedyt|embed|audio|video)\b([^\]]*)\](?:\s*([^\[\s<]+)\s*\[/\1\])?#i', $html, $all, PREG_SET_ORDER ) ) {
			foreach ( $all as $m ) {
				$atts = shortcode_parse_atts( $m[2] );
				$url  = isset( $m[3] ) ? $m[3] : '';
				foreach ( array( 'url', 'src', 'mp3', 'm4a', 'ogg', 'wav', 'mp4' ) as $key ) {
					if ( is_array( $atts ) && ! empty( $atts[ $key ] ) ) {
						$url = $atts[ $key ];
						break;
					}
				}
				$out[] = array( 'url' => $url, 'title' => '', 'link' => '' );
			}
		}
		$text = (string) preg_replace( '#\[(embedyt|embed|audio|video)\b[^\]]*\].*?(\[/\1\]|$)#ims', '', $html );
		if ( preg_match_all( '#^\s*(?:<p>)?\s*(https?://[^\s<>"\']+)\s*(?:</p>)?\s*$#im', $text, $all ) ) {
			foreach ( $all[1] as $url ) {
				$out[] = array( 'url' => $url, 'title' => '', 'link' => '' );
			}
		}
		if ( preg_match_all( '#<a\s[^>]*href=(["\'])(https?://[^"\']+)\1[^>]*>(.*?)</a>#is', $text, $all, PREG_SET_ORDER ) ) {
			foreach ( $all as $m ) {
				$out[] = array( 'url' => $m[2], 'title' => Bible_Navigation::clean_title( $m[3] ), 'link' => '' );
			}
		}
		$valid = array();
		foreach ( $out as $item ) {
			$item['url'] = html_entity_decode( trim( (string) $item['url'] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			if ( Bible_Navigation::detect( $item['url'] ) ) {
				$valid[] = $item;
			}
		}
		return $valid;
	}
}
