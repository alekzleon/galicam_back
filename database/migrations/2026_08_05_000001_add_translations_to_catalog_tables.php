<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('translations')->nullable()->after('keyword');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->json('translations')->nullable()->after('slug');
        });

        Schema::table('families', function (Blueprint $table) {
            $table->json('translations')->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('translations');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('translations');
        });

        Schema::table('families', function (Blueprint $table) {
            $table->dropColumn('translations');
        });
    }
};
