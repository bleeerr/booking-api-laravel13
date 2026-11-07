<x-mail::message>
    # {{ $subjectLine }}

    Hello, {{ $booking->user->name }}!

    {{ $messageText }}

    **Booking ID:** {{ $booking->id }}

    **Room:** {{ $booking->room->name }}

    **Start:** {{ $booking->starts_at->format('Y-m-d H:i') }} UTC

    **End:** {{ $booking->ends_at->format('Y-m-d H:i') }} UTC

    **Total:** {{ $booking->total_price_minor }} minor units ({{ $booking->currency_code }})

    Thanks,
    {{ config('app.name') }}
</x-mail::message>
