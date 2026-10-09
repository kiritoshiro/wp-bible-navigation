<?php
/**
 * The 66 books of the Bible, in canonical order, grouped as on the original
 * adventistai.lt page. Slugs are the page's old anchors, so links such as
 * /biblijos-tyrinejimai/#pradzios-knyga keep working.
 */
defined( 'ABSPATH' ) || exit;

final class Bible_Navigation_Books {

	/** Testament slug => name, groups (group slug => name, books (book slug => name)). */
	public static function tree() {
		return array(
			'senasis-testamentas' => array(
				'name'   => 'Senasis Testamentas',
				'groups' => array(
					'penkiaknyge' => array(
						'name'  => 'Penkiaknygė',
						'books' => array(
							'pradzios-knyga' => 'Pradžios knyga',
							'isejimo-knyga' => 'Išėjimo knyga',
							'kunigu-knyga' => 'Kunigų knyga',
							'skaiciu-knyga' => 'Skaičių knyga',
							'pakartoto-istatymo-knyga' => 'Pakartoto Įstatymo knyga',
						),
					),
					'istorines-knygos' => array(
						'name'  => 'Istorinės knygos',
						'books' => array(
							'jozues-knyga' => 'Jozuės knyga',
							'teiseju-knyga' => 'Teisėjų knyga',
							'rutos-knyga' => 'Rūtos knyga',
							'samuelio-pirma-knyga' => 'Samuelio pirma knyga',
							'samuelio-antra-knyga' => 'Samuelio antra knyga',
							'karaliu-pirma-knyga' => 'Karalių pirma knyga',
							'karaliu-antra-knyga' => 'Karalių antra knyga',
							'metrasciu-pirma-knyga' => 'Metraščių pirma knyga',
							'metrasciu-antra-knyga' => 'Metraščių antra knyga',
							'ezros-knyga' => 'Ezros knyga',
							'nehemijo-knyga' => 'Nehemijo knyga',
							'esteros-knyga' => 'Esteros knyga',
						),
					),
					'isminties-knygos' => array(
						'name'  => 'Išminties knygos',
						'books' => array(
							'jobo-knyga' => 'Jobo knyga',
							'psalmynas' => 'Psalmynas',
							'patarliu-knyga' => 'Patarlių knyga',
							'mokytojo-knyga' => 'Mokytojo knyga',
							'giesmiu-giesmes-knyga' => 'Giesmių Giesmės knyga',
						),
					),
					'didziuju-pranasu-knygos' => array(
						'name'  => 'Didžiųjų pranašų knygos',
						'books' => array(
							'izaijo-knyga' => 'Izaijo knyga',
							'jeremijo-knyga' => 'Jeremijo knyga',
							'raudu-knyga' => 'Raudų knyga',
							'ezechielio-knyga' => 'Ezechielio knyga',
							'danieliaus-knyga' => 'Danieliaus knyga',
						),
					),
					'mazuju-pranasu-knygos' => array(
						'name'  => 'Mažųjų pranašų knygos',
						'books' => array(
							'ozejo-knyga' => 'Ozėjo knyga',
							'joelio-knyga' => 'Joelio knyga',
							'amoso-knyga' => 'Amoso knyga',
							'abdijo-knyga' => 'Abdijo knyga',
							'jonos-knyga' => 'Jonos knyga',
							'michejo-knyga' => 'Michėjo knyga',
							'nahumo-knyga' => 'Nahumo knyga',
							'habakuko-knyga' => 'Habakuko knyga',
							'sofonijo-knyga' => 'Sofonijo knyga',
							'agejo-knyga' => 'Agėjo knyga',
							'zacharijo-knyga' => 'Zacharijo knyga',
							'malachijo-knyga' => 'Malachijo knyga',
						),
					),
				),
			),
			'naujasis-testamentas' => array(
				'name'   => 'Naujasis Testamentas',
				'groups' => array(
					'istorines-knygos-nt' => array(
						'name'  => 'Istorinės knygos',
						'books' => array(
							'evangelija-pagal-mata' => 'Evangelija pagal Matą',
							'evangelija-pagal-morku' => 'Evangelija pagal Morkų',
							'evangelija-pagal-luka' => 'Evangelija pagal Luką',
							'evangelija-pagal-jona' => 'Evangelija pagal Joną',
							'apastalu-darbai' => 'Apaštalų darbai',
						),
					),
					'laiskai' => array(
						'name'  => 'Laiškai',
						'books' => array(
							'laiskas-romieciams' => 'Laiškas romiečiams',
							'pirmas-laiskas-korintieciams' => 'Pirmas laiškas korintiečiams',
							'antras-laiskas-korintieciams' => 'Antras laiškas korintiečiams',
							'laiskas-galatams' => 'Laiškas galatams',
							'laiskas-efezieciams' => 'Laiškas efeziečiams',
							'laiskas-filipieciams' => 'Laiškas filipiečiams',
							'laiskas-kolosieciams' => 'Laiškas kolosiečiams',
							'pirmas-laiskas-tesalonikieciams' => 'Pirmas laiškas tesalonikiečiams',
							'antras-laiskas-tesalonikieciams' => 'Antras laiškas tesalonikiečiams',
							'pirmas-laiskas-timotiejui' => 'Pirmas laiškas Timotiejui',
							'antras-laiskas-timotiejui' => 'Antras laiškas Timotiejui',
							'laiskas-titui' => 'Laiškas Titui',
							'laiskas-filemonui' => 'Laiškas Filemonui',
							'laiskas-hebrajams' => 'Laiškas hebrajams',
							'jokubo-laiskas' => 'Jokūbo laiškas',
							'petro-pirmas-laiskas' => 'Petro pirmas laiškas',
							'petro-antras-laiskas' => 'Petro antras laiškas',
							'jono-pirmas-laiskas' => 'Jono pirmas laiškas',
							'jono-antras-laiskas' => 'Jono antras laiškas',
							'jono-trecias-laiskas' => 'Jono trečias laiškas',
							'judo-laiskas' => 'Judo laiškas',
						),
					),
					'apreiskimas' => array(
						'name'  => 'Apreiškimas',
						'books' => array(
							'apreiskimas-jonui' => 'Apreiškimas Jonui',
						),
					),
				),
			),
		);
	}

	/** Book slug => array( name, group slug, testament slug, canonical position 1–66 ). */
	public static function books() {
		static $books = null;
		if ( null === $books ) {
			$books = array();
			foreach ( self::tree() as $testament => $t ) {
				foreach ( $t['groups'] as $group => $g ) {
					foreach ( $g['books'] as $slug => $name ) {
						$books[ $slug ] = array(
							'name'      => $name,
							'group'     => $group,
							'testament' => $testament,
							'position'  => count( $books ) + 1,
						);
					}
				}
			}
		}
		return $books;
	}

	/** Book slug for a book name or slug ("Pradžios knyga", "pradzios-knyga"), else ''. */
	public static function find( $text ) {
		$books = self::books();
		$text  = trim( wp_strip_all_tags( (string) $text ) );
		if ( isset( $books[ $text ] ) ) {
			return $text;
		}
		$key = self::fold( $text );
		foreach ( $books as $slug => $book ) {
			if ( self::fold( $book['name'] ) === $key ) {
				return $slug;
			}
		}
		return '';
	}

	/** Lower case without Lithuanian diacritics or extra spaces, for loose name matching. */
	public static function fold( $text ) {
		$text = strtr( (string) $text, array( 'ą' => 'a', 'č' => 'c', 'ę' => 'e', 'ė' => 'e', 'į' => 'i', 'š' => 's', 'ų' => 'u', 'ū' => 'u', 'ž' => 'z', 'Ą' => 'a', 'Č' => 'c', 'Ę' => 'e', 'Ė' => 'e', 'Į' => 'i', 'Š' => 's', 'Ų' => 'u', 'Ū' => 'u', 'Ž' => 'z' ) );
		return trim( (string) preg_replace( '/\s+/u', ' ', strtolower( $text ) ) );
	}
}
