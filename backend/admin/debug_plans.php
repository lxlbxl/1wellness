<?php
require_once __DIR__ . '/../config/config.php';
$settings = Settings::getInstance();
$plans = $settings->get('payment_plans', []);
echo json_encode($plans, JSON_PRETTY_PRINT);
