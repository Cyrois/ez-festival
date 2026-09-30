<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->boolean('has_set_password')->default(true);
            $table->boolean('must_change_password')->default(false);
        });

        Schema::create('login_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_invitations');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['has_set_password', 'must_change_password']);
        });

        DB::table('users')
            ->whereNull('password')
            ->update(['password' => Hash::make(Str::random(64))]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};
