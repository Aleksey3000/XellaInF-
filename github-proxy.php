<?php
// Конфигурация - ЗАМЕНИТЕ ЭТИ ДАННЫЕ НА ВАШИ!
$username = 'ВАШ_GITHUB_ЛОГИН';     // Ваш логин на GitHub
$token = 'ВАШ_GITHUB_ТОКЕН';        // Ваш Personal Access Token
$repo = 'ВАШ_РЕПОЗИТОРИЙ';         // Название репозитория (например: hotline-database)
$dataFile = 'data.json';            // Файл для хранения данных

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
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if (isset($data['content'])) {
        $content = base64_decode($data['content']);
        header('Content-Type: application/json');
        echo $content;
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка загрузки данных']);
    }
    exit;
}

// Сохранение данных в GitHub
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Получаем текущий файл (для получения SHA)
    $url = "https://api.github.com/repos/$username/$repo/contents/$dataFile";
    
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
    
    // Подготавливаем данные для сохранения
    $postData = json_decode(file_get_contents('php://input'), true);
    $content = json_encode(['addresses' => $postData['addresses'] ?? []], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $encodedContent = base64_encode($content);
    
    // Формируем данные для отправки
    $data = [
        'message' => 'Обновление базы адресов: ' . date('Y-m-d H:i:s'),
        'content' => $encodedContent,
        'sha' => $fileInfo['sha'] ?? null
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
        echo json_encode(['error' => 'Ошибка сохранения данных', 'details' => $response]);
    }
    exit;
}

// Неизвестное действие
http_response_code(400);
echo json_encode(['error' => 'Неизвестное действие']);
?>
