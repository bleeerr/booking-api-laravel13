<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ApiErrorCode;
use App\Enums\BookingStatus;
use App\Events\BookingCancelled;
use App\Events\BookingConfirmed;
use App\Events\BookingCreated;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Http\Resources\BookingCollection;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Room;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class BookingController extends Controller
{
    public function index(Request $request): BookingCollection
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $validated['per_page'] ?? 20;

        $bookings = Booking::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->paginate($perPage);

        return new BookingCollection($bookings);
    }

    public function store(StoreBookingRequest $request): JsonResponse
    {
        Gate::authorize('create', Booking::class);

        $validated = $request->validated();

        $room = Room::findOrFail($validated['room_id']);

        if (! $room->is_active) {
            throw new ApiException(
                errorCode: ApiErrorCode::RoomInactive,
                message: 'Room is inactive',
                httpStatus: Response::HTTP_CONFLICT,
            );
        }

        $startsAt = CarbonImmutable::parse(
            $validated['starts_at']
        )->utc();

        $endsAt = CarbonImmutable::parse(
            $validated['ends_at']
        )->utc();

        $hasConflicts = $room->bookings()
            ->whereIn('status', [
                BookingStatus::Pending->value,
                BookingStatus::Confirmed->value,
            ])
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();

        if ($hasConflicts) {
            throw new ApiException(
                errorCode: ApiErrorCode::RoomNotAvailable,
                message: 'Room is not available for the selected period',
                httpStatus: Response::HTTP_CONFLICT,
            );
        }

        $seconds = $endsAt->getTimestamp() - $startsAt->getTimestamp();

        if ($seconds <= 0) {
            throw ValidationException::withMessages([
                'ends_at' => ['Invalid booking interval.'],
            ]);
        }

        $price = $room->price_per_hour_minor;
        $hours = intdiv($seconds, 3600);
        $remainingSeconds = $seconds % 3600;

        if ($hours > 0 && $price > intdiv(PHP_INT_MAX, $hours)) {
            throw ValidationException::withMessages([
                'ends_at' => ['Booking price exceeds the supported range.'],
            ]);
        }

        $base = $price * $hours;

        $fractional = intdiv($price, 3600) * $remainingSeconds
            + intdiv(($price % 3600) * $remainingSeconds + 3599, 3600);

        if ($fractional > PHP_INT_MAX - $base) {
            throw ValidationException::withMessages([
                'ends_at' => ['Booking price exceeds the supported range.'],
            ]);
        }

        $booking = Booking::create([
            'user_id' => $request->user()->id,
            'room_id' => $room->id,
            'status' => BookingStatus::Pending,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'total_price_minor' => $base + $fractional,
            'currency_code' => $room->currency_code,
            'cancelled_at' => null,
        ]);

        BookingCreated::dispatch($booking);

        return (new BookingResource($booking))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Booking $booking): BookingResource
    {
        Gate::authorize('view', $booking);

        return new BookingResource($booking);
    }

    public function cancel(Booking $booking): BookingResource
    {
        return DB::transaction(function () use ($booking): BookingResource {
            $booking = Booking::query()
                ->lockForUpdate()
                ->findOrFail($booking->id);

            Gate::authorize('cancel', $booking);

            if ($booking->status === BookingStatus::Cancelled) {
                throw new ApiException(
                    errorCode: ApiErrorCode::BookingAlreadyCancelled,
                    message: 'Booking is already cancelled',
                    httpStatus: Response::HTTP_CONFLICT,
                );
            }

            $booking->update([
                'status' => BookingStatus::Cancelled,
                'cancelled_at' => CarbonImmutable::now('UTC'),
            ]);

            BookingCancelled::dispatch($booking);

            return new BookingResource($booking);
        });
    }

    public function confirm(Booking $booking): BookingResource
    {
        return DB::transaction(function () use ($booking): BookingResource {
            $booking = Booking::query()
                ->lockForUpdate()
                ->findOrFail($booking->id);

            Gate::authorize('confirm', $booking);

            if ($booking->status !== BookingStatus::Pending) {
                throw new ApiException(
                    errorCode: ApiErrorCode::InvalidBookingStatus,
                    message: 'Booking cannot be confirmed in its current status',
                    httpStatus: Response::HTTP_CONFLICT,
                );
            }

            $booking->update([
                'status' => BookingStatus::Confirmed,
            ]);

            BookingConfirmed::dispatch($booking);

            return new BookingResource($booking);
        });
    }
}
