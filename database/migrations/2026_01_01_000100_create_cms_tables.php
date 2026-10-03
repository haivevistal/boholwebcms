<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('options', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->unique();
            $table->longText('value')->nullable();
            $table->boolean('autoload')->default(true)->index();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name');
            $table->json('capabilities')->nullable();
            $table->timestamps();
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title')->nullable();
            $table->string('alt')->nullable();
            $table->text('caption')->nullable();
            $table->text('description')->nullable();
            $table->string('filename');
            $table->string('path');
            $table->string('disk')->default('public');
            $table->string('mime_type', 150)->index();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->json('sizes')->nullable();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->default('post')->index();
            $table->string('status', 20)->default('draft')->index();
            $table->string('title')->default('');
            $table->string('slug', 191)->index();
            $table->longText('content')->nullable();
            $table->text('excerpt')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->index();
            $table->integer('menu_order')->default(0);
            $table->string('template')->nullable();
            $table->string('comment_status', 20)->default('open');
            $table->foreignId('featured_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('password')->nullable();
            $table->unsignedInteger('comment_count')->default(0);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();

            $table->index(['type', 'status', 'published_at']);
        });

        Schema::create('post_meta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('meta_key', 191)->index();
            $table->longText('meta_value')->nullable();
            $table->unique(['post_id', 'meta_key']);
        });

        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->string('taxonomy', 50)->index();
            $table->string('name');
            $table->string('slug', 191);
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->index();
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();
            $table->unique(['taxonomy', 'slug']);
        });

        Schema::create('term_relationships', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->primary(['post_id', 'term_id']);
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->index();
            $table->string('author_name')->nullable();
            $table->string('author_email')->nullable();
            $table->string('author_url')->nullable();
            $table->string('author_ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->text('content');
            $table->string('status', 20)->default('pending')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
        Schema::dropIfExists('term_relationships');
        Schema::dropIfExists('terms');
        Schema::dropIfExists('post_meta');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('media');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('options');
    }
};
