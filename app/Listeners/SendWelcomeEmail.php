<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Mail\WelcomeMail;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Mail;

final class SendWelcomeEmail implements ShouldQueueAfterCommit
{
    public string $queue = 'emails';

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    public function handle(UserRegistered $event): void
    {
        Mail::to($event->user->email)->send(
            new WelcomeMail($event->user)
        );
    }
}
