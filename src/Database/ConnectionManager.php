<?php
namespace Database;

use SafeMySQL;

class ConnectionManager
{
    private SafeMySQL $source;
    private SafeMySQL $analytics;

    public function __construct(array $config)
    {
        $this->source = new SafeMySQL($config['source_db']);
        $this->analytics = new SafeMySQL($config['analytics_db']);
    }

    public function getSource(): SafeMySQL
    {
        return $this->source;
    }

    public function getAnalytics(): SafeMySQL
    {
        return $this->analytics;
    }
}
