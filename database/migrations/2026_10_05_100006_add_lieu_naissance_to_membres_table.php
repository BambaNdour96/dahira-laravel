<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membres', function (Blueprint $table) {
            $table->string('lieu_naissance', 100)->nullable()->after('date_naissance');
        });
    }

    public function down(): void
    {
        Schema::table('membres', function (Blueprint $table) {
            $table->dropColumn('lieu_naissance');
        });
    }
};
