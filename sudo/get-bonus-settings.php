<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

include_once('dbcon.php');

try {
    // Get all active bonus settings
    $settingsQuery = "SELECT setting_name, setting_value FROM tbl_bonus_settings WHERE is_active = 1";
    $result = mysqli_query($con, $settingsQuery);

    $settings = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $settings[$row['setting_name']] = floatval($row['setting_value']);
    }

    // Ensure all required settings exist with defaults
    $defaultSettings = [
        'base_bonus_percentage' => 5.0,
        'early_completion_bonus' => 2.5,
        'quality_bonus_threshold' => 95.0,
        'quality_bonus_percentage' => 3.0,
        'perfect_month_bonus' => 10.0
    ];

    foreach ($defaultSettings as $key => $defaultValue) {
        if (!isset($settings[$key])) {
            $settings[$key] = $defaultValue;
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $settings,
        'base_bonus_percentage' => $settings['base_bonus_percentage'],
        'early_completion_bonus' => $settings['early_completion_bonus'],
        'perfect_month_bonus' => $settings['perfect_month_bonus'],
        'quality_bonus_threshold' => $settings['quality_bonus_threshold'],
        'quality_bonus_percentage' => $settings['quality_bonus_percentage']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch bonus settings',
        'message' => $e->getMessage()
    ]);
}

mysqli_close($con);
?>