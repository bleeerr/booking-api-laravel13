<?php

namespace App\Listeners;

use App\Events\BookingConfirmed;
use App\Mail\BookingStatusMail;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Mail;

final class SendBookingConfirmedEmail implements ShouldQueueAfterCommit
{
    public string $queue = 'emails';

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    public function handle(BookingConfirmed $event): void
    {
        $booking = $event->booking;

        Mail::to($booking->user->email)->send(
            new BookingStatusMail(
                booking: $booking,
                subjectLine: 'Booking confirmed',
                messageText: 'Your booking has been confirmed.',
            )
        );
    }
}
