<?php

namespace App\DTO;

use App\Enum\CourseEnum;
use DateTimeImmutable;
use JMS\Serializer\Annotation as Serializer;

class PaymentResponseDTO
{
    public string $success;

    public string $type;

    public ?DateTimeImmutable $expires;

    public function __construct($status, $type, $expires)
    {
        $this->success=$status;
        $this->type=CourseEnum::NAMES[$type];
        $this->expires=$expires;
    }
}