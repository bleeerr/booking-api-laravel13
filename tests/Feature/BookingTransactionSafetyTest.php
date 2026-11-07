<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Events\BookingConfirmed;
use App\Mail\BookingStatusMail;
use App\Models\Booking;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BookingTransactionSafetyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_rollback_does_not_send_email(): void
    {
        Mail::fake();

        $booking = Booking::factory()->create([
            'status' => BookingStatus::Pending,
        ]);

        DB::beginTransaction();

        try {
            $booking->update([
                'status' => BookingStatus::Confirmed,
            ]);

            BookingConfirmed::dispatch($booking);

            // Email must not be sent before commit.
            Mail::assertNothingSent();
        } finally {
            DB::rollBack();
        }

        // After rollback, email must never be sent.
        Mail::assertNothingSent();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Pending->value,
        ]);
    }

    public function test_commit_sends_email_only_after_commit(): void
    {
        Mail::fake();

        $booking = Booking::factory()->create([
            'status' => BookingStatus::Pending,
        ]);

        DB::beginTransaction();

        try {
            $booking->update([
                'status' => BookingStatus::Confirmed,
            ]);

            BookingConfirmed::dispatch($booking);

            // Before commit, no email should be sent.
            Mail::assertNothingSent();

            DB::commit();
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }

        Mail::assertSent(
            BookingStatusMail::class,
            fn (BookingStatusMail $mail): bool => $mail->hasTo($booking->user->email)
                && $mail->subjectLine === 'Booking confirmed'
        );

        Mail::assertSentCount(1);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Confirmed->value,
        ]);
    }
}
