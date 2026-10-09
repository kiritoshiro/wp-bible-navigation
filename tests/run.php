<?php
/* Small WordPress stubs exercise link detection, escaped output, the page importer and release gating. */
define( 'ABSPATH', __DIR__ );
define( 'BNAV_VERSION', '0.1.0' );
define( 'BNAV_FILE', __DIR__ . '/../wp-bible-navigation.php' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['blocks'] = array();
$GLOBALS['release'] = null;
function wp_parse_url( $url ) { return parse_url( $url ); }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $v ) { return preg_match( '#^https?://#i', (string) $v ) ? str_replace( '&', '&#038;', htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ) ) : ''; }
function sanitize_url( $v ) { return trim( (string) $v ); }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_key( $v ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $v ) ); }
function __( $v, $domain ) { return $v; }
function esc_html__( $v, $domain ) { return esc_html( $v ); }
function esc_attr__( $v, $domain ) { return esc_attr( $v ); }
function apply_filters( $hook, $value ) { return $value; }
function parse_blocks( $content ) { return $GLOBALS['blocks']; }
function shortcode_parse_atts( $text ) {
	preg_match_all( '/(\w+)\s*=\s*"([^"]*)"/', (string) $text, $m, PREG_SET_ORDER );
	$atts = array();
	foreach ( $m as $pair ) {
		$atts[ strtolower( $pair[1] ) ] = $pair[2];
	}
	return $atts;
}
function get_site_transient( $key ) { return $GLOBALS['release']; }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
class WP_Error { public $code; public function __construct( $code ) { $this->code = $code; } }
function download_url( $url, $timeout ) { $file = tempnam( sys_get_temp_dir(), 'bnav' ); file_put_contents( $file, 'tampered zip' ); return $file; }
function wp_delete_file( $file ) { unlink( $file ); }

$failures = 0;
function check( $condition, $message ) {
	global $failures;
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: $message\n" );
		++$failures;
	}
}

require __DIR__ . '/../includes/class-books.php';
require __DIR__ . '/../includes/class-bible-navigation.php';
require __DIR__ . '/../includes/class-admin.php';
require __DIR__ . '/../includes/class-updater.php';

/* ---- books */
$books = Bible_Navigation_Books::books();
check( 66 === count( $books ), '66 books' );
check( 1 === $books['pradzios-knyga']['position'] && 66 === $books['apreiskimas-jonui']['position'], 'canonical order' );
check( 'penkiaknyge' === $books['pradzios-knyga']['group'] && 'naujasis-testamentas' === $books['laiskas-efezieciams']['testament'], 'groups and testaments' );
check( 'pradzios-knyga' === Bible_Navigation_Books::find( 'Pradžios knyga' ), 'find by name' );
check( 'pradzios-knyga' === Bible_Navigation_Books::find( ' <a href="#x">PRADŽIOS  knyga</a> ' ), 'find by name in heading HTML' );
check( 'pradzios-knyga' === Bible_Navigation_Books::find( 'pradzios-knyga' ), 'find by slug' );
check( '' === Bible_Navigation_Books::find( 'Penkiaknygė' ), 'a group is not a book' );

/* ---- link detection */
$d = Bible_Navigation::detect( 'https://www.youtube.com/watch?v=C2qkbYzbnOM&list=PLjcvwNsWxJC51yIeFo9i4nwSac_SsDNfB&index=3' );
check( 'video' === $d['type'] && 'C2qkbYzbnOM' === $d['video'] && 'PLjcvwNsWxJC51yIeFo9i4nwSac_SsDNfB' === $d['list'], 'video inside a playlist keeps its own video ID' );
$d = Bible_Navigation::detect( 'https://youtu.be/TQ-MzWRJNTU' );
check( 'video' === $d['type'] && 'TQ-MzWRJNTU' === $d['video'], 'youtu.be' );
$d = Bible_Navigation::detect( 'https://www.youtube.com/shorts/TQ-MzWRJNTU' );
check( 'TQ-MzWRJNTU' === $d['video'], 'shorts URL' );
$d = Bible_Navigation::detect( 'https://www.youtube.com/playlist?list=PLjcvwNsWxJC51yIeFo9i4nwSac_SsDNfB' );
check( 'video' === $d['type'] && '' === $d['video'] && 0 === strpos( $d['href'], 'https://www.youtube.com/playlist?list=' ), 'playlist only' );
$d = Bible_Navigation::detect( 'https://youtube.com.evil.test/watch?v=TQ-MzWRJNTU' );
check( 'article' === $d['type'] && '' === $d['video'], 'spoofed YouTube host is an ordinary link' );
$d = Bible_Navigation::detect( 'https://www.youtube.com/watch?v=../../x"y' );
check( 'article' === $d['type'], 'invalid video ID rejected' );
check( null === Bible_Navigation::detect( 'javascript:alert(1)' ), 'javascript: rejected' );
check( null === Bible_Navigation::detect( '//example.test/x' ) && null === Bible_Navigation::detect( 'not a url' ), 'relative and junk rejected' );
$d = Bible_Navigation::detect( 'https://anchor.fm/s/430eecd0/podcast/play/24615560/https%3A%2F%2Fd3ctxlq1ktw2nl.cloudfront.net%2Fstaging%2F2020-11-31%2F141966201-44100-2-0ad37d36b2ef7.m4a' );
check( 'audio' === $d['type'] && 'https://d3ctxlq1ktw2nl.cloudfront.net/staging/2020-11-31/141966201-44100-2-0ad37d36b2ef7.m4a' === $d['src'], 'anchor play link decoded to the audio file' );
$d = Bible_Navigation::detect( 'https://d3ctxlq1ktw2nl.cloudfront.net/staging/2020-11-30/141802389-44100-2-22405359e0eb4.m4a' );
check( 'audio' === $d['type'], 'm4a is audio' );
$d = Bible_Navigation::detect( 'https://dievozodis.lt/pradzia-2022-ii/' );
check( 'article' === $d['type'] && 'dievozodis.lt' === $d['host'], 'dievozodis article' );
check( 'audio' === Bible_Navigation::detect( 'https://example.test/stream', 'audio' )['type'], 'type can be forced to audio' );
check( 'article' === Bible_Navigation::detect( 'https://youtu.be/TQ-MzWRJNTU', 'article' )['type'], 'type can be forced to article' );
check( 'Pradžia' === Bible_Navigation::clean_title( '<b>Pradžia</b>' ), 'title stripped' );
check( 'Trys kosminės žinios (2023/II)' === Bible_Navigation::strip_site_name( 'Trys kosminės žinios (2023/II) – Sabatos Biblijos mokykla', 'Sabatos Biblijos mokykla' ), 'site name removed from a fetched title' );
check( 'A – B' === Bible_Navigation::strip_site_name( 'A – B', '' ) && 'Sabatos Biblijos mokykla' === Bible_Navigation::strip_site_name( 'Sabatos Biblijos mokykla', 'Sabatos Biblijos mokykla' ), 'title kept when nothing to strip' );
check( 'YouTube C2qkbYzbnOM' === Bible_Navigation::fallback_title( 'https://www.youtube.com/watch?v=C2qkbYzbnOM' ), 'stand-in video title' );
check( 'a b.m4a' === Bible_Navigation::fallback_title( 'https://example.test/x/a%20b.m4a' ), 'stand-in audio title' );
check( 'pradzia-2022-ii' === Bible_Navigation::fallback_title( 'https://dievozodis.lt/pradzia-2022-ii/' ), 'stand-in article title' );

/* ---- quick add lines */
$line = Bible_Navigation_Admin::parse_line( 'Pradžia (2022/II) | https://dievozodis.lt/pradzia-2022-ii/' );
check( 'Pradžia (2022/II)' === $line['title'] && 'https://dievozodis.lt/pradzia-2022-ii/' === $line['url'], 'Title | URL' );
check( null === Bible_Navigation_Admin::parse_line( 'just words' ), 'line without URL rejected' );
$line = Bible_Navigation_Admin::parse_line( 'https://anchor.fm/s/1/podcast/play/2/https%3A%2F%2Fexample.test%2Fa.m4a' );
check( false !== strpos( $line['url'], '%3A%2F%2F' ), 'percent escapes kept in pasted URLs' );

/* ---- sorting */
$e = static function ( $type, $title, $id, $order = 0 ) {
	return array( 'type' => $type, 'title' => $title, 'id' => $id, 'order' => $order );
};
$list = array( $e( 'article', 'A', 1 ), $e( 'video', 'Kaip Dievas mus gelbsti | #4', 9 ), $e( 'audio', 'B', 2 ), $e( 'video', 'Paulius ir efeziečiai | #1', 3 ), $e( 'video', 'Pinned', 50, -1 ) );
usort( $list, array( 'Bible_Navigation', 'compare' ) );
check( array( 'Pinned', 'Paulius ir efeziečiai | #1', 'Kaip Dievas mus gelbsti | #4', 'B', 'A' ) === array_column( $list, 'title' ), 'videos, audio, articles; Order field, then the order added' );

/* ---- rendered list */
$entry = static function ( $url, $title, $extra = array() ) {
	return array_merge( Bible_Navigation::detect( $url ), array( 'title' => $title, 'link' => '', 'order' => 0, 'id' => 1, 'local' => false ), $extra );
};
$html = Bible_Navigation::build(
	array(
		'pradzios-knyga'      => array(
			$entry( 'https://www.youtube.com/watch?v=C2qkbYzbnOM&list=PLjcvwNsWxJC51yIeFo9i4nwSac_SsDNfB', '<script>alert(1)</script> Sukūrimas' ),
			$entry( 'https://anchor.fm/s/430eecd0/podcast/play/24615560/https%3A%2F%2Fexample.test%2Fa.m4a', 'Pradžioje', array( 'link' => 'https://podcasters.spotify.com/x' ) ),
			$entry( 'https://dievozodis.lt/pradzia-2022-ii/', 'Pradžia (2022/II)' ),
		),
		'laiskas-efezieciams' => array( $entry( 'https://example.test/post/', 'Vietinis įrašas', array( 'local' => true ) ) ),
	)
);
check( false === strpos( $html, '<script>' ) && false !== strpos( $html, '&lt;script&gt;' ), 'titles escaped' );
check( false === strpos( $html, '<iframe' ) && false === strpos( $html, '<audio' ), 'no player or third-party request before a click' );
check( false !== strpos( $html, 'href="#pradzios-knyga">Pradžios knyga <span class="bnav-count">3</span>' ), 'book count in contents' );
check( false !== strpos( $html, '<details class="bnav-book" id="pradzios-knyga">' ), 'old anchor kept as the book id' );
check( false !== strpos( $html, 'id="penkiaknyge-turinys"' ) && false !== strpos( $html, 'id="senasis-testamentas"' ), 'old group and testament anchors kept' );
check( false !== strpos( $html, '<span class="bnav-chip is-empty">Teisėjų knyga</span>' ), 'empty book shown, not linked' );
check( false !== strpos( $html, 'data-video="C2qkbYzbnOM" data-list="PLjcvwNsWxJC51yIeFo9i4nwSac_SsDNfB"' ), 'video data' );
check( false !== strpos( $html, 'data-audio="https://example.test/a.m4a"' ), 'audio data' );
check( false !== strpos( $html, '<span class="bnav-host">dievozodis.lt</span>' ), 'external article shows its site' );
check( false !== strpos( $html, '<li class="bnav-item is-article"><a href="https://example.test/post/">Vietinis įrašas</a></li>' ), 'local article: same tab, no site label' );
check( false !== strpos( $html, 'class="bnav-source" href="https://podcasters.spotify.com/x" target="_blank" rel="noopener"' ), 'audio source link' );
$lean = Bible_Navigation::build( array( 'pradzios-knyga' => array( $entry( 'https://youtu.be/TQ-MzWRJNTU', 'V' ) ) ), false );
check( false === strpos( $lean, 'Teisėjų knyga' ) && false === strpos( $lean, 'Naujasis Testamentas' ), 'empty books and testaments hidden when asked' );
check( false !== strpos( Bible_Navigation::build( array() ), 'bnav-none' ), 'empty state' );

/* ---- page importer */
$heading = static function ( $text, $anchor = '' ) {
	return array( 'blockName' => 'core/heading', 'attrs' => $anchor ? array( 'anchor' => $anchor ) : array(), 'innerHTML' => '<h4 class="wp-block-heading">' . $text . '</h4>', 'innerBlocks' => array() );
};
$embed = static function ( $url, $caption = '' ) {
	return array( 'blockName' => 'core/embed', 'attrs' => array( 'url' => $url ), 'innerHTML' => '<figure class="wp-block-embed"><div class="wp-block-embed__wrapper">' . $url . '</div>' . $caption . '</figure>', 'innerBlocks' => array() );
};
$columns = static function ( array $inner ) {
	return array( 'blockName' => 'core/columns', 'attrs' => array(), 'innerHTML' => '', 'innerBlocks' => array( array( 'blockName' => 'core/column', 'attrs' => array(), 'innerHTML' => '', 'innerBlocks' => $inner ) ) );
};
$GLOBALS['blocks'] = array(
	array( 'blockName' => 'core/table', 'attrs' => array(), 'innerHTML' => '<table><tr><td><a href="https://adventistai.lt/x">TOC</a></td></tr></table>', 'innerBlocks' => array() ),
	$heading( 'Senasis Testamentas' ),
	$heading( 'Penkiaknygė' ),
	$heading( '<a href="#penkiaknyge-turinys">Pradžios knyga</a>', 'pradzios-knyga' ),
	$columns(
		array(
			$embed( 'https://www.youtube.com/watch?v=C2qkbYzbnOM&amp;list=PLjcvwNsWxJC51yIeFo9i4nwSac_SsDNfB' ),
			$embed( 'https://anchor.fm/s/430eecd0/podcast/play/24615560/https%3A%2F%2Fexample.test%2Fa.m4a', '<figcaption class="wp-element-caption"><a href="https://anchor.fm/algimantas/episodes/Pradioje-eocre5">Podkastas 5 minutės: Pradžioje..</a></figcaption>' ),
			$embed( 'https://dievozodis.lt/pradzia-2022-ii/' ),
			$embed( 'https://dievozodis.lt/pradzia-2022-ii/' ),
		)
	),
	array( 'blockName' => 'core/paragraph', 'attrs' => array(), 'innerHTML' => '<p>Daugiau: <a href="https://dievozodis.lt/kitas/">Kitas straipsnis</a> ir <a href="#isejimo-knyga">vidinė</a></p>', 'innerBlocks' => array() ),
	array( 'blockName' => 'core/shortcode', 'attrs' => array(), 'innerHTML' => '[embedyt] https://www.youtube.com/watch?v=TQ-MzWRJNTU[/embedyt]', 'innerBlocks' => array() ),
	$heading( 'Istorinės knygos' ),
	$embed( 'https://www.youtube.com/watch?v=WPoGf6RPdDk' ),
	$heading( 'Laiškas efeziečiams' ),
	array( 'blockName' => null, 'attrs' => array(), 'innerHTML' => "\n<p>https://youtu.be/WPoGf6RPdDk</p>\n", 'innerBlocks' => array() ),
	array( 'blockName' => 'core/audio', 'attrs' => array(), 'innerHTML' => '<figure class="wp-block-audio"><audio controls src="https://example.test/b.mp3"></audio><figcaption>Garsas</figcaption></figure>', 'innerBlocks' => array() ),
);
$found = Bible_Navigation_Admin::collect( 'ignored by the stub' );
$by    = array();
foreach ( $found as $item ) {
	$by[ $item['book'] ][] = $item;
}
check( 7 === count( $found ), 'importer finds 7 distinct links (got ' . count( $found ) . ')' );
check( 5 === count( $by['pradzios-knyga'] ) && 2 === count( $by['laiskas-efezieciams'] ), 'links under the right books' );
check( 'https://www.youtube.com/watch?v=C2qkbYzbnOM&list=PLjcvwNsWxJC51yIeFo9i4nwSac_SsDNfB' === $by['pradzios-knyga'][0]['url'], 'embed URL entity-decoded' );
check( 'Podkastas 5 minutės: Pradžioje..' === $by['pradzios-knyga'][1]['title'] && 'https://anchor.fm/algimantas/episodes/Pradioje-eocre5' === $by['pradzios-knyga'][1]['link'], 'caption title and source link' );
check( 'Kitas straipsnis' === $by['pradzios-knyga'][3]['title'], 'link in text with its title' );
check( 'https://www.youtube.com/watch?v=TQ-MzWRJNTU' === $by['pradzios-knyga'][4]['url'], '[embedyt] shortcode' );
check( 'Garsas' === $by['laiskas-efezieciams'][1]['title'], 'audio block with caption' );
check( ! in_array( 'https://www.youtube.com/watch?v=WPoGf6RPdDk', array_column( $found, 'url' ), true ), 'links under a group heading are ignored' );

/* ---- translations: every PHP string has a Lithuanian translation */
$l10n = include __DIR__ . '/../languages/wp-bible-navigation-lt_LT.l10n.php';
foreach ( glob( __DIR__ . '/../includes/*.php' ) as $file ) {
	preg_match_all( "/(?:__|esc_html__|esc_attr__)\(\s*'((?:[^'\\\\]|\\\\.)*)'\s*,\s*'wp-bible-navigation'/", file_get_contents( $file ), $m );
	foreach ( $m[1] as $string ) {
		check( isset( $l10n['messages'][ stripslashes( $string ) ] ), 'Lithuanian translation for: ' . $string );
	}
}
check( count( $l10n['messages'] ) > 50, 'translation file loaded' );

/* ---- updater */
$asset   = 'wp-bible-navigation-0.2.0.zip';
$release = array(
	'tag_name' => 'v0.2.0',
	'assets'   => array(
		array(
			'name'                 => $asset,
			'state'                => 'uploaded',
			'browser_download_url' => 'https://github.com/kiritoshiro/wp-bible-navigation/releases/download/v0.2.0/' . $asset,
			'digest'               => 'sha256:' . str_repeat( 'a', 64 ),
		),
	),
);
$parsed = Bible_Navigation_Updater::parse( $release );
check( '0.2.0' === $parsed['version'] && str_repeat( 'a', 64 ) === $parsed['digest'], 'release parsed' );
$bad = $release;
$bad['assets'][0]['browser_download_url'] = 'https://evil.test/' . $asset;
check( null === Bible_Navigation_Updater::parse( $bad ), 'foreign download host rejected' );
$bad = $release;
$bad['prerelease'] = true;
check( null === Bible_Navigation_Updater::parse( $bad ), 'prerelease ignored' );
$GLOBALS['release'] = $parsed;
check( Bible_Navigation_Updater::download( false, $parsed['package'], null ) instanceof WP_Error, 'tampered download rejected' );

if ( $failures ) {
	fwrite( STDERR, "$failures check(s) failed\n" );
	exit( 1 );
}
echo "All checks passed.\n";
