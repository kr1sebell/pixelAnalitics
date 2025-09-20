<?php
namespace Segmentation;

class DimensionConfig
{
    public static function list(): array
    {
        return [
            'gender' => [
                'table' => 'analytics_users',
                'field' => 'gender',
                'label' => 'Пол',
            ],
            'age_group' => [
                'table' => 'analytics_users',
                'field' => 'age_group',
                'label' => 'Возрастная группа',
            ],
            'city' => [
                'table' => 'analytics_users',
                'field' => 'city',
                'label' => 'Город',
            ],
            'occupation' => [
                'table' => 'analytics_users',
                'field' => 'occupation',
                'label' => 'Профессия',
            ],
            'weekday' => [
                'table' => 'analytics_orders',
                'field' => 'weekday',
                'label' => 'День недели заказа',
            ],
            'payment_type' => [
                'table' => 'analytics_orders',
                'field' => 'payment_type',
                'label' => 'Тип оплаты',
            ],
            'city_id' => [
                'table' => 'analytics_orders',
                'field' => 'city_id',
                'label' => 'Город доставки',
            ],
        ];
    }
}
