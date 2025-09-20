<?php
class Date
{
    public static function parseOrderDT($dateStr, $timeStr)
    {
        if ($dateStr === null) {
            return null;
        }
        $dateStr = trim($dateStr);
        if ($dateStr === '') {
            return null;
        }
        $date = null;
        if (strpos($dateStr, '.') !== false) {
            $date = DateTime::createFromFormat('d.m.Y', $dateStr);
        } elseif (strpos($dateStr, '/') !== false) {
            $date = DateTime::createFromFormat('Y/m/d', $dateStr);
            if (!$date) {
                $date = DateTime::createFromFormat('d/m/Y', $dateStr);
            }
        } else {
            $date = DateTime::createFromFormat('Y-m-d', $dateStr);
            if (!$date) {
                $date = DateTime::createFromFormat('d-m-Y', $dateStr);
            }
        }
        if (!$date) {
            return null;
        }
        $time = '00:00:00';
        if ($timeStr !== null) {
            $timeStr = trim($timeStr);
            if ($timeStr !== '') {
                $timeParsed = DateTime::createFromFormat('H:i:s', $timeStr);
                if (!$timeParsed) {
                    $timeParsed = DateTime::createFromFormat('H:i', $timeStr);
                }
                if ($timeParsed) {
                    $time = $timeParsed->format('H:i:s');
                }
            }
        }
        return $date->format('Y-m-d') . ' ' . $time;
    }

    public static function toDateSk($datetime)
    {
        $ts = strtotime($datetime);
        if ($ts === false) {
            return null;
        }
        return (int)date('Ymd', $ts);
    }

    public static function toDate($datetime)
    {
        $ts = strtotime($datetime);
        if ($ts === false) {
            return null;
        }
        return date('Y-m-d', $ts);
    }
}
