<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('piece_orchestra', function (Blueprint $table) {
            // Drop foreign keys first so MySQL allows dropping the primary key
            $table->dropForeign(['piece_id']);
            $table->dropForeign(['orchestra_id']);
            $table->dropPrimary(['piece_id', 'orchestra_id']);
        });

        Schema::table('piece_orchestra', function (Blueprint $table) {
            $table->id()->first();
            $table->date('van')->nullable()->after('orchestra_id');
            $table->date('tot')->nullable()->after('van');
            $table->text('details')->nullable()->after('tot');
            $table->timestamps();

            // Re-add foreign keys and add indices
            $table->foreign('piece_id')->references('id')->on('pieces')->cascadeOnDelete();
            $table->foreign('orchestra_id')->references('id')->on('orchestras')->cascadeOnDelete();
            $table->index(['piece_id', 'orchestra_id']);
            $table->index('tot');
        });
    }

    public function down(): void
    {
        Schema::table('piece_orchestra', function (Blueprint $table) {
            $table->dropForeign(['piece_id']);
            $table->dropForeign(['orchestra_id']);
            $table->dropIndex(['piece_id', 'orchestra_id']);
            $table->dropIndex(['tot']);

            $table->dropTimestamps();
            $table->dropColumn(['id', 'van', 'tot', 'details']);

            $table->primary(['piece_id', 'orchestra_id']);
            $table->foreign('piece_id')->references('id')->on('pieces')->cascadeOnDelete();
            $table->foreign('orchestra_id')->references('id')->on('orchestras')->cascadeOnDelete();
        });
    }
};
