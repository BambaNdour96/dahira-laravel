<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Comités et réunions : le Comité directeur, les CDGMT et autres comités, leurs commissions,
 * leurs réunions (ordre du jour, présences), leurs décisions numérotées et leurs actions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instances', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 20)->index();
            $table->string('nom', 150);
            $table->string('code', 15);
            $table->unsignedSmallInteger('annee');
            $table->foreignId('collecte_id')->nullable()->constrained('collectes')->nullOnDelete();
            $table->foreignId('president_id')->nullable()->constrained('membres')->nullOnDelete();
            $table->unsignedTinyInteger('quorum')->default(50);
            $table->date('mise_en_place')->nullable();
            $table->string('statut', 10)->default('active');
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('instance_membre', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instance_id')->constrained('instances')->cascadeOnDelete();
            $table->foreignId('membre_id')->constrained('membres')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['instance_id', 'membre_id']);
        });

        Schema::create('commissions_comite', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instance_id')->constrained('instances')->cascadeOnDelete();
            $table->string('nom', 100);
            $table->string('mission', 255)->nullable();
            $table->foreignId('chef_id')->nullable()->constrained('membres')->nullOnDelete();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });

        Schema::create('reunions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instance_id')->constrained('instances')->cascadeOnDelete();
            $table->string('titre', 150);
            $table->dateTime('debut');
            $table->string('lieu', 150);
            $table->string('lien', 255)->nullable();
            $table->foreignId('secretaire_id')->nullable()->constrained('membres')->nullOnDelete();
            $table->text('divers')->nullable();
            $table->string('statut', 10)->default('preparee');
            $table->timestamp('convoquee_le')->nullable();
            $table->timestamp('publiee_le')->nullable();
            $table->foreignId('publiee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('reunion_points', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reunion_id')->constrained('reunions')->cascadeOnDelete();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->string('titre', 200);
            $table->text('resume')->nullable();
            $table->timestamps();
        });

        Schema::create('reunion_presences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reunion_id')->constrained('reunions')->cascadeOnDelete();
            $table->foreignId('membre_id')->constrained('membres')->cascadeOnDelete();
            $table->string('statut', 10);
            $table->timestamps();
            $table->unique(['reunion_id', 'membre_id']);
        });

        Schema::create('decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instance_id')->constrained('instances')->cascadeOnDelete();
            $table->foreignId('reunion_id')->constrained('reunions')->cascadeOnDelete();
            $table->string('numero', 30)->unique();
            $table->text('texte');
            $table->unsignedSmallInteger('pour')->nullable();
            $table->unsignedSmallInteger('contre')->nullable();
            $table->unsignedSmallInteger('abstention')->nullable();
            $table->string('etat', 15)->default('en_vigueur');
            $table->timestamps();
        });

        Schema::create('actions_comite', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instance_id')->constrained('instances')->cascadeOnDelete();
            $table->foreignId('reunion_id')->nullable()->constrained('reunions')->cascadeOnDelete();
            $table->string('description', 255);
            $table->foreignId('responsable_id')->nullable()->constrained('membres')->nullOnDelete();
            $table->foreignId('commission_id')->nullable()->constrained('commissions_comite')->nullOnDelete();
            $table->date('echeance')->nullable();
            $table->string('statut', 10)->default('a_faire');
            $table->timestamps();
        });

        DB::table('instances')->insert([
            'type' => 'comite_directeur',
            'nom' => 'Comité directeur',
            'code' => 'CD',
            'annee' => (int) date('Y'),
            'statut' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Role::findOrCreate('comite_directeur', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('actions_comite');
        Schema::dropIfExists('decisions');
        Schema::dropIfExists('reunion_presences');
        Schema::dropIfExists('reunion_points');
        Schema::dropIfExists('reunions');
        Schema::dropIfExists('commissions_comite');
        Schema::dropIfExists('instance_membre');
        Schema::dropIfExists('instances');
    }
};
