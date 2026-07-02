<?php
/**
 * Shared version functions for both user and admin interfaces
 */

/**
 * Gets the current version number from the sudo/version.json file
 * @return string The current version number
 */
function getVersionNumber() {
    $versionFile = __DIR__ . '/sudo/version.json';

    // Check if version file exists
    if (!file_exists($versionFile)) {
        return "v3.0.0"; // Default version if file doesn't exist
    }

    // Read current version
    $versionData = json_decode(file_get_contents($versionFile), true);

    // Check if parsing was successful
    if (json_last_error() !== JSON_ERROR_NONE || !isset($versionData['major'])) {
        return "v3.0.0"; // Default version if file is invalid
    }

    // Return formatted version string
    return "v{$versionData['major']}.{$versionData['minor']}.{$versionData['patch']}";
}

/**
 * Gets the version description from the sudo/version.json file
 * @return string The version description
 */
function getVersionDescription() {
    $versionFile = __DIR__ . '/sudo/version.json';

    // Check if version file exists
    if (!file_exists($versionFile)) {
        return ""; // Default empty description if file doesn't exist
    }

    // Read current version
    $versionData = json_decode(file_get_contents($versionFile), true);

    // Check if parsing was successful
    if (json_last_error() !== JSON_ERROR_NONE || !isset($versionData['description'])) {
        return ""; // Default empty description if file is invalid
    }

    return $versionData['description'];
}

/**
 * Gets the last updated date from the sudo/version.json file
 * @param string $format Date format (default: 'F j, Y')
 * @return string The formatted last updated date
 */
function getVersionLastUpdated($format = 'F j, Y') {
    $versionFile = __DIR__ . '/sudo/version.json';

    // Check if version file exists
    if (!file_exists($versionFile)) {
        return date($format); // Default to current date if file doesn't exist
    }

    // Read current version
    $versionData = json_decode(file_get_contents($versionFile), true);

    // Check if parsing was successful
    if (json_last_error() !== JSON_ERROR_NONE || !isset($versionData['lastUpdated'])) {
        return date($format); // Default to current date if file is invalid
    }

    return date($format, strtotime($versionData['lastUpdated']));
}
?>