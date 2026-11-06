<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ApiErrorCode;
use App\Enums\BookingStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Room\RoomAvailabilityRequest;
use App\Http\Requests\Room\StoreRoomRequest;
use App\Http\Requests\Room\UpdateRoomRequest;
use App\Http\Resources\RoomCollection;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class RoomController extends Controller
{
    public function index(
        Request $request,
        Workspace $workspace
    ): RoomCollection {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $validated['per_page'] ?? 20;

        $rooms = $workspace->rooms()
            ->where('is_active', true)
            ->orderBy('id')
            ->paginate($perPage);

        return new RoomCollection($rooms);
    }

    public function store(
        StoreRoomRequest $request,
        Workspace $workspace
    ): JsonResponse {
        Gate::authorize('create', Room::class);

        $room = $workspace->rooms()->create(
            $request->validated()
        );

        return (new RoomResource($room))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(
        UpdateRoomRequest $request,
        Room $room
    ): RoomResource {
        Gate::authorize('update', $room);

        $room->update($request->validated());

        return new RoomResource($room);
    }

    public function destroy(Room $room): Response
    {
        Gate::authorize('delete', $room);

        if ($room->bookings()->exists()) {
            throw new ApiException(
                errorCode: ApiErrorCode::ResourceHasDependencies,
                message: 'Room has bookings and cannot be deleted',
                httpStatus: Response::HTTP_CONFLICT,
            );
        }

        $room->delete();

        return response()->noContent();
    }

    public function availability(
        RoomAvailabilityRequest $request,
        Room $room
    ): JsonResponse {
        $validated = $request->validated();

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

        return response()->json([
            'data' => [
                'room_id' => $room->id,
                'starts_at' => $validated['starts_at'],
                'ends_at' => $validated['ends_at'],
                'available' => $room->is_active && ! $hasConflicts,
            ],
        ]);
    }
}
