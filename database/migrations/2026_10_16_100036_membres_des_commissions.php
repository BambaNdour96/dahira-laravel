<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Les membres de chaque commission d'un comité (en plus de son chef). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_comite_membre', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('commission_comite_id')->constrained('commissions_comite')->cascadeOnDelete();
            $table->foreignId('membre_id')->constrained('membres')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['commission_comite_id', 'membre_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_comite_membre');
    }
};
