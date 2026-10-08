<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Les annonces seront publiées sur le futur site de la Daara.
        Schema::dropIfExists('annonces');
    }

    public function down(): void
    {
        Schema::create('annonces', function (Blueprint $table) {
            $table->id();
            $table->string('titre', 150);
            $table->text('contenu')->nullable();
            $table->string('affiche_path')->nullable();
            $table->boolean('importante')->default(false);
            $table->timestamp('expire_le')->nullable();
            $table->foreignId('auteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
};
