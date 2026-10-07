<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demandes_suppression', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('membre_id')->nullable()->constrained('membres')->nullOnDelete();
            $table->text('motif')->nullable();
            $table->string('statut', 20)->default('en_attente')->index();
            $table->text('reponse')->nullable();
            $table->foreignId('traitee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('traitee_le')->nullable();
            $table->timestamps();
        });

        Schema::table('membres', function (Blueprint $table) {
            // Date à laquelle les données personnelles ont été effacées.
            $table->timestamp('anonymise_le')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('membres', function (Blueprint $table) {
            $table->dropColumn('anonymise_le');
        });

        Schema::dropIfExists('demandes_suppression');
    }
};
