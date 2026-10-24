<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\PublishLessonRequest;

class PublicationLessonRequest extends PublishLessonRequest
{
    use RejectsUnknownFields;
}
