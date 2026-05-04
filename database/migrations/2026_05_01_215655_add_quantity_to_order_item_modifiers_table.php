<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('order_item_modifiers', function (Blueprint $table) {
            // On ajoute la quantité, par défaut à 1
            $table->integer('quantity')->default(1)->after('price');
            // Optionnel : recalculer le total pour faciliter les rapports financiers
            $table->decimal('total', 12, 2)->virtualAs('quantity * price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_item_modifiers', function (Blueprint $table) {
            //
        });
    }
};
