-- Additional indexes for analytics workload
ALTER TABLE fact_orders
  ADD KEY idx_fact_orders_channel (channel),
  ADD KEY idx_fact_orders_payment_type (payment_type);

ALTER TABLE fact_order_items
  ADD KEY idx_fact_order_items_order (order_id);

ALTER TABLE vk_profiles
  ADD KEY idx_vk_profiles_fetched_at (fetched_at);

ALTER TABLE summary_rfm
  ADD KEY idx_summary_rfm_segment (segment_label);

ALTER TABLE summary_segments_daily
  ADD KEY idx_summary_segments_daily_city (city);

ALTER TABLE summary_products_daily
  ADD KEY idx_summary_products_daily_product (product_id);

ALTER TABLE summary_cohorts
  ADD KEY idx_summary_cohorts_month (cohort_month);
