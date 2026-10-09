<?php
/**
 * Data model (link items and the Bible book taxonomy), link detection and the
 * cached front-end list.
 */
defined( 'ABSPATH' ) || exit;

final class Bible_Navigation {
	/** Post type of a single video, audio or article link. */
	const ITEM = 'bnav_item';
	/** Taxonomy of Bible books, shared by link items and regular posts. */
	const BOOK = 'bnav_book';
	/** Option whose number is part of the cache key; see flush(). */
	const CACHE = 'bnav_cache';
	/** Option set once the 66 book terms exist. */
	const SEEDED = 'bnav_books_seeded';
	/** Upper bound of rendered entries, so one page never builds an unbounded list. */
	const MAX = 3000;

	const TYPES = array( 'video', 'audio', 'article' );

	public static function register() {
		add_action( 'init', array( __CLASS__, 'load_translations' ), 1 );
		add_action( 'init', array( __CLASS__, 'register_types' ) );
		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_shortcode( 'bible_navigation', array( __CLASS__, 'shortcode' ) );
		add_action( 'save_post', array( __CLASS__, 'flush_for_post' ) );
		add_action( 'delete_post', array( __CLASS__, 'flush_for_post' ) );
		add_action( 'set_object_terms', array( __CLASS__, 'flush_for_terms' ), 10, 4 );
		add_action( 'edited_' . self::BOOK, array( __CLASS__, 'flush' ) );
		add_action( 'delete_' . self::BOOK, array( __CLASS__, 'flush' ) );
	}

	public static function load_translations() {
		load_plugin_textdomain( 'wp-bible-navigation', false, dirname( plugin_basename( BNAV_FILE ) ) . '/languages' );
	}

	/** Post types whose posts can be put under a Bible book (regular articles). */
	public static function post_types() {
		$types = apply_filters( 'bible_navigation_post_types', array( 'post' ) );
		return array_values( array_filter( array_map( 'sanitize_key', (array) $types ) ) );
	}

	public static function register_types() {
		register_post_type(
			self::ITEM,
			array(
				'labels'              => array(
					'name'               => __( 'Links', 'wp-bible-navigation' ),
					'singular_name'      => __( 'Link', 'wp-bible-navigation' ),
					'menu_name'          => 'Bible navigation',
					'all_items'          => __( 'All links', 'wp-bible-navigation' ),
					'add_new'            => __( 'Add link', 'wp-bible-navigation' ),
					'add_new_item'       => __( 'Add link', 'wp-bible-navigation' ),
					'edit_item'          => __( 'Edit link', 'wp-bible-navigation' ),
					'new_item'           => __( 'New link', 'wp-bible-navigation' ),
					'search_items'       => __( 'Search links', 'wp-bible-navigation' ),
					'not_found'          => __( 'No links found.', 'wp-bible-navigation' ),
					'not_found_in_trash' => __( 'No links found in Trash.', 'wp-bible-navigation' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'menu_position'       => 21,
				'menu_icon'           => 'dashicons-book-alt',
				'supports'            => array( 'title', 'page-attributes' ),
				'map_meta_cap'        => true,
			)
		);
		register_taxonomy(
			self::BOOK,
			array_merge( array( self::ITEM ), self::post_types() ),
			array(
				'labels'             => array(
					'name'          => __( 'Bible books', 'wp-bible-navigation' ),
					'singular_name' => __( 'Bible book', 'wp-bible-navigation' ),
					'menu_name'     => __( 'Bible books', 'wp-bible-navigation' ),
					'search_items'  => __( 'Search books', 'wp-bible-navigation' ),
					'all_items'     => __( 'All books', 'wp-bible-navigation' ),
					'edit_item'     => __( 'Edit book', 'wp-bible-navigation' ),
					'not_found'     => __( 'No books found.', 'wp-bible-navigation' ),
				),
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_rest'       => true,
				'show_admin_column'  => true,
				'show_tagcloud'      => false,
				'hierarchical'       => true,
				'rewrite'            => false,
				'query_var'          => self::BOOK,
				'meta_box_cb'        => array( 'Bible_Navigation_Admin', 'book_box' ),
			)
		);
	}

	/** Create the 66 book terms once (slugs = the old page anchors). */
	public static function seed_books() {
		if ( '1' === get_option( self::SEEDED ) || ! taxonomy_exists( self::BOOK ) ) {
			return;
		}
		foreach ( Bible_Navigation_Books::books() as $slug => $book ) {
			if ( ! term_exists( $slug, self::BOOK ) ) {
				wp_insert_term( $book['name'], self::BOOK, array( 'slug' => $slug ) );
			}
		}
		update_option( self::SEEDED, '1', false );
	}

	/* ------------------------------------------------------------------ links */

	/**
	 * What a URL is: array( type, href, video, list, src, host ), or null when
	 * it is not an http(s) URL. $type forces video/audio/article where possible.
	 */
	public static function detect( $url, $type = '' ) {
		$url   = trim( html_entity_decode( (string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! isset( $parts['scheme'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return null;
		}
		$host = strtolower( $parts['host'] );
		$host = 0 === strpos( $host, 'www.' ) ? substr( $host, 4 ) : $host;
		$path = isset( $parts['path'] ) ? $parts['path'] : '';
		$query = array();
		parse_str( isset( $parts['query'] ) ? $parts['query'] : '', $query );
		$entry = array(
			'type'  => 'article',
			'href'  => $url,
			'video' => '',
			'list'  => '',
			'src'   => '',
			'host'  => $host,
		);

		$video = '';
		$list  = isset( $query['list'] ) && is_string( $query['list'] ) ? $query['list'] : '';
		if ( in_array( $host, array( 'youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtube-nocookie.com' ), true ) ) {
			if ( isset( $query['v'] ) && is_string( $query['v'] ) ) {
				$video = $query['v'];
			} elseif ( preg_match( '#^/(?:embed|shorts|live|v)/([A-Za-z0-9_-]{11})(?:[/?]|$)#', $path, $m ) ) {
				$video = $m[1];
			}
		} elseif ( 'youtu.be' === $host && preg_match( '#^/([A-Za-z0-9_-]{11})$#', $path, $m ) ) {
			$video = $m[1];
		} else {
			$list = '';
		}
		$video = preg_match( '/^[A-Za-z0-9_-]{11}$/D', $video ) ? $video : '';
		$list  = preg_match( '/^[A-Za-z0-9_-]{12,64}$/D', $list ) ? $list : '';
		if ( '' !== $video || '' !== $list ) {
			if ( '' === $type || 'video' === $type ) {
				$entry['type']  = 'video';
				$entry['video'] = $video;
				$entry['list']  = $list;
				$entry['href']  = '' !== $video
					? 'https://www.youtube.com/watch?v=' . $video . ( '' !== $list ? '&list=' . $list : '' )
					: 'https://www.youtube.com/playlist?list=' . $list;
			}
			return $entry;
		}

		// Anchor (Spotify for Podcasters) play links wrap the real audio file URL.
		if ( 'anchor.fm' === $host && preg_match( '#/podcast/play/[0-9]+/(https?%3A%2F%2F[^/]+)$#i', $path, $m ) ) {
			$inner = self::detect( rawurldecode( $m[1] ) );
			if ( $inner && 'audio' === $inner['type'] ) {
				return '' === $type || 'audio' === $type ? $inner : $entry;
			}
		}
		if ( ( '' === $type && preg_match( '/\.(?:mp3|m4a|aac|ogg|oga|opus|wav|flac)$/iD', $path ) ) || 'audio' === $type ) {
			$entry['type'] = 'audio';
			$entry['src']  = $url;
		}
		return $entry;
	}

	/** A link item as a list entry, or null when its URL is unusable. */
	public static function item_entry( $post ) {
		$type  = (string) get_post_meta( $post->ID, '_bnav_type', true );
		$entry = self::detect( (string) get_post_meta( $post->ID, '_bnav_url', true ), in_array( $type, self::TYPES, true ) ? $type : '' );
		if ( ! $entry ) {
			return null;
		}
		$link           = (string) get_post_meta( $post->ID, '_bnav_link', true );
		$entry['title'] = self::clean_title( $post->post_title );
		$entry['link']  = self::detect( $link ) ? $link : '';
		$entry['order'] = (int) $post->menu_order;
		$entry['id']    = (int) $post->ID;
		$entry['local'] = false;
		return $entry;
	}

	public static function clean_title( $title ) {
		$title = trim( wp_strip_all_tags( html_entity_decode( (string) $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $title, 0, 200 ) : substr( $title, 0, 200 );
	}

	/* ------------------------------------------------------------------ cache */

	public static function flush() {
		update_option( self::CACHE, (int) get_option( self::CACHE, 0 ) + 1, true );
	}

	public static function flush_for_post( $post_id ) {
		$type = get_post_type( $post_id );
		if ( self::ITEM === $type || ( in_array( $type, self::post_types(), true ) && has_term( '', self::BOOK, $post_id ) ) ) {
			self::flush();
		}
	}

	public static function flush_for_terms( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( self::BOOK === $taxonomy ) {
			self::flush();
		}
	}

	/* -------------------------------------------------------------- front end */

	public static function register_block() {
		wp_register_style( 'bible-navigation', plugins_url( 'assets/navigation.css', BNAV_FILE ), array(), BNAV_VERSION );
		wp_register_script( 'bible-navigation', plugins_url( 'assets/navigation.js', BNAV_FILE ), array(), BNAV_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_register_script( 'bible-navigation-block', plugins_url( 'assets/block.js', BNAV_FILE ), array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-server-side-render' ), BNAV_VERSION, true );
		wp_add_inline_script(
			'bible-navigation-block',
			'window.bnavBlock = ' . wp_json_encode(
				array(
					'title'       => __( 'Bible navigation', 'wp-bible-navigation' ),
					'description' => __( 'Bible books with their videos, audio and articles.', 'wp-bible-navigation' ),
					'showEmpty'   => __( 'Show books without links', 'wp-bible-navigation' ),
				)
			) . ';',
			'before'
		);
		register_block_type(
			'bible-navigation/books',
			array(
				'api_version'         => 3,
				'editor_script_handles' => array( 'bible-navigation-block' ),
				'style_handles'       => array( 'bible-navigation' ),
				'view_script_handles' => array( 'bible-navigation' ),
				'attributes'          => array(
					'showEmpty' => array( 'type' => 'boolean', 'default' => true ),
				),
				'supports'            => array( 'align' => array( 'wide', 'full' ), 'html' => false ),
				'render_callback'     => array( __CLASS__, 'render_block' ),
			)
		);
	}

	public static function render_block( $attributes ) {
		return self::render( ! isset( $attributes['showEmpty'] ) || (bool) $attributes['showEmpty'] );
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'show_empty' => '1' ), $atts, 'bible_navigation' );
		wp_enqueue_style( 'bible-navigation' );
		wp_enqueue_script( 'bible-navigation' );
		return self::render( ! in_array( strtolower( (string) $atts['show_empty'] ), array( '0', 'no', 'false' ), true ) );
	}

	/** The list HTML; cached until a link, a book or a tagged post changes. */
	public static function render( $show_empty = true ) {
		$key  = 'bnav_html_' . BNAV_VERSION . '_' . (int) get_option( self::CACHE, 0 ) . '_' . ( $show_empty ? 1 : 0 ) . '_' . sanitize_key( determine_locale() );
		$html = get_transient( $key );
		if ( ! is_string( $html ) ) {
			$html = self::build( self::entries(), $show_empty );
			set_transient( $key, $html, DAY_IN_SECONDS );
		}
		return $html;
	}

	/** Book slug => sorted entries, from published link items and tagged posts. */
	public static function entries() {
		$by_book = array();
		$items   = get_posts(
			array(
				'post_type'        => self::ITEM,
				'post_status'      => 'publish',
				'numberposts'      => self::MAX,
				'orderby'          => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ),
				'no_found_rows'    => true,
			)
		);
		foreach ( $items as $post ) {
			$entry = self::item_entry( $post );
			if ( $entry ) {
				self::add_entry( $by_book, $post, $entry );
			}
		}
		$types = self::post_types();
		$posts = $types ? get_posts(
			array(
				'post_type'              => $types,
				'post_status'            => 'publish',
				'has_password'           => false,
				'numberposts'            => self::MAX,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Cached; runs only after a change.
				'tax_query'              => array(
					array(
						'taxonomy' => self::BOOK,
						'operator' => 'EXISTS',
					),
				),
			)
		) : array();
		foreach ( $posts as $i => $post ) {
			$entry = array(
				'type'  => 'article',
				'href'  => get_permalink( $post ),
				'video' => '',
				'list'  => '',
				'src'   => '',
				'host'  => '',
				'title' => self::clean_title( get_the_title( $post ) ),
				'link'  => '',
				'order' => PHP_INT_MAX - count( $posts ) + $i,
				'id'    => (int) $post->ID,
				'local' => true,
			);
			self::add_entry( $by_book, $post, $entry );
		}
		foreach ( $by_book as $slug => $list ) {
			usort( $list, array( __CLASS__, 'compare' ) );
			$by_book[ $slug ] = $list;
		}
		return $by_book;
	}

	private static function add_entry( array &$by_book, $post, array $entry ) {
		$terms = get_the_terms( $post, self::BOOK );
		if ( ! is_array( $terms ) || '' === $entry['title'] ) {
			return;
		}
		foreach ( $terms as $term ) {
			$by_book[ $term->slug ][] = $entry;
		}
	}

	/**
	 * Videos, then audio, then articles; then by the Order field, then in the
	 * order they were added (page order for an import, line order for Quick
	 * add). Titles are not sorted: a series like "… | Laiškas efeziečiams #3"
	 * has its number at the end.
	 */
	public static function compare( $a, $b ) {
		$rank = array_flip( self::TYPES );
		if ( $rank[ $a['type'] ] !== $rank[ $b['type'] ] ) {
			return $rank[ $a['type'] ] - $rank[ $b['type'] ];
		}
		if ( $a['order'] !== $b['order'] ) {
			return $a['order'] < $b['order'] ? -1 : 1;
		}
		return $a['id'] - $b['id'];
	}

	/** Contents (every book, with counts) followed by each book's collapsible list. */
	public static function build( array $by_book, $show_empty = true ) {
		$toc     = '';
		$content = '';
		foreach ( Bible_Navigation_Books::tree() as $testament => $t ) {
			$toc_groups     = '';
			$content_groups = '';
			foreach ( $t['groups'] as $group => $g ) {
				$chips = '';
				$books = '';
				foreach ( $g['books'] as $slug => $name ) {
					$list  = isset( $by_book[ $slug ] ) ? $by_book[ $slug ] : array();
					$count = count( $list );
					if ( $count ) {
						$chips .= '<li><a class="bnav-chip" href="#' . esc_attr( $slug ) . '">' . esc_html( $name ) . ' <span class="bnav-count">' . $count . '</span></a></li>';
						$books .= self::book( $slug, $name, $group, $list );
					} elseif ( $show_empty ) {
						$chips .= '<li><span class="bnav-chip is-empty">' . esc_html( $name ) . '</span></li>';
					}
				}
				if ( '' !== $chips ) {
					$toc_groups .= '<h3 class="bnav-group-title" id="' . esc_attr( $group . '-turinys' ) . '">' . esc_html( $g['name'] ) . '</h3><ul class="bnav-chips">' . $chips . '</ul>';
				}
				if ( '' !== $books ) {
					$content_groups .= '<h3 class="bnav-group-title" id="' . esc_attr( $group ) . '">' . esc_html( $g['name'] ) . '</h3>' . $books;
				}
			}
			if ( '' !== $toc_groups ) {
				$toc .= '<div class="bnav-testament"><h2 class="bnav-testament-title" id="' . esc_attr( $testament . '-turinys' ) . '">' . esc_html( $t['name'] ) . '</h2>' . $toc_groups . '</div>';
			}
			if ( '' !== $content_groups ) {
				$content .= '<h2 class="bnav-testament-title" id="' . esc_attr( $testament ) . '">' . esc_html( $t['name'] ) . '</h2>' . $content_groups;
			}
		}
		if ( '' === $content ) {
			$content = '<p class="bnav-none">' . esc_html__( 'No links have been added yet.', 'wp-bible-navigation' ) . '</p>';
		}
		return '<div class="bnav"><nav class="bnav-toc" aria-label="' . esc_attr__( 'Bible books', 'wp-bible-navigation' ) . '">' . $toc . '</nav><div class="bnav-books">' . $content . '</div></div>';
	}

	private static function book( $slug, $name, $group, array $list ) {
		$labels = array(
			'video'   => __( 'Videos', 'wp-bible-navigation' ),
			'audio'   => __( 'Audio recordings', 'wp-bible-navigation' ),
			'article' => __( 'Articles', 'wp-bible-navigation' ),
		);
		$sections = array();
		foreach ( $list as $entry ) {
			$sections[ $entry['type'] ][] = $entry;
		}
		$body = '';
		foreach ( $sections as $type => $entries ) {
			$body .= '<p class="bnav-type">' . esc_html( $labels[ $type ] ) . ' <span class="bnav-type-count">' . count( $entries ) . '</span></p><ul class="bnav-list">';
			foreach ( $entries as $entry ) {
				$body .= '<li class="bnav-item is-' . esc_attr( $type ) . '">' . self::entry_html( $entry ) . '</li>';
			}
			$body .= '</ul>';
		}
		$body .= '<p class="bnav-back"><a href="#' . esc_attr( $group . '-turinys' ) . '">' . esc_html__( 'Back to the books', 'wp-bible-navigation' ) . '</a></p>';
		return '<details class="bnav-book" id="' . esc_attr( $slug ) . '"><summary><h4 class="bnav-book-title">' . esc_html( $name ) . '</h4> <span class="bnav-count">' . count( $list ) . '</span></summary><div class="bnav-book-body">' . $body . '</div></details>';
	}

	/** One entry: videos and audio play in place (links without JavaScript); articles are links. */
	private static function entry_html( array $entry ) {
		$title = esc_html( $entry['title'] );
		if ( 'video' === $entry['type'] ) {
			return '<a class="bnav-open" href="' . esc_url( $entry['href'] ) . '" data-video="' . esc_attr( $entry['video'] ) . '" data-list="' . esc_attr( $entry['list'] ) . '" aria-expanded="false">' . $title . '</a>';
		}
		if ( 'audio' === $entry['type'] ) {
			// The player waits, inert, in a <template> until the title is clicked.
			$html = '<a class="bnav-open" href="' . esc_url( $entry['src'] ) . '" data-audio="1" aria-expanded="false">' . $title . '</a>'
				. '<template class="bnav-audio"><audio controls preload="none" src="' . esc_url( $entry['src'] ) . '"></audio></template>';
			if ( '' !== $entry['link'] ) {
				$html .= ' <a class="bnav-source" href="' . esc_url( $entry['link'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'source', 'wp-bible-navigation' ) . '</a>';
			}
			return $html;
		}
		if ( $entry['local'] ) {
			return '<a href="' . esc_url( $entry['href'] ) . '">' . $title . '</a>';
		}
		return '<a href="' . esc_url( $entry['href'] ) . '" target="_blank" rel="noopener">' . $title . '</a> <span class="bnav-host">' . esc_html( $entry['host'] ) . '</span>';
	}

	/* ------------------------------------------------------------ titles */

	/** Title of a page or video from the web: oEmbed (YouTube, WordPress sites), then <title>; '' if none. */
	public static function fetch_title( $url ) {
		$entry = self::detect( $url );
		if ( ! $entry || 'audio' === $entry['type'] ) {
			return '';
		}
		$data = _wp_oembed_get_object()->get_data( $entry['href'], array( 'discover' => 'video' !== $entry['type'] ) );
		if ( is_object( $data ) && ! empty( $data->title ) && is_string( $data->title ) ) {
			return self::clean_title( $data->title );
		}
		if ( 'article' === $entry['type'] ) {
			$response = wp_safe_remote_get( $entry['href'], array( 'timeout' => 5, 'limit_response_size' => 150000 ) );
			$body     = is_wp_error( $response ) ? '' : (string) wp_remote_retrieve_body( $response );
			if ( preg_match( '#<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)#i', $body, $m ) || preg_match( '#<title[^>]*>(.*?)</title>#is', $body, $m ) ) {
				$site = preg_match( '#<meta[^>]+property=["\']og:site_name["\'][^>]+content=["\']([^"\']+)#i', $body, $s ) ? self::clean_title( $s[1] ) : '';
				return self::strip_site_name( self::clean_title( $m[1] ), $site );
			}
		}
		return '';
	}

	/** "Pradžia (2022/II) – Sabatos Biblijos mokykla" → "Pradžia (2022/II)" when the site name is known. */
	public static function strip_site_name( $title, $site ) {
		if ( '' === $site ) {
			return $title;
		}
		foreach ( array( ' – ', ' — ', ' - ', ' | ', ' · ' ) as $separator ) {
			$suffix = $separator . $site;
			if ( strlen( $title ) > strlen( $suffix ) && substr( $title, -strlen( $suffix ) ) === $suffix ) {
				return trim( substr( $title, 0, -strlen( $suffix ) ) );
			}
		}
		return $title;
	}

	/** A stand-in title from the URL itself ("YouTube <id>", the file name or the site). */
	public static function fallback_title( $url ) {
		$entry = self::detect( $url );
		if ( ! $entry ) {
			return '';
		}
		if ( 'video' === $entry['type'] ) {
			return 'YouTube ' . ( '' !== $entry['video'] ? $entry['video'] : $entry['list'] );
		}
		$parts = wp_parse_url( 'audio' === $entry['type'] ? $entry['src'] : $entry['href'] );
		$name  = isset( $parts['path'] ) ? rawurldecode( basename( $parts['path'] ) ) : '';
		return self::clean_title( '' !== $name ? $name : $entry['host'] );
	}
}
