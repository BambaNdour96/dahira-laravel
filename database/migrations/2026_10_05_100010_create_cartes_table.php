<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cartes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membre_id')->constrained('membres')->cascadeOnDelete();
            $table->char('jeton', 22)->unique();
            $table->string('statut', 20)->default('active')->index();
            $table->timestamp('emise_le');
            $table->timestamp('revoquee_le')->nullable();
            $table->foreignId('revoquee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motif_revocation', 255)->nullable();
            $table->timestamps();
        });

        // Une seule carte active par membre (section 8 du cahier des charges).
        DB::statement("CREATE UNIQUE INDEX cartes_une_active ON cartes (membre_id) WHERE statut = 'active'");
    }

    public function down(): void
    {
        Schema::dropIfExists('cartes');
    }
};
