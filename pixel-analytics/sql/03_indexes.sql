USE analytics;

ALTER TABLE fact_orders
  ADD INDEX idx_fact_orders_city_date (city_id, date_sk),
  ADD INDEX idx_fact_orders_channel_date (channel, date_sk);

ALTER TABLE fact_order_items
  ADD INDEX idx_fact_order_items_order (order_id);

ALTER TABLE summary_segments_daily
  ADD INDEX idx_summary_segments_daily_city (city);

-- Опционально можно включить партиционирование по месяцам, если объем данных большой:
-- ALTER TABLE fact_orders
-- PARTITION BY RANGE (TO_DAYS(order_dt)) (
--   PARTITION p202401 VALUES LESS THAN (TO_DAYS('2024-02-01')),
--   PARTITION pmax VALUES LESS THAN MAXVALUE
-- );
