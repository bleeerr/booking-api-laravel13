<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Mail\BookingStatusMail;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Mail;

final class SendBookingCreatedEmail implements ShouldQueueAfterCommit
{
    public string $queue = 'emails';

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    public function handle(BookingCreated $event): void
    {
        $booking = $event->booking;

        Mail::to($booking->user->email)->send(
            new BookingStatusMail(
                booking: $booking,
                subjectLine: 'Booking created',
                messageText: 'Your booking is awaiting confirmation.',
            )
        );
    }
}
