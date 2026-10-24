<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\ReorderLessonsRequest;

class OrderLessonsRequest extends ReorderLessonsRequest
{
    use RejectsUnknownFields;
}
