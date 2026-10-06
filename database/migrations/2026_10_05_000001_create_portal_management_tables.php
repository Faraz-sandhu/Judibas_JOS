<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('slug')->unique();
            $t->text('excerpt')->nullable();
            $t->text('body');
            $t->boolean('published')->default(false);
            $t->timestamps();
        });
        Schema::create('site_pages', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('title');
            $t->text('intro')->nullable();
            $t->text('body');
            $t->timestamps();
        });
        Schema::create('site_content', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->json('value');
            $t->timestamps();
        });
        Schema::create('product_settings', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('name');
            $t->text('description');
            $t->string('icon', 16)->nullable();
            $t->string('color', 7)->default('#777777');
            $t->integer('display_order')->default(0);
            $t->boolean('visible')->default(true);
            $t->timestamps();
        });
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_active')->default(true);
        });
        Schema::create('user_product_access', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('product_slug');
            $t->unique(['user_id', 'product_slug']);
        });
        Schema::create('product_plans', function (Blueprint $t) {
            $t->id();
            $t->string('product_slug');
            $t->string('name');
            $t->decimal('price', 12, 2)->default(0);
            $t->string('currency', 3)->default('USD');
            $t->string('interval')->default('monthly');
            $t->unsignedInteger('trial_days')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['product_plans', 'user_product_access', 'product_settings', 'site_content', 'site_pages', 'blog_posts'] as $table) {
            Schema::dropIfExists($table);
        }Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_active'));
    }
};
