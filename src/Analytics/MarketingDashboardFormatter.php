<?php

declare(strict_types=1);

namespace Analytics;

class MarketingDashboardFormatter
{
    /**
     * @param array<int, string> $cityMap
     */
    public static function formatDimensionValue(string $dimension, mixed $value, array $cityMap): string
    {
        if ($value === null || $value === '' || $value === 'unknown') {
            return 'Не определено';
        }

        switch ($dimension) {
            case 'gender':
                if ($value === 'male') {
                    return 'Мужчины';
                }
                if ($value === 'female') {
                    return 'Женщины';
                }
                return 'Не определено';
            case 'weekday':
                return self::weekdayName((int) $value);
            case 'payment_type':
                return self::paymentType((int) $value);
            case 'city_id':
                return $cityMap[(int) $value] ?? ('Город #' . (int) $value);
            default:
                return (string) $value;
        }
    }

    public static function calculateDelta(float|int|null $current, float|int|null $previous): ?float
    {
        if (!$previous) {
            return null;
        }

        return round((($current ?? 0) - $previous) / $previous * 100, 1);
    }

    public static function deltaClass(?float $delta, bool $positiveIsGood = true): string
    {
        if ($delta === null) {
            return 'delta-null';
        }

        if ($delta > 0) {
            return $positiveIsGood ? 'delta-up' : 'delta-down';
        }

        if ($delta < 0) {
            return $positiveIsGood ? 'delta-down' : 'delta-up';
        }

        return 'delta-null';
    }

    public static function formatDelta(?float $delta): string
    {
        return $delta === null ? '—' : sprintf('%s%%', $delta);
    }

    private static function weekdayName(int $weekday): string
    {
        $map = [
            1 => 'Понедельник',
            2 => 'Вторник',
            3 => 'Среда',
            4 => 'Четверг',
            5 => 'Пятница',
            6 => 'Суббота',
            7 => 'Воскресенье',
        ];

        return $map[$weekday] ?? 'Не определено';
    }

    private static function paymentType(int $type): string
    {
        switch ($type) {
            case 0:
                return 'Онлайн';
            case 1:
                return 'Наличные';
            case 2:
                return 'Терминал';
            default:
                return 'Не определено';
        }
    }
}
