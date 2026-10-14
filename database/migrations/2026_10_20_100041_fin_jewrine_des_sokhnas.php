<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot R1b : il n'y a plus de Jewrine des Sokhnas, le Jewrine de cellule est responsable de tous ses membres.
 * Chaque Jewrine des Sokhnas devient pointeuse des Sokhnas de sa cellule.
 */
return new class extends Migration
{
    public function up(): void
    {
        $maintenant = now();

        foreach (DB::table('cellules')->whereNotNull('jewrine_femmes_id')->get(['id', 'jewrine_femmes_id']) as $cellule) {
            $dejaPointeur = DB::table('cellule_pointeurs')
                ->where('cellule_id', $cellule->id)
                ->where('user_id', $cellule->jewrine_femmes_id)
                ->whereNull('fin_le')
                ->exists();

            if (! $dejaPointeur) {
                DB::table('cellule_pointeurs')->insert([
                    'cellule_id' => $cellule->id,
                    'user_id' => $cellule->jewrine_femmes_id,
                    'perimetre' => 'femmes',
                    'created_at' => $maintenant,
                    'updated_at' => $maintenant,
                ]);
            }
        }

        $role = DB::table('roles')->where('name', 'jewrine_femmes')->value('id');

        if ($role !== null) {
            DB::table('model_has_roles')->where('role_id', $role)->delete();
            DB::table('role_has_permissions')->where('role_id', $role)->delete();
            DB::table('roles')->where('id', $role)->delete();
        }

        Schema::table('cellules', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('jewrine_femmes_id');
        });
    }

    /** La colonne revient, vide : les pointeuses restent pointeuses (aucune donnée n'est perdue). */
    public function down(): void
    {
        Schema::table('cellules', function (Blueprint $table): void {
            $table->foreignId('jewrine_femmes_id')->nullable()->constrained('users')->nullOnDelete();
        });

        if (! DB::table('roles')->where('name', 'jewrine_femmes')->exists()) {
            DB::table('roles')->insert(['name' => 'jewrine_femmes', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        }
    }
};
