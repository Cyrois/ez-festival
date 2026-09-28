<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // Comparison key for unique names: trimmed, inner spaces squashed, lower case, accents kept
            // (see App\Support\RoleName). MySQL's default *_ci collations ignore accents, so the key
            // uses a binary collation there; "Café" and "Cafe" must stay distinct.
            $nameKey = $table->string('name_key');
            if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $nameKey->collation('utf8mb4_bin');
            }
            $nameKey->unique();

            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
