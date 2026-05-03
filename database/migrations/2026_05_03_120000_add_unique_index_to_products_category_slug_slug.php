<?php

// ABOUTME: Promote the (category_slug, slug) index from non-unique to unique.
// ABOUTME: Pre-step deletes any existing collisions (keeping lowest id) so the unique index can land cleanly.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Promote the existing non-unique (category_slug, slug) index to a UNIQUE
     * composite constraint. The route resolution at ProductController::show
     * already assumes this tuple is unique; this migration enforces that
     * assumption at the DB level so future buggy code (anything that bypasses
     * import:lv) fails loudly instead of silently inserting a colliding row.
     *
     * Pre-step: a small number of historical duplicates exist where the same
     * Yupoo album was scraped under case-different folder names (e.g.
     * 'Gucci 0015' vs 'GUCCI 0015'). We collapse each duplicate group to its
     * earliest row before adding the constraint.
     */
    public function up(): void
    {
        // Collapse historical (category_slug, slug) duplicates: keep the
        // lowest id in each group, delete the rest. Without this, adding
        // the unique index would fail on existing data.
        DB::statement('
            DELETE FROM products
            WHERE id NOT IN (
                SELECT MIN(id) FROM products
                WHERE category_slug IS NOT NULL AND slug IS NOT NULL
                GROUP BY category_slug, slug
            )
            AND category_slug IS NOT NULL
            AND slug IS NOT NULL
        ');

        Schema::table('products', function (Blueprint $table) {
            // Drop the non-unique index added in 2025_12_14_152308.
            $table->dropIndex(['category_slug', 'slug']);
        });

        Schema::table('products', function (Blueprint $table) {
            // Re-add as a UNIQUE composite index. Same column order so existing
            // queries that filter by category_slug first still benefit from it.
            $table->unique(['category_slug', 'slug']);
        });
    }

    /**
     * Reverse: drop the unique constraint and restore the plain composite
     * index so query plans that relied on it still work.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['category_slug', 'slug']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index(['category_slug', 'slug']);
        });
    }
};
