<x-mail::message>
    # Welcome, {{ $user->name }}!

    Your Coworking Booking account has been created successfully.

    You can now sign in and start booking rooms.

    **Your login:** {{ $user->email }}

    **Password:** The password you created during registration.

    For security reasons, we never send passwords by email.

    Thanks,
    {{ config('app.name') }}
</x-mail::message>
