<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Confidentialité : charte acceptée par chaque membre du bureau, et journal de chaque document exporté. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('charte_acceptee_le')->nullable();
        });

        Schema::create('journal_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document', 150);
            $table->string('details', 255)->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_exports');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('charte_acceptee_le');
        });
    }
};
