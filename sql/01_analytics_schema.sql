-- Analytics schema for PixelAnalytics
SET NAMES utf8;
SET time_zone = '+03:00';

CREATE TABLE IF NOT EXISTS dim_user (
  user_id BIGINT UNSIGNED NOT NULL,
  phone_sha256 BINARY(32) NOT NULL,
  created_at DATETIME NOT NULL,
  do_not_profile TINYINT(1) NOT NULL DEFAULT 0,
  first_order_dt DATETIME NULL,
  last_order_dt DATETIME NULL,
  PRIMARY KEY (user_id),
  KEY idx_dim_user_last_order_dt (last_order_dt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS dim_product (
  product_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  category VARCHAR(128) DEFAULT NULL,
  price_current DECIMAL(10,2) DEFAULT NULL,
  description TEXT,
  updated_at DATETIME NULL,
  PRIMARY KEY (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS dim_date (
  date_sk INT NOT NULL,
  date DATE NOT NULL,
  year SMALLINT NOT NULL,
  month TINYINT NOT NULL,
  week TINYINT NOT NULL,
  dow TINYINT NOT NULL,
  is_weekend TINYINT(1) NOT NULL DEFAULT 0,
  is_holiday_ru TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (date_sk),
  UNIQUE KEY uniq_dim_date_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS fact_orders (
  order_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  order_dt DATETIME NOT NULL,
  date_sk INT NOT NULL,
  city_id INT NULL,
  channel VARCHAR(32) DEFAULT NULL,
  payment_type VARCHAR(16) DEFAULT NULL,
  promo_applied TINYINT(1) NOT NULL DEFAULT 0,
  items_qty INT NOT NULL DEFAULT 0,
  sum_goods DECIMAL(10,2) NOT NULL DEFAULT 0,
  delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  sum_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (order_id),
  KEY idx_fact_orders_user (user_id),
  KEY idx_fact_orders_order_dt (order_dt),
  KEY idx_fact_orders_date_sk (date_sk)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS fact_order_items (
  order_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  qty INT NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (order_id, product_id),
  KEY idx_fact_order_items_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS vk_profiles (
  user_id BIGINT UNSIGNED NOT NULL,
  vk_id BIGINT UNSIGNED NOT NULL,
  sex TINYINT NULL,
  age SMALLINT NULL,
  city VARCHAR(128) NULL,
  occupation VARCHAR(128) NULL,
  is_closed TINYINT(1) NULL,
  last_seen DATETIME NULL,
  fetched_at DATETIME NOT NULL,
  raw_json MEDIUMTEXT NOT NULL,
  PRIMARY KEY (user_id),
  UNIQUE KEY uniq_vk_profiles_vk_id (vk_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS stg_users (
  id BIGINT UNSIGNED NOT NULL,
  phone VARBINARY(255) NULL,
  vk_id BIGINT UNSIGNED NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  do_not_profile TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS stg_orders (
  id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  order_dt DATETIME NOT NULL,
  city_id INT NULL,
  channel VARCHAR(32) NULL,
  payment_type VARCHAR(16) NULL,
  promo_applied TINYINT(1) NOT NULL DEFAULT 0,
  items_qty INT NOT NULL DEFAULT 0,
  sum_goods DECIMAL(10,2) NOT NULL DEFAULT 0,
  delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  sum_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  updated_at DATETIME NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS stg_order_items (
  order_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  qty INT NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (order_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS summary_rfm (
  user_id BIGINT UNSIGNED NOT NULL,
  recency_days INT NOT NULL,
  frequency_90d INT NOT NULL,
  monetary_90d DECIMAL(10,2) NOT NULL,
  r_class TINYINT NOT NULL,
  f_class TINYINT NOT NULL,
  m_class TINYINT NOT NULL,
  segment_label VARCHAR(32) NOT NULL,
  PRIMARY KEY (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS summary_segments_daily (
  stat_date DATE NOT NULL,
  sex TINYINT NULL,
  age_bucket VARCHAR(11) NOT NULL,
  city VARCHAR(128) NOT NULL,
  users_cnt INT NOT NULL DEFAULT 0,
  orders_cnt INT NOT NULL DEFAULT 0,
  revenue_sum DECIMAL(12,2) NOT NULL DEFAULT 0,
  avg_check DECIMAL(10,2) NOT NULL DEFAULT 0,
  repeat_rate_90d DECIMAL(5,3) NOT NULL DEFAULT 0,
  PRIMARY KEY (stat_date, sex, age_bucket, city)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS summary_products_daily (
  date DATE NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  orders_cnt INT NOT NULL DEFAULT 0,
  qty_sum INT NOT NULL DEFAULT 0,
  revenue_sum DECIMAL(12,2) NOT NULL DEFAULT 0,
  unique_buyers INT NOT NULL DEFAULT 0,
  PRIMARY KEY (date, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS summary_cohorts (
  cohort_month DATE NOT NULL,
  m0 INT NOT NULL DEFAULT 0,
  m1 INT NOT NULL DEFAULT 0,
  m2 INT NOT NULL DEFAULT 0,
  m3 INT NOT NULL DEFAULT 0,
  m4 INT NOT NULL DEFAULT 0,
  m5 INT NOT NULL DEFAULT 0,
  m6 INT NOT NULL DEFAULT 0,
  PRIMARY KEY (cohort_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS etl_watermarks (
  name VARCHAR(64) NOT NULL,
  value_str VARCHAR(64) NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
