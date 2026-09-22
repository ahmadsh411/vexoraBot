<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*************  ✨ Windsurf Command ⭐  *************/
    /**
     * Create the settings table.
     *
     * @return void
     */
    /*******  da1a8fdb-dfd9-4038-b8e9-a8b65e5207a9  *******/    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->enum('type', ['string', 'int', 'bool', 'json', 'decimal'])
                ->default('string');
            $table->string('group', 50)->default('general')->index();
            $table->string('label', 200)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_editable')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
