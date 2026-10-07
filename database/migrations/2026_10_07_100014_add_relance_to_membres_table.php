<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membres', function (Blueprint $table) {
            $table->timestamp('relance_le')->nullable();
            $table->foreignId('relance_par')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('membres', function (Blueprint $table) {
            $table->dropConstrainedForeignId('relance_par');
            $table->dropColumn('relance_le');
        });
    }
};
