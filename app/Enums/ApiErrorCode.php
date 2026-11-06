<?php

namespace App\Enums;

enum ApiErrorCode: int
{
    case InvalidCredentials = 1004;
    case EmailAlreadyExists = 1005;
    case ResourceHasDependencies = 2002;
}
