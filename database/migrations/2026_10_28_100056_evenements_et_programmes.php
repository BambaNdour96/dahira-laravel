<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * R4 : les événements (programmes) se créent d'un seul formulaire. Les types forment une liste que le super
 * administrateur complète et archive ; la contribution est un Jàyante ou un Sass (les quatre anciens modes sont repris) ;
 * chaque événement a ses dates, son lieu, son objectif, son budget, sa façon d'organiser (comité dédié ou commissions
 * permanentes choisies, avec leur part du budget) et la case « itinéraire et transport ». Les tâches confiées aux
 * commissions gardent leur événement.
 */
return new class extends Migration
{
    /** Les types de départ, et les anciennes valeurs reprises. */
    private const TYPES = [
        'magal' => 'Magal de Touba', 'ziar' => 'Ziar', 'prokhane' => 'Prokhane', 'gamou' => 'Gamou', 'laylatoul_qadr' => 'Laylatoul Qadr',
    ];

    private const ANCIENS = ['secondaire' => 'Programme secondaire', 'autre' => 'Autre événement'];

    public function up(): void
    {
        Schema::create('types_programme', function (Blueprint $table): void {
            $table->id();
            $table->string('nom', 80)->unique();
            $table->string('code', 40)->unique();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamp('archive_le')->nullable();
            $table->foreignId('archive_par')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        $maintenant = now();
        $ordre = 0;
        foreach (self::TYPES as $code => $nom) {
            DB::table('types_programme')->insert(['nom' => $nom, 'code' => $code, 'ordre' => ++$ordre, 'created_at' => $maintenant, 'updated_at' => $maintenant]);
        }

        Schema::table('collectes', function (Blueprint $table): void {
            $table->foreignId('type_programme_id')->nullable()->after('titre')->constrained('types_programme')->restrictOnDelete();
            $table->dateTime('debut')->nullable()->after('description');
            $table->dateTime('fin')->nullable()->after('debut');
            $table->string('lieu', 150)->nullable()->after('fin');
            $table->unsignedInteger('budget')->nullable()->after('objectif');
            $table->string('organisation', 20)->nullable()->after('budget');
            $table->boolean('itineraire_transport')->default(false)->after('organisation');
        });

        Schema::create('programme_commissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('collecte_id')->constrained('collectes')->restrictOnDelete();
            $table->foreignId('commission_id')->constrained('commissions')->restrictOnDelete();
            $table->unsignedInteger('budget')->nullable();
            $table->timestamp('retiree_le')->nullable();
            $table->timestamps();
            $table->unique(['collecte_id', 'commission_id']);
        });

        Schema::table('actions_comite', function (Blueprint $table): void {
            $table->foreignId('collecte_id')->nullable()->after('commission_id')->constrained('collectes')->restrictOnDelete();
        });

        $this->reprendre();

        Schema::table('collectes', function (Blueprint $table): void {
            $table->dropColumn('programme');
        });
        Schema::table('collectes', function (Blueprint $table): void {
            $table->string('mode', 20)->nullable()->change();
        });
    }

    /** Les types, les modes, les dates et l'organisation des événements existants. */
    private function reprendre(): void
    {
        foreach (DB::table('collectes')->get() as $collecte) {
            $code = (string) $collecte->programme;
            $typeId = null;

            if ($code !== 'projet') {
                $typeId = DB::table('types_programme')->where('code', $code)->value('id');

                if ($typeId === null) {
                    $nom = self::ANCIENS[$code] ?? ucfirst($code);
                    $typeId = DB::table('types_programme')->insertGetId([
                        'nom' => $nom, 'code' => $code, 'ordre' => 50, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }

            $evenement = $collecte->evenement_id !== null ? DB::table('evenements')->where('id', $collecte->evenement_id)->first() : null;
            $comite = DB::table('instances')->where('collecte_id', $collecte->id)->exists();

            // Un Magal sans comité retrouve le CDGMT de la même année, s'il n'est lié à rien.
            if (! $comite && $code === 'magal') {
                $annee = (int) substr((string) ($evenement->debut ?? $collecte->ouverture), 0, 4);
                $cdgmt = DB::table('instances')->where('type', 'cdgmt')->whereNull('collecte_id')->where('annee', $annee)->orderBy('id')->value('id');

                if ($cdgmt !== null) {
                    DB::table('instances')->where('id', $cdgmt)->update(['collecte_id' => $collecte->id]);
                    $comite = true;
                }
            }
            // Un ancien Sass « attribué » prend pour minimum le plus petit montant déjà fixé par le bureau.
            $sass = $collecte->mode === 'attribue' && $collecte->montant === null
                ? DB::table('engagements')->where('collecte_id', $collecte->id)->min('montant')
                : $collecte->montant;

            DB::table('collectes')->where('id', $collecte->id)->update([
                'type_programme_id' => $typeId,
                'mode' => $collecte->mode === 'libre' ? 'jayante' : 'sass',
                'montant' => $collecte->mode === 'libre' ? null : $sass,
                'debut' => $evenement->debut ?? null,
                'fin' => $evenement->fin ?? null,
                'lieu' => $evenement->lieu ?? null,
                'organisation' => $typeId === null ? null : ($comite || in_array($code, ['magal', 'ziar'], true) ? 'comite' : 'commissions'),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('collectes', function (Blueprint $table): void {
            $table->string('programme', 20)->default('autre')->after('titre');
        });

        foreach (DB::table('collectes')->get() as $collecte) {
            $code = $collecte->type_programme_id !== null ? DB::table('types_programme')->where('id', $collecte->type_programme_id)->value('code') : 'projet';
            DB::table('collectes')->where('id', $collecte->id)->update([
                'programme' => in_array($code, ['magal', 'ziar', 'prokhane', 'laylatoul_qadr', 'secondaire', 'projet'], true) ? $code : 'autre',
                'mode' => $collecte->mode === 'jayante' ? 'libre' : ($collecte->montant !== null ? 'minimum' : 'libre'),
            ]);
        }

        Schema::table('actions_comite', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('collecte_id');
        });
        Schema::dropIfExists('programme_commissions');
        Schema::table('collectes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('type_programme_id');
            $table->dropColumn(['debut', 'fin', 'lieu', 'budget', 'organisation', 'itineraire_transport']);
        });
        Schema::dropIfExists('types_programme');
    }
};
