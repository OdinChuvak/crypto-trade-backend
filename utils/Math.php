<?php

namespace app\utils;

class Math
{
    public static function getPercent(float $number, float $percent): float
    {
        return ($number * $percent) / 100;
    }
}