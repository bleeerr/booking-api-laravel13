<?php

namespace App\Enums;

enum ApiErrorCode: int
{
    case InvalidCredentials = 1004;
    case EmailAlreadyExists = 1005;
    case ResourceHasDependencies = 2002;
    case RoomInactive = 2003;
    case RoomNotAvailable = 3001;
    case BookingAlreadyCancelled = 3002;
    case InvalidBookingStatus = 3003;
}
