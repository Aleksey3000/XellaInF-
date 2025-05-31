<?php
// Конфигурация - ОБНОВЛЕННЫЕ ДАННЫЕ
$username = 'Aleksey3000';     // Логин верный
$token = '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'; // Токен (замените если устарел)
$repo = 'XellaInF-';           // ИСПРАВЛЕНО: регистр и название репозитория
$dataFile = 'data.json';        // Файл верный

// Проверка авторизации
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (strpos($authHeader, 'Bearer ') !== 0) {
    http_response_code(401);
    die(json_encode(['error' => 'Не авторизован']));
}

$clientToken = substr($authHeader, 7);
if ($clientToken !== $token) {
    http_response_code(403);
    die(json_encode(['error' => 'Неверный токен']));
}

// Определяем действие
$action = $_GET['action'] ?? '';

// Загрузка данных из GitHub
if ($action === 'load') {
    // ИСПРАВЛЕННЫЙ URL
    $url = "https://api.github.com/repos/$username/$repo/contents/$dataFile";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: HotlineApp',
        "Authorization: token $token"
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if (isset($data['content'])) {
        $content = base64_decode($data['content']);
        header('Content-Type: application/json');
        echo $content;
    } else {
        // ДОБАВЛЕНА ДЕБАГ-ИНФОРМАЦИЯ
        http_response_code(500);
        echo json_encode([
            'error' => 'Ошибка загрузки данных',
            'details' => $data['message'] ?? 'Unknown error',
            'url' => $url
        ]);
    }
    exit;
}

// Сохранение данных в GitHub
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // ИСПРАВЛЕННЫЙ URL
    $url = "https://api.github.com/repos/$username/$repo/contents/$dataFile";
    
    // Получаем SHA текущей версии
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: HotlineApp',
        "Authorization: token $token"
    ]);
    
    $response = curl_exec($ch);
    $fileInfo = json_decode($response, true);
    curl_close($ch);
    
    // Исправление структуры данных (адреса)
    $postData = json_decode(file_get_contents('php://input'), true);
    $content = json_encode(
        ['addresses' => $postData['addresses'] ?? []], // ИСПРАВЛЕН КЛЮЧ
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    );
    $encodedContent = base64_encode($content);
    
    // Формируем данные для отправки
    $data = [
        'message' => 'Обновление базы адресов: ' . date('Y-m-d H:i:s'),
        'content' => $encodedContent,
        'sha' => $fileInfo['sha'] ?? null // Важно для обновления
    ];
    
    // Отправляем обновление
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: HotlineApp',
        "Authorization: token $token",
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode([
            'error' => 'Ошибка сохранения',
            'details' => json_decode($response, true),
            'http_code' => $httpCode
        ]);
    }
    exit;
}

// Неизвестное действие
http_response_code(400);
echo json_encode(['error' => 'Неизвестное действие']);
