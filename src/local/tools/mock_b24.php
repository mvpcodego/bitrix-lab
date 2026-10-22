<?php
// Заглушка REST Битрикс24 для локальной проверки агента: crm.deal.add → {"result": <id>}; ?fail=1 → ошибка портала
// только для локального стенда: из внешней сети не отвечает
if (!preg_match('/^(127\.|10\.|172\.(1[6-9]|2\d|3[01])\.|192\.168\.)/', $_SERVER['REMOTE_ADDR'] ?? '')) {
    http_response_code(404);
    return;
}
header('Content-Type: application/json');
if (($_GET['fail'] ?? '') === '1') {
    http_response_code(503);
    echo json_encode(['error' => 'QUERY_LIMIT_EXCEEDED', 'error_description' => 'Too many requests']);
    return;
}
$body = json_decode(file_get_contents('php://input'), true);
file_put_contents('/tmp/mock_b24.log', date('c') . ' ' . json_encode($body, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
echo json_encode(['result' => random_int(1000, 9999)]);
