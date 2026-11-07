<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingCollection;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminBookingController extends Controller
{
    public function index(Request $request): BookingCollection
    {
        Gate::authorize('viewAny', Booking::class);

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $validated['per_page'] ?? (int) config('api.pagination.default_per_page');

        $bookings = Booking::query()
            ->orderByDesc('id')
            ->paginate($perPage);

        return new BookingCollection($bookings);
    }
}
