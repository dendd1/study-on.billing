<?php

namespace App\Service;
use App\Enum\CourseEnum;

class ArrayService
{
    public static function mapToKey($array, $key): array
    {
        $result = [];
        foreach ($array as $el) {
            if (isset($result[$el[$key]])) {
                $result[$el[$key]][] = $el;
            } else {
                $result[$el[$key]] = [$el];
            }
        }
        return $result;
    }
}