<?php
namespace Analytics;

use SafeMySQL;

class AnalyticsInstaller
{
    private SafeMySQL $analytics;

    public function __construct(SafeMySQL $analytics)
    {
        $this->analytics = $analytics;
    }

    public function install(): void
    {
        $this->createMetadataTable();
        $this->createUsersTable();
        $this->createVkProfilesTable();
        $this->createOrdersTable();
        $this->createOrderItemsTable();
        $this->createDimensionMetricsTable();
        $this->createTopProductsTable();
    }

    private function createMetadataTable(): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS analytics_metadata (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(191) NOT NULL UNIQUE,
    value TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
        $this->analytics->query($sql);
    }

    private function createUsersTable(): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS analytics_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_user_id INT NOT NULL,
    vk_id BIGINT NULL,
    phone VARCHAR(32) NULL,
    email VARCHAR(191) NULL,
    first_name VARCHAR(191) NULL,
    last_name VARCHAR(191) NULL,
    birthday DATE NULL,
    gender ENUM('male','female','unknown') DEFAULT 'unknown',
    city VARCHAR(191) NULL,
    occupation VARCHAR(191) NULL,
    age INT NULL,
    age_group VARCHAR(32) NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    first_order_at DATETIME NULL,
    last_order_at DATETIME NULL,
    total_orders INT DEFAULT 0,
    total_revenue DECIMAL(10,2) DEFAULT 0,
    avg_receipt DECIMAL(10,2) DEFAULT 0,
    total_items INT DEFAULT 0,
    UNIQUE KEY uk_source_user (source_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
        $this->analytics->query($sql);
    }

    private function createVkProfilesTable(): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS analytics_vk_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vk_id BIGINT NOT NULL,
    first_name VARCHAR(191) NULL,
    last_name VARCHAR(191) NULL,
    sex TINYINT NULL,
    bdate VARCHAR(32) NULL,
    city VARCHAR(191) NULL,
    country VARCHAR(191) NULL,
    occupation VARCHAR(191) NULL,
    domain VARCHAR(191) NULL,
    photo_url VARCHAR(255) NULL,
    data JSON NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_vk_id (vk_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
        $this->analytics->query($sql);
    }

    private function createOrdersTable(): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS analytics_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_order_id INT NOT NULL,
    source_user_id INT NOT NULL,
    order_date DATE NOT NULL,
    order_datetime DATETIME NOT NULL,
    status INT NOT NULL,
    payment_type INT NOT NULL,
    order_type ENUM('delivery','pickup') NULL,
    total_sum DECIMAL(10,2) NOT NULL,
    total_sum_discounted DECIMAL(10,2) NULL,
    total_items INT NOT NULL,
    city_id INT NULL,
    city_name VARCHAR(191) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    repeat_order TINYINT(1) DEFAULT 0,
    order_number INT DEFAULT 1,
    weekday TINYINT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_source_order (source_order_id),
    KEY idx_user_date (source_user_id, order_datetime)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
        $this->analytics->query($sql);

        $this->ensureColumnExists(
            'analytics_orders',
            'order_type',
            "ENUM('delivery','pickup') NULL AFTER payment_type"
        );
        $this->ensureColumnExists(
            'analytics_orders',
            'latitude',
            'DECIMAL(10,7) NULL'
        );
        $this->ensureColumnExists(
            'analytics_orders',
            'longitude',
            'DECIMAL(10,7) NULL'
        );
    }

    private function ensureColumnExists(string $table, string $column, string $definition): void
    {
        $columnExists = $this->analytics->getRow(
            sprintf('SHOW COLUMNS FROM `%s` LIKE ?s', $table),
            [$column]
        );

        if (!$columnExists) {
            $this->analytics->query(sprintf('ALTER TABLE `%s` ADD COLUMN `%s` %s', $table, $column, $definition));
        }
    }

    private function createOrderItemsTable(): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS analytics_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    analytics_order_id INT NOT NULL,
    source_order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_title VARCHAR(255) NOT NULL,
    category_id INT NULL,
    category_name VARCHAR(255) NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    revenue DECIMAL(10,2) NOT NULL,
    is_gift TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_order_product (analytics_order_id, product_id, product_title(64)),
    CONSTRAINT fk_order FOREIGN KEY (analytics_order_id) REFERENCES analytics_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
        $this->analytics->query($sql);
    }

    private function createDimensionMetricsTable(): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS analytics_dimension_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dimension VARCHAR(64) NOT NULL,
    dimension_value VARCHAR(255) NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    total_orders INT NOT NULL,
    total_revenue DECIMAL(10,2) NOT NULL,
    total_customers INT NOT NULL,
    new_customers INT NOT NULL,
    repeat_customers INT NOT NULL,
    repeat_rate DECIMAL(5,2) NOT NULL,
    avg_receipt DECIMAL(10,2) NOT NULL,
    avg_frequency DECIMAL(10,2) NOT NULL,
    avg_items DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_dimension_period (dimension, dimension_value, period_start, period_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
        $this->analytics->query($sql);
    }

    private function createTopProductsTable(): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS analytics_dimension_top_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dimension VARCHAR(64) NOT NULL,
    dimension_value VARCHAR(255) NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    products JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_dimension_top (dimension, dimension_value, period_start, period_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
        $this->analytics->query($sql);
    }
}
