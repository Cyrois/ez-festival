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
            $table->string('name_key')->unique();
            $table->boolean('active')->default(true);
            $table->json('permissions')->default('[]');
            $table->timestamps();

            $table->index(['active', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
