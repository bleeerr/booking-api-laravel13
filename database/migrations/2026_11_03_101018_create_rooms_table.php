<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();

            $table->foreignId('workspace_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->integer('capacity');
            $table->bigInteger('price_per_hour_minor');
            $table->char('currency_code', 3);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

        DB::statement('ALTER TABLE rooms ADD CONSTRAINT rooms_capacity_check CHECK (capacity > 0)');
        DB::statement('ALTER TABLE rooms ADD CONSTRAINT rooms_price_check CHECK (price_per_hour_minor >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
