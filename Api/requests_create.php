<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/audit.php';

const MAX_MEDIA_SIZE = 20971520;

require_method('POST');

$user = require_login();

if (($user['role'] ?? '') !== 'user') {
    respond([
        'ok' => false,
        'error' => 'Forbidden',
    ], 403);
}

$contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
$isMultipart = stripos($contentType, 'multipart/form-data') !== false;

if ($isMultipart) {
    $address = trim((string) ($_POST['address'] ?? ''));
    $itemsRaw = (string) ($_POST['items'] ?? '[]');
    $items = json_decode($itemsRaw, true);

    if (!is_array($items)) {
        respond([
            'ok' => false,
            'error' => 'Items payload is invalid',
        ], 422);
    }
} else {
    $body = json_input();
    if (!is_array($body)) {
        $body = [];
    }

    $address = trim((string) ($body['address'] ?? ''));
    $items = $body['items'] ?? [];
}

if ($address === '') {
    respond([
        'ok' => false,
        'error' => 'Address is required',
    ], 422);
}

if (!is_array($items)) {
    respond([
        'ok' => false,
        'error' => 'Items must be an array',
    ], 422);
}

$cleanItems = [];

foreach ($items as $item) {
    if (!is_array($item)) {
        continue;
    }

    $name = trim((string) ($item['name'] ?? ''));
    $qty = filter_var($item['qty'] ?? null, FILTER_VALIDATE_INT);

    if ($name === '' || $qty === false || $qty <= 0) {
        continue;
    }

    $cleanItems[] = [
        'name' => $name,
        'qty' => $qty,
    ];
}

if ($cleanItems === []) {
    respond([
        'ok' => false,
        'error' => 'At least one valid item is required',
    ], 422);
}

if (!isset($_FILES['media']) || !is_array($_FILES['media'])) {
    respond([
        'ok' => false,
        'error' => 'Photo or video upload is required',
    ], 422);
}

$mediaFile = $_FILES['media'];
$uploadError = (int) ($mediaFile['error'] ?? UPLOAD_ERR_NO_FILE);

if ($uploadError !== UPLOAD_ERR_OK) {
    switch ($uploadError) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $errorMessage = 'Uploaded file exceeds the maximum allowed size';
            break;
        case UPLOAD_ERR_PARTIAL:
            $errorMessage = 'Uploaded file was only partially received';
            break;
        case UPLOAD_ERR_NO_FILE:
            $errorMessage = 'Photo or video upload is required';
            break;
        default:
            $errorMessage = 'Failed to upload file';
            break;
    }

    respond([
        'ok' => false,
        'error' => $errorMessage,
    ], 422);
}

$fileSize = (int) ($mediaFile['size'] ?? 0);
if ($fileSize <= 0) {
    respond([
        'ok' => false,
        'error' => 'Uploaded file is empty',
    ], 422);
}

if ($fileSize > MAX_MEDIA_SIZE) {
    respond([
        'ok' => false,
        'error' => 'Uploaded file exceeds the 20MB limit',
    ], 422);
}

$tmpName = (string) ($mediaFile['tmp_name'] ?? '');
if ($tmpName === '' || !is_uploaded_file($tmpName)) {
    respond([
        'ok' => false,
        'error' => 'Invalid uploaded file',
    ], 422);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$detectedMime = (string) $finfo->file($tmpName);
$allowedMimeMap = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'video/mp4' => 'mp4',
    'video/webm' => 'webm',
    'video/quicktime' => 'mov',
];

if (!isset($allowedMimeMap[$detectedMime])) {
    respond([
        'ok' => false,
        'error' => 'Unsupported file type. Allowed: JPG, PNG, WEBP, MP4, WEBM, MOV',
    ], 422);
}

$publicDir = realpath(__DIR__ . '/../public');
if ($publicDir === false) {
    respond([
        'ok' => false,
        'error' => 'Public directory not found',
    ], 500);
}

$uploadsDir = $publicDir . DIRECTORY_SEPARATOR . 'uploads';
if (!is_dir($uploadsDir) && !mkdir($uploadsDir, 0775, true) && !is_dir($uploadsDir)) {
    respond([
        'ok' => false,
        'error' => 'Failed to create uploads directory',
    ], 500);
}

$extension = $allowedMimeMap[$detectedMime];
$filename = sprintf('ewaste_%d_%d_%s.%s', (int) $user['id'], time(), bin2hex(random_bytes(4)), $extension);
$destination = $uploadsDir . DIRECTORY_SEPARATOR . $filename;
$relativePath = 'uploads/' . $filename;

if (!move_uploaded_file($tmpName, $destination)) {
    respond([
        'ok' => false,
        'error' => 'Failed to save uploaded file',
    ], 500);
}

$db = db();
$itemsJson = json_encode($cleanItems);

if ($itemsJson === false) {
    @unlink($destination);
    respond([
        'ok' => false,
        'error' => 'Failed to encode items',
    ], 500);
}

$sql = "INSERT INTO pickup_requests (user_id, address, items, status, media_path)
VALUES (:uid, :addr, :items, 'pending', :media_path)";
$stmt = $db->prepare($sql);

if (!$stmt) {
    @unlink($destination);
    respond([
        'ok' => false,
        'error' => 'Prepare failed: ' . $db->lastErrorMsg(),
    ], 500);
}

$stmt->bindValue(':uid', (int) $user['id'], SQLITE3_INTEGER);
$stmt->bindValue(':addr', $address, SQLITE3_TEXT);
$stmt->bindValue(':items', $itemsJson, SQLITE3_TEXT);
$stmt->bindValue(':media_path', $relativePath, SQLITE3_TEXT);

$res = $stmt->execute();

if (!$res) {
    @unlink($destination);
    respond([
        'ok' => false,
        'error' => 'Execute failed: ' . $db->lastErrorMsg(),
    ], 500);
}

$id = $db->lastInsertRowID();
add_status_history($id, 'pending', 'Request created', $db);

respond([
    'ok' => true,
    'message' => 'Request created',
    'request_id' => $id,
]);
