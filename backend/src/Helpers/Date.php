<?php
namespace PixelAnalytics\Helpers;

class Date
{
    public static function now()
    {
        return new \DateTime('now');
    }

    public static function parse($dateString)
    {
        return new \DateTime($dateString);
    }

    public static function formatSql(\DateTime $dateTime)
    {
        return $dateTime->format('Y-m-d H:i:s');
    }
}
