<?php

namespace Tests\Unit\Support;

use App\Support\PhoneNumber;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires sur App\Support\PhoneNumber (Phase 5 etape 1).
 *
 * Couvre les formats susceptibles d arriver depuis le formulaire client
 * (saisie libre, raw legacy en base, eSIM voyage, expat) et garantit que le
 * helper retourne un E.164 strict ou null. C est ce contrat qui permet aux
 * call-sites (lookup, findOrCreate, migration) de raisonner sur du tel
 * canonique sans plus jamais re-parser.
 */
class PhoneNumberTest extends TestCase
{
    public function test_normalise_un_numero_sn_local_sans_prefixe(): void
    {
        $this->assertSame('+221771234567', PhoneNumber::normalize('771234567'));
    }

    public function test_normalise_un_numero_sn_avec_prefixe_et_espaces(): void
    {
        $this->assertSame('+221771234567', PhoneNumber::normalize('+221 77 123 45 67'));
    }

    public function test_normalise_un_numero_sn_avec_tirets(): void
    {
        $this->assertSame('+221771234567', PhoneNumber::normalize('+221-77-123-4567'));
    }

    public function test_normalise_un_numero_sn_avec_parentheses(): void
    {
        $this->assertSame('+221771234567', PhoneNumber::normalize('(+221) 77 123 4567'));
    }

    public function test_normalise_un_numero_etranger_meme_si_default_country_sn(): void
    {
        // Un touriste FR qui reserve : son +33 doit etre accepte tel quel.
        $this->assertSame('+33612345678', PhoneNumber::normalize('+33612345678'));
        $this->assertSame('+33612345678', PhoneNumber::normalize('+33 6 12 34 56 78'));
    }

    public function test_normalise_un_numero_us(): void
    {
        // Numero US classique d annuaire.
        $this->assertSame('+14155552671', PhoneNumber::normalize('+1 415 555 2671'));
    }

    public function test_respecte_un_default_country_alternatif(): void
    {
        // Saisi local francais sans prefixe + default country FR => +33.
        $this->assertSame('+33612345678', PhoneNumber::normalize('0612345678', 'FR'));
    }

    public function test_retourne_null_pour_un_input_non_numerique(): void
    {
        $this->assertNull(PhoneNumber::normalize('voir avec elle'));
        $this->assertNull(PhoneNumber::normalize('abc'));
    }

    public function test_retourne_null_pour_un_input_trop_court(): void
    {
        $this->assertNull(PhoneNumber::normalize('123'));
        $this->assertNull(PhoneNumber::normalize('77'));
    }

    public function test_retourne_null_pour_un_input_invalide_pour_le_pays(): void
    {
        // Numero qui ne respecte pas le format SN (mauvaise longueur sur prefixe local).
        $this->assertNull(PhoneNumber::normalize('+221123'));
    }

    public function test_retourne_null_pour_null_ou_chaine_vide(): void
    {
        $this->assertNull(PhoneNumber::normalize(null));
        $this->assertNull(PhoneNumber::normalize(''));
        $this->assertNull(PhoneNumber::normalize('   '));
    }

    public function test_is_valid_est_coherent_avec_normalize(): void
    {
        $this->assertTrue(PhoneNumber::isValid('+221771234567'));
        $this->assertTrue(PhoneNumber::isValid('771234567'));
        $this->assertTrue(PhoneNumber::isValid('+33612345678'));

        $this->assertFalse(PhoneNumber::isValid('voir avec elle'));
        $this->assertFalse(PhoneNumber::isValid(null));
        $this->assertFalse(PhoneNumber::isValid(''));
        $this->assertFalse(PhoneNumber::isValid('123'));
    }

    public function test_default_country_est_bien_sn(): void
    {
        // Garde-fou : si on touche a la constante par erreur, ce test casse vite.
        $this->assertSame('SN', PhoneNumber::DEFAULT_COUNTRY);
    }

    public function test_idempotence_sur_un_e164_deja_normalise(): void
    {
        // Re-normaliser un E.164 deja propre doit rendre la meme chose -
        // garantie pour la migration legacy (re-run safe).
        $this->assertSame('+221771234567', PhoneNumber::normalize('+221771234567'));
        $this->assertSame('+33612345678', PhoneNumber::normalize('+33612345678'));
    }
}
