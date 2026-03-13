<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->longText('content')->nullable();
            $table->string('url')->unique();
            $table->text('image_url')->nullable();
            $table->string('source');
            $table->string('source_url');
            $table->dateTime('published_at');
            $table->timestamps();

            if (DB::getDriverName() === 'mysql') {
                $table->fullText(['title', 'description']);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
