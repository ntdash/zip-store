<?php

namespace ZipStore\Supports;

use Carbon\Carbon;

abstract class Utils
{
    public static function toMSDOSDate(Carbon $dTime): int
    {
        return (max(0, $dTime->year - 1980) << 9) | ($dTime->month << 5) | ($dTime->day);
    }

    public static function toMSDOSTime(Carbon $dTime): int
    {
        return ($dTime->hour << 11) | ($dTime->minute << 5) | (int) ($dTime->second / 2);
    }
}
