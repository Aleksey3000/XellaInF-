<?php
// Конфигурация
$username = 'Aleksey3000';
$token = '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';
$repo = 'XellaInF-';
$dataFile = 'data.json';

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
    $url = "https://api.github.com/repos/$username/$repo/contents/$dataFile";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: HotlineApp',
        "Authorization: token $token"
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['content'])) {
            $content = base64_decode($data['content']);
            header('Content-Type: application/json');
            echo $content;
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Файл данных не найден в репозитории']);
        }
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Репозиторий или файл не найдены', 'details' => $response]);
    }
    exit;
}

// Сохранение данных в GitHub
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
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
    
    $content = json_encode(
        ['addresses' => json_decode(file_get_contents('php://input'), true)['addresses'] ?? []],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    );
    $encodedContent = base64_encode($content);
    
    $data = [
        'message' => 'Обновление базы адресов: ' . date('Y-m-d H:i:s'),
        'content' => $encodedContent,
        'sha' => $fileInfo['sha'] ?? null
    ];
    
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
    
    header('Content-Type: application/json');
    if ($httpCode >= 200 && $httpCode < 300) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode([
            'error' => 'Ошибка сохранения',
            'details' => json_decode($response, true),
            'http_code' => $httpCode
        ]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Неизвестное действие']);
