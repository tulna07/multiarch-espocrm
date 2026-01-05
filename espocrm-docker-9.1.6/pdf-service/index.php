<?php
/**
 * PDF Generation Service - Localhost Web API
 * Listen: 127.0.0.1:8888 (NOT exposed to internet)
 * Run: php -S 127.0.0.1:8888 index.php
 */

// Configuration
const WKHTMLTOPDF_BINARY = '/usr/local/bin/wkhtmltopdf';
const TEMP_DIR = '/tmp/pdf-service';
const LOG_FILE = '/opt/pdf-service/logs/service.log';
const API_KEY = 'your-secret-api-key-min-32-chars'; // Change this!
const MAX_HTML_SIZE = 10 * 1024 * 1024; // 10MB

// Allowed options whitelist
const ALLOWED_OPTIONS = [
    'margin-top' => '/^\d+mm$/',
    'margin-bottom' => '/^\d+mm$/',
    'margin-left' => '/^\d+mm$/',
    'margin-right' => '/^\d+mm$/',
    'page-size' => '/^(A4|A3|Letter)$/',
    'dpi' => '/^\d{2,3}$/',
    'orientation' => '/^(Portrait|Landscape)$/',
];

// Ensure directories exist
if (!is_dir(TEMP_DIR)) {
    mkdir(TEMP_DIR, 0700, true);
}

$logDir = dirname(LOG_FILE);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Main router
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

switch ($requestUri) {
    case '/':
    case '/health':
        handleHealth();
        break;
    
    case '/generate':
        handleGenerate();
        break;
    
    default:
        sendResponse(404, ['error' => 'Not found']);
}

// ============================================================================
// Handlers
// ============================================================================

function handleHealth(): void
{
    sendResponse(200, [
        'status' => 'ok',
        'service' => 'PDF Generation Service',
        'wkhtmltopdf' => file_exists(WKHTMLTOPDF_BINARY) ? 'available' : 'missing',
    ]);
}

function handleGenerate(): void
{
    // Only POST allowed
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(405, ['error' => 'Method not allowed']);
        return;
    }

    // Verify API Key
    $providedKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (!hash_equals(API_KEY, $providedKey)) {
        logError('Unauthorized access attempt');
        sendResponse(401, ['error' => 'Unauthorized']);
        return;
    }

    // Parse JSON body
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(400, ['error' => 'Invalid JSON: ' . json_last_error_msg()]);
        return;
    }

    // Validate input
    if (empty($input['html']) || !is_string($input['html'])) {
        sendResponse(400, ['error' => 'Missing or invalid HTML']);
        return;
    }

    // Size limit
    if (strlen($input['html']) > MAX_HTML_SIZE) {
        sendResponse(413, ['error' => 'HTML too large (max 10MB)']);
        return;
    }

    try {
        $pdfContent = generatePdf($input['html'], $input['options'] ?? []);
        
        // Return PDF
        header('Content-Type: application/pdf');
        header('Content-Length: ' . strlen($pdfContent));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo $pdfContent;
        
    } catch (Exception $e) {
        logError('PDF generation failed: ' . $e->getMessage());
        sendResponse(500, ['error' => 'PDF generation failed']);
    }
}

// ============================================================================
// PDF Generation
// ============================================================================

function generatePdf(string $html, array $options): string
{
    $tempId = bin2hex(random_bytes(16));
    $htmlPath = TEMP_DIR . "/input-{$tempId}.html";
    $pdfPath = TEMP_DIR . "/output-{$tempId}.pdf";

    try {
        // Write HTML to temp file
        if (file_put_contents($htmlPath, $html) === false) {
            throw new RuntimeException("Failed to write HTML file");
        }

        // Build safe command
        $safeOptions = buildSafeOptions($options);
        $command = sprintf(
            '%s %s %s %s 2>&1',
            escapeshellcmd(WKHTMLTOPDF_BINARY),
            $safeOptions,
            escapeshellarg($htmlPath),
            escapeshellarg($pdfPath)
        );

        // Execute with timeout (using timeout command if available)
        $timeoutCmd = '';
        if (file_exists('/usr/bin/timeout')) {
            $timeoutCmd = '/usr/bin/timeout 30 '; // 30 seconds timeout
        }

        exec($timeoutCmd . $command, $output, $returnCode);

        // Check result
        if ($returnCode !== 0) {
            throw new RuntimeException("wkhtmltopdf failed with code {$returnCode}: " . implode("\n", $output));
        }

        if (!file_exists($pdfPath)) {
            throw new RuntimeException("PDF file not created");
        }

        // Read PDF content
        $pdfContent = file_get_contents($pdfPath);
        if ($pdfContent === false) {
            throw new RuntimeException("Failed to read PDF file");
        }

        logInfo("PDF generated successfully: {$tempId}");
        return $pdfContent;

    } finally {
        // Cleanup temp files
        @unlink($htmlPath);
        @unlink($pdfPath);
    }
}

function buildSafeOptions(array $options): string
{
    $safeArgs = [];

    foreach ($options as $key => $value) {
        // Check if option is allowed
        if (!isset(ALLOWED_OPTIONS[$key])) {
            continue;
        }

        // Validate value against pattern
        if (!preg_match(ALLOWED_OPTIONS[$key], $value)) {
            logWarning("Invalid option value: {$key}={$value}");
            continue;
        }

        // Build argument
        $safeArgs[] = '--' . $key . ' ' . escapeshellarg($value);
    }

    return implode(' ', $safeArgs);
}

// ============================================================================
// Utilities
// ============================================================================

function sendResponse(int $statusCode, array $data): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function writeLog(string $level, string $message): void
{
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[{$timestamp}] {$level}: {$message}\n";
    
    // Write to file
    @file_put_contents(LOG_FILE, $logLine, FILE_APPEND | LOCK_EX);
    
    // Also write to error_log for systemd journal (optional)
    error_log("[PDF-Service] {$level}: {$message}");
}

function logInfo(string $message): void
{
    writeLog('INFO', $message);
}

function logWarning(string $message): void
{
    writeLog('WARNING', $message);
}

function logError(string $message): void
{
    writeLog('ERROR', $message);
}

// Cleanup old temp files periodically (1% chance per request)
if (rand(1, 100) === 1) {
    cleanupOldFiles();
}

function cleanupOldFiles(): void
{
    $files = glob(TEMP_DIR . '/*');
    $now = time();
    $count = 0;

    foreach ($files as $file) {
        if (is_file($file) && ($now - filemtime($file)) > 3600) { // 1 hour old
            if (@unlink($file)) {
                $count++;
            }
        }
    }

    if ($count > 0) {
        logInfo("Cleaned up {$count} old temp files");
    }
}