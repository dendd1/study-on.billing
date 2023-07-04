<?php

namespace App\DTO;

use App\Entity\Course;
use App\Enum\CourseEnum;
use JMS\Serializer\Annotation as Serializer;

class CourseRequestDTO
{
    public string $name;

    public string $code;

    public ?float $price = null;

    public int $type;

    public function setName($name)
    {
        $this->name=$name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setCode($code)
    {
        $this->code=$code;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function setType($type)
    {
        $this->type=$type;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setPrice($price)
    {
        $this->price=$price;
    }

    public function getPrice()
    {
        return $this->price;
    }
}