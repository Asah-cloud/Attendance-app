<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('title');
        });

        $usedSlugs = [];
        DB::table('events')->orderBy('id')->select('id', 'title')->each(function ($event) use (&$usedSlugs) {
            $base = Str::slug($event->title) ?: 'event';
            $slug = $base;
            $suffix = 2;
            while (in_array($slug, $usedSlugs, true)) {
                $slug = $base.'-'.$suffix++;
            }
            $usedSlugs[] = $slug;
            DB::table('events')->where('id', $event->id)->update(['slug' => $slug]);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
