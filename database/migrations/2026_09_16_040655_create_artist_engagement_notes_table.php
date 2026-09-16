<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artist_engagement_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_engagement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['artist_engagement_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artist_engagement_notes');
    }
};
