<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Rappels des programmes : fenêtre à l'ouverture et notifications, notés pour ne jamais doubler. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collectes', function (Blueprint $table): void {
            $table->boolean('rappels_actifs')->default(true)->after('jauge_visible_depuis');
        });

        Schema::create('rappels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('collecte_id')->constrained('collectes')->cascadeOnDelete();
            $table->foreignId('membre_id')->constrained('membres')->cascadeOnDelete();
            $table->string('canal', 10);
            $table->string('type', 20);
            $table->timestamp('envoye_le')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rappels');

        Schema::table('collectes', function (Blueprint $table): void {
            $table->dropColumn('rappels_actifs');
        });
    }
};
