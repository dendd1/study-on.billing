<?php

namespace App\DTO;

use App\Entity\Course;
use App\Enum\CourseEnum;
use JMS\Serializer\Annotation as Serializer;

class CourseResponseDTO
{
    public string $code;

    public float $price;

    public string $type;

    public function __construct(Course $course)
    {
        if ($course) {
            $this->code = $course->getCode();
            $this->type = CourseEnum::NAMES[$course->getType()];
            if ($this->type != CourseEnum::FREE_NAME) {
                $this->price = $course->getPrice();
            }
        }
    }
}