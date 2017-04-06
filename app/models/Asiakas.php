<?php

class Asiakas extends BaseModel {
	// Attribuutit
	public $asiakas_id, $ktunnus, $etunimi, $sukunimi,
		$puhelinnumero, $sahkopostiosoite,
		// Tietokantatoteutuksessa seuraavat kaksi kenttää eivät löydy
		// taulusta Asiakas, vaan taulusta Kayttaja. En tiedä, onko ihan
		// korrektia hämärtää malliolioiden ja taulujen yksi yhteen
		// -vastaavuutta tällä tavalla, mutta kokeilen nyt kuitenkin.
		$salasana, $tyyppi;

	// Konstruktori
	public function __construct( $attributes ) {
		parent::__construct( $attributes );
		// Seuraava attribuutti on määritelty BaseModelissa
		$this->validaattorit = array(
			'validoi_etunimi', 'validoi_sukunimi',
			'validoi_puhelinnumero', 'validoi_sahkopostiosoite' );
	}

	public function validoi_etunimi() {
		$errors = BaseModel::merkkijono_on_erisnimi(
			"Etunimi: ", $this->etunimi );
		return $errors;
	}

	public function validoi_sukunimi() {
		$errors = BaseModel::merkkijono_on_erisnimi(
			"Sukunimi: ", $this->sukunimi );
		return $errors;
	}

	// Hätäisesti ja kiireessä toteutettu
	public function validoi_puhelinnumero() {
		$errors = array();
		$sl = '/\A[+]?[0-9 ]{10,20}\z/';
		if( strlen( $this->puhelinnumero ) > 0
			&& preg_match( $sl, $this->puhelinnumero ) == 0 ) {
			$errors[] = 'Puhelinnumero ei kelpaa';
		}
		return $errors;
	}

	// Hätäisesti ja kiireessä toteutettu
	public function validoi_sahkopostiosoite() {
		$errors = array();
		$sl = '/\A.{1,20}@.{1,20}\z/';
		if( strlen( $this->sahkopostiosoite ) > 0
			&& preg_match( $sl, $this->sahkopostiosoite ) == 0 ) {
			$errors[] = 'Sähköpostiosoite ei kelpaa';
		}
		return $errors;
	}

	// Asiakastietojen päivitys. Huomaa, että kenttien asiakas_id ja ktunnus
	// arvoja ei voi päivittää tällä funktiolla. Se ei olisi tarkoituksenmukaista.
	public function paivita() {
		$query = DB::connection()->prepare(
			'update Asiakas set etunimi = :etunimi, sukunimi = :sukunimi, '
			. 'puhelinnumero = :puhelinnumero, sahkopostiosoite = :sahkopostiosoite '
			. 'where asiakas_id = :asiakas_id;' );
		$query->execute( array(
			'asiakas_id' => $this->asiakas_id,
			'etunimi' => $this->etunimi,
			'sukunimi' => $this->sukunimi,
			'puhelinnumero' => $this->puhelinnumero,
			'sahkopostiosoite' => $this->sahkopostiosoite
		) );
	}

	// Poistetaan Asiakas-olio tietokannasta
	public function poista() {
		// Varmistetaan, että poistettavaksi tarkoitettu olio
		// on tallennettuna tietokantaan
		if( self::find( $this->asiakas_id ) == null ) {
			return null;
		}

		$query = DB::connection()->prepare(
			'delete from Asiakas where asiakas_id = :asiakas_id;' );
		$query->execute( array(
			'asiakas_id' => $this->asiakas_id
		) );

		return $this;
	}

	// Tallennetaan Asiakas-olio tietokantaan
	public function save() {
		$kayttaja = new Kayttaja( array(
			'ktunnus' => $this->ktunnus, 'salasana' => null, 'tyyppi' => 0 ) );
		$kayttaja->save();

		$query = DB::connection()->prepare(
			'insert into Asiakas ( ktunnus, etunimi, sukunimi, puhelinnumero, '
			. 'sahkopostiosoite ) values ( :ktunnus, :etunimi, :sukunimi, '
			. ':puhelinnumero, :sahkopostiosoite ) returning asiakas_id;' );
		$query->execute( array(
			'ktunnus' => $this->ktunnus,
			'etunimi' => $this->etunimi,
			'sukunimi' => $this->sukunimi,
			'puhelinnumero' => $this->puhelinnumero,
			'sahkopostiosoite' => $this->sahkopostiosoite
		) );

		$row = $query->fetch();
		$this->asiakas_id = $row[ 'asiakas_id' ];
	}

	// Haetaan tietokannasta kaikki Asiakas-oliot. Jokaiseen asiakkaaseen
	// liittyy täsmälleen yksi Kayttaja-olio, joten haetaan samalla nekin.
	public static function all() {
		$query = DB::connection()->prepare( 'select Asiakas.asiakas_id, Asiakas.etunimi, Asiakas.sukunimi, Asiakas.puhelinnumero, Asiakas.sahkopostiosoite, Kayttaja.ktunnus, Kayttaja.salasana, Kayttaja.tyyppi from Asiakas inner join Kayttaja on Asiakas.ktunnus = Kayttaja.ktunnus;' );
		$query->execute();
		$rows = $query->fetchAll();

		$asiakkaat = array();

		foreach( $rows as $row ) {
			$asiakkaat[] = new Asiakas( array(
				'asiakas_id' => $row[ 'asiakas_id' ],
				'ktunnus' => $row[ 'ktunnus' ],
				'etunimi' => $row[ 'etunimi' ],
				'sukunimi' => $row[ 'sukunimi' ],
				'puhelinnumero' => $row[ 'puhelinnumero' ],
				'sahkopostiosoite' => $row[ 'sahkopostiosoite' ],
				'salasana' => $row[ 'salasana' ],
				'tyyppi' => $row[ 'tyyppi' ]
			) );
		}

		return $asiakkaat;
	}

	// Haetaan tietokannasta asiakas_id:tä vastaava asiakas. Huomaa, että
	// tässä yhteydessä haetaan tietoa Asiakas-taulun lisäksi myös
	// Kayttaja-taulusta
	public static function find( $asiakas_id ) {
		$query = DB::connection()->prepare( 'select Asiakas.asiakas_id, Asiakas.etunimi, Asiakas.sukunimi, Asiakas.puhelinnumero, Asiakas.sahkopostiosoite, Kayttaja.ktunnus, Kayttaja.salasana, Kayttaja.tyyppi from ( Asiakas inner join Kayttaja on Asiakas.ktunnus = Kayttaja.ktunnus ) where asiakas_id = :asiakas_id limit 1;' );
		$query->execute( array( 'asiakas_id' => $asiakas_id ) );
		$row = $query->fetch();

		if( $row ) {
			$asiakas = new Asiakas( array(
				'asiakas_id' => $row[ 'asiakas_id' ],
				'ktunnus' => $row[ 'ktunnus' ],
				'etunimi' => $row[ 'etunimi' ],
				'sukunimi' => $row[ 'sukunimi' ],
				'puhelinnumero' => $row[ 'puhelinnumero' ],
				'sahkopostiosoite' => $row[ 'sahkopostiosoite' ],
				'salasana' => $row[ 'salasana' ],
				'tyyppi' => $row[ 'tyyppi' ]
			) );

			return $asiakas;
		}

		return null;
	}
}
