<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Lot R1b : la séance de cellule s'appelle « dahira », partout. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('evenements')->where('type', 'seance')->update(['type' => 'dahira']);
    }

    public function down(): void
    {
        DB::table('evenements')->where('type', 'dahira')->update(['type' => 'seance']);
    }
};
