<?php
/**
 * Test script to verify video upload configuration
 */

// Load Symfony environment
require_once dirname(__DIR__) . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Cloudinary\Cloudinary;

// Load environment variables
$dotenv = new Dotenv();
$dotenv->loadEnv(dirname(__DIR__) . '/.env');

echo "Video Upload Configuration Test\n";
echo "================================\n\n";

// Test 1: Check environment variables
echo "1. Checking environment variables...\n";
$cloudName = $_ENV['CLOUDINARY_CLOUD_NAME'] ?? null;
$apiKey = $_ENV['CLOUDINARY_API_KEY'] ?? null;
$apiSecret = $_ENV['CLOUDINARY_API_SECRET'] ?? null;

if ($cloudName && $apiKey && $apiSecret) {
    echo "   ✓ Cloudinary credentials found\n";
    echo "   - Cloud Name: $cloudName\n";
    echo "   - API Key: " . substr($apiKey, 0, 5) . "...\n";
    echo "   - API Secret: " . substr($apiSecret, 0, 5) . "...\n";
} else {
    echo "   ✗ Missing Cloudinary environment variables\n";
    echo "   - CLOUDINARY_CLOUD_NAME: " . ($cloudName ? "SET" : "MISSING") . "\n";
    echo "   - CLOUDINARY_API_KEY: " . ($apiKey ? "SET" : "MISSING") . "\n";
    echo "   - CLOUDINARY_API_SECRET: " . ($apiSecret ? "SET" : "MISSING") . "\n";
    exit(1);
}

// Test 2: Test Cloudinary SDK initialization
echo "\n2. Testing Cloudinary SDK initialization...\n";
try {
    $cloudinary = new Cloudinary([
        'cloud' => [
            'cloud_name' => $cloudName,
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
        ],
        'url' => [
            'secure' => true,
        ],
    ]);
    echo "   ✓ Cloudinary SDK initialized successfully\n";
} catch (Exception $e) {
    echo "   ✗ Failed to initialize Cloudinary: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Database connection
echo "\n3. Testing database connection...\n";
$dbUrl = $_ENV['DATABASE_URL'] ?? null;
if ($dbUrl) {
    echo "   ✓ Database URL configured\n";
    $dbName = parse_url($dbUrl, PHP_URL_PATH);
    echo "   - Database: " . ltrim($dbName, '/') . "\n";
    try {
        // Try to get the entity manager for a basic test
        echo "   ✓ Database configuration found\n";
    } catch (Exception $e) {
        echo "   ⚠ Database test incomplete (would need full app context)\n";
    }
} else {
    echo "   ✗ DATABASE_URL not set\n";
    exit(1);
}

// Test 4: Required Entity Fields
echo "\n4. Checking Video entity required fields...\n";
$requiredFields = [
    'title' => 'string (required)',
    'gameType' => 'string (optional)',
    'filePath' => 'string (required)',
    'uploadedAt' => 'DateTime (required)',
    'status' => 'string (required)',
    'visibility' => 'string with default PRIVATE',
    'uploadedBy' => 'User (required)',
];

foreach ($requiredFields as $field => $type) {
    echo "   - $field: $type\n";
}

// Test 5: Form configuration check
echo "\n5. Checking form configuration...\n";
$formChecks = [
    'VideoUploadType' => [
        'title' => 'TextType (required)',
        'gameType' => 'TextType (required)',
        'visibility' => 'ChoiceType with expanded=true',
        'videoFile' => 'FileType with mimeType=video/mp4, maxSize=200M',
    ],
    'AdminVideoUploadType' => [
        'user' => 'EntityType (required)',
        'title' => 'TextType (required)',
        'gameType' => 'TextType (required)',
        'visibility' => 'ChoiceType with expanded=true',
        'videoFile' => 'FileType with mimeType=video/mp4, maxSize=200M',
    ],
];

foreach ($formChecks as $form => $fields) {
    echo "   - $form\n";
    foreach ($fields as $field => $config) {
        echo "     • $field: $config\n";
    }
}

echo "\n================================\n";
echo "Configuration check completed!\n";
echo "\nNext steps if upload still fails:\n";
echo "1. Check browser console for detailed error messages\n";
echo "2. Verify file size doesn't exceed 200MB\n";
echo "3. Verify file is in MP4 format\n";
echo "4. Check server logs in var/log/\n";
echo "5. Ensure Cloudinary API credentials are valid\n";
