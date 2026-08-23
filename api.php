<?php

header('Content-Type: application/json; charset=utf-8');

$jsonFile = __DIR__ . '/nutrition.json';

function respond($data, $status = 200)
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function readData($jsonFile)
{
    if (!file_exists($jsonFile)) {
        respond(['error' => 'nutrition.json not found'], 404);
    }

    $data = json_decode(file_get_contents($jsonFile), true);

    if (!is_array($data) || !isset($data['categories']) || !is_array($data['categories'])) {
        respond(['error' => 'Invalid nutrition.json'], 500);
    }

    return $data;
}

function saveData($jsonFile, $data)
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    if ($json === false || file_put_contents($jsonFile, $json . PHP_EOL, LOCK_EX) === false) {
        respond(['error' => 'Could not save nutrition.json'], 500);
    }
}

function requestBody()
{
    $body = json_decode(file_get_contents('php://input'), true);

    if (!is_array($body)) {
        respond(['error' => 'Invalid request body'], 400);
    }

    return $body;
}

function foodFromBody($body)
{
    $name = trim((string)($body['name'] ?? ''));

    if ($name === '') {
        respond(['error' => 'Product name is required'], 422);
    }

    $number = function ($key) use ($body) {
        if (!array_key_exists($key, $body) || $body[$key] === '' || $body[$key] === null) {
            return null;
        }

        if (!is_numeric($body[$key])) {
            respond(['error' => "Invalid value for {$key}"], 422);
        }

        return (float)$body[$key];
    };

    return [
        'name' => $name,
        'calories' => $number('calories'),
        'protein' => $number('protein'),
        'fat' => $number('fat'),
        'carbs' => $number('carbs'),
        'notes' => trim((string)($body['notes'] ?? ''))
    ];
}

$method = $_SERVER['REQUEST_METHOD'];
$data = readData($jsonFile);

if ($method === 'GET') {
    respond($data);
}

if ($method !== 'POST') {
    respond(['error' => 'Method not allowed'], 405);
}

$body = requestBody();
$action = $body['action'] ?? '';
$category = (string)($body['category'] ?? '');

if (!array_key_exists($category, $data['categories'])) {
    respond(['error' => 'Category not found'], 404);
}

if ($action === 'create') {
    $food = foodFromBody($body);
    $data['categories'][$category][] = $food;
    saveData($jsonFile, $data);
    respond(['message' => 'Product created', 'food' => $food], 201);
}

if ($action === 'update' || $action === 'delete') {
    if (!isset($body['index']) || filter_var($body['index'], FILTER_VALIDATE_INT) === false) {
        respond(['error' => 'Valid product index is required'], 422);
    }

    $index = (int)$body['index'];

    if (!isset($data['categories'][$category][$index])) {
        respond(['error' => 'Product not found'], 404);
    }

    if ($action === 'delete') {
        array_splice($data['categories'][$category], $index, 1);
        saveData($jsonFile, $data);
        respond(['message' => 'Product deleted']);
    }

    $food = foodFromBody($body);
    $data['categories'][$category][$index] = $food;
    saveData($jsonFile, $data);
    respond(['message' => 'Product updated', 'food' => $food]);
}

respond(['error' => 'Unknown action'], 400);
