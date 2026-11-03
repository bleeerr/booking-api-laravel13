<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('room_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('status', 20)->default('pending');

            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');

            $table->bigInteger('total_price_minor');
            $table->char('currency_code', 3);

            $table->timestampTz('cancelled_at')->nullable();

            $table->timestamps();
        });

        DB::statement("
            ALTER TABLE bookings
            ADD CONSTRAINT bookings_status_check
            CHECK (status IN ('pending', 'confirmed', 'cancelled'))
        ");

        DB::statement('
            ALTER TABLE bookings
            ADD CONSTRAINT bookings_interval_check
            CHECK (ends_at > starts_at)
        ');

        DB::statement('
            ALTER TABLE bookings
            ADD CONSTRAINT bookings_price_check
            CHECK (total_price_minor >= 0)
        ');

        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        DB::statement("
            ALTER TABLE bookings
            ADD CONSTRAINT bookings_no_overlap
            EXCLUDE USING gist (
                room_id WITH =,
                tstzrange(starts_at, ends_at, '[)') WITH &&
            )
            WHERE (status IN ('pending', 'confirmed'))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
