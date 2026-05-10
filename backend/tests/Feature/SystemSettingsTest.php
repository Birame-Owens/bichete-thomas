<?php

namespace Tests\Feature;

use App\Models\ParametreSysteme;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests d integration sur App\Support\SystemSettings (I7 + B9).
 *
 * Verifie le cache + l invalidation auto via les model events de
 * ParametreSysteme. Indispensable pour s assurer que la performance gagnee
 * en I7 (5 SELECT -> 0 sur cache hit) ne masque pas un bug de coherence.
 */
class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_lit_la_valeur_depuis_la_base_au_premier_appel(): void
    {
        ParametreSysteme::query()->create([
            'cle' => 'test_param',
            'valeur' => ['value' => 'hello'],
            'type' => 'string',
            'description' => 'test',
            'modifiable' => true,
        ]);

        $this->assertSame('hello', SystemSettings::get('test_param'));
    }

    public function test_get_retourne_le_default_si_la_cle_n_existe_pas(): void
    {
        $this->assertSame('fallback', SystemSettings::get('inexistant', 'fallback'));
        $this->assertNull(SystemSettings::get('inexistant'));
    }

    public function test_les_lectures_repetees_n_engendrent_qu_un_seul_select_grace_au_cache(): void
    {
        ParametreSysteme::query()->create([
            'cle' => 'cached_param',
            'valeur' => ['value' => 42],
            'type' => 'integer',
            'description' => 'test',
            'modifiable' => true,
        ]);

        // Compte les SELECT executes.
        DB::flushQueryLog();
        DB::enableQueryLog();

        // 5 lectures successives.
        for ($i = 0; $i < 5; $i++) {
            SystemSettings::get('cached_param');
        }

        $queries = collect(DB::getQueryLog())
            ->filter(fn ($q) => str_contains($q['query'], 'parametres_systeme'))
            ->count();

        // Une seule requete sur parametres_systeme : la 1re lecture popule
        // le cache, les 4 suivantes hit cache.
        $this->assertSame(1, $queries, "Attendu 1 SELECT sur parametres_systeme, recu {$queries}");

        DB::disableQueryLog();
    }

    public function test_modifier_un_parametre_invalide_le_cache_automatiquement(): void
    {
        $param = ParametreSysteme::query()->create([
            'cle' => 'auto_invalidate',
            'valeur' => ['value' => 'original'],
            'type' => 'string',
            'description' => 'test',
            'modifiable' => true,
        ]);

        // Premiere lecture : popule le cache.
        $this->assertSame('original', SystemSettings::get('auto_invalidate'));

        // Modification via le modele (declenche l event saved).
        $param->update(['valeur' => ['value' => 'modifie']]);

        // Lecture suivante : doit voir la nouvelle valeur (cache flushe par event).
        $this->assertSame('modifie', SystemSettings::get('auto_invalidate'));
    }

    public function test_supprimer_un_parametre_invalide_le_cache(): void
    {
        $param = ParametreSysteme::query()->create([
            'cle' => 'to_delete',
            'valeur' => ['value' => 'x'],
            'type' => 'string',
            'description' => 'test',
            'modifiable' => true,
        ]);

        $this->assertSame('x', SystemSettings::get('to_delete'));

        $param->delete();

        $this->assertNull(SystemSettings::get('to_delete'));
    }

    public function test_flush_manuel_efface_le_cache(): void
    {
        ParametreSysteme::query()->create([
            'cle' => 'manual',
            'valeur' => ['value' => 'a'],
            'type' => 'string',
            'description' => 'test',
            'modifiable' => true,
        ]);

        SystemSettings::get('manual'); // Popule cache.

        // Modif raw query qui ne fire pas les events.
        DB::table('parametres_systeme')->where('cle', 'manual')->update([
            'valeur' => json_encode(['value' => 'b']),
        ]);

        // Sans flush manuel, le cache rend encore l ancienne valeur.
        // On confirme + on flush + on relit.
        $this->assertSame('a', SystemSettings::get('manual'));

        SystemSettings::flush();

        $this->assertSame('b', SystemSettings::get('manual'));
    }
}
