SET NAMES utf8;
SET time_zone = '+03:00';

CREATE DATABASE IF NOT EXISTS analytics CHARACTER SET utf8 COLLATE utf8_general_ci;
USE analytics;

CREATE TABLE IF NOT EXISTS dim_user (
  user_id INT PRIMARY KEY,
  phone_sha256 BINARY(32) NULL,
  created_at DATETIME NULL,
  first_order_dt DATETIME NULL,
  last_order_dt DATETIME NULL,
  KEY idx_dim_user_last_order_dt (last_order_dt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS dim_product (
  product_id INT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  category_id INT NULL,
  category_name VARCHAR(255) NULL,
  price_current DECIMAL(10,2) NULL,
  is_active TINYINT(1) DEFAULT 1,
  city_bind INT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS dim_date (
  date_sk INT PRIMARY KEY,
  date DATE NOT NULL,
  year SMALLINT NOT NULL,
  month TINYINT NOT NULL,
  week TINYINT NOT NULL,
  dow TINYINT NOT NULL,
  is_weekend TINYINT(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS vk_profiles (
  user_id INT PRIMARY KEY,
  vk_id BIGINT NULL,
  sex TINYINT NULL,
  age SMALLINT NULL,
  city VARCHAR(128) NULL,
  occupation VARCHAR(128) NULL,
  is_closed TINYINT(1) NULL,
  last_seen DATETIME NULL,
  fetched_at DATETIME NULL,
  raw_json MEDIUMTEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS fact_orders (
  order_id INT PRIMARY KEY,
  user_id INT NOT NULL,
  order_dt DATETIME NOT NULL,
  date_sk INT NOT NULL,
  city_id INT NULL,
  city_name VARCHAR(255) NULL,
  channel VARCHAR(32) NULL,
  payment_type VARCHAR(32) NULL,
  promo_applied VARCHAR(255) NULL,
  items_qty INT NOT NULL DEFAULT 0,
  sum_goods DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  sum_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  KEY idx_fact_orders_user_id (user_id),
  KEY idx_fact_orders_order_dt (order_dt),
  KEY idx_fact_orders_date_sk (date_sk)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS fact_order_items (
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  qty INT NOT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (order_id, product_id),
  KEY idx_fact_order_items_product_id (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS summary_rfm (
  user_id INT PRIMARY KEY,
  recency_days INT NOT NULL,
  frequency_90d INT NOT NULL,
  monetary_90d DECIMAL(10,2) NOT NULL,
  r_class TINYINT NOT NULL,
  f_class TINYINT NOT NULL,
  m_class TINYINT NOT NULL,
  segment_label VARCHAR(32) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS summary_segments_daily (
  stat_date DATE NOT NULL,
  sex TINYINT NULL,
  age_bucket VARCHAR(11) NULL,
  city VARCHAR(128) NULL,
  users_cnt INT NOT NULL,
  orders_cnt INT NOT NULL,
  revenue_sum DECIMAL(12,2) NOT NULL,
  avg_check DECIMAL(10,2) NOT NULL,
  repeat_rate_90d DECIMAL(5,3) NOT NULL,
  PRIMARY KEY (stat_date, sex, age_bucket, city),
  KEY idx_summary_segments_daily_stat_date (stat_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS stg_users LIKE dim_user;
CREATE TABLE IF NOT EXISTS stg_orders LIKE fact_orders;

CREATE TABLE IF NOT EXISTS etl_watermarks (
  name VARCHAR(64) PRIMARY KEY,
  value_str VARCHAR(64) NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
