<?php
include '_config.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$userMessage = strtolower(trim($input['message']));

$response = match (true) {
    str_contains($userMessage, 'hello'), str_contains($userMessage, 'hi') => 'Hi there! How can I help you today?',
    str_contains($userMessage, 'price') => 'Our pricing depends on the service. Please visit our pricing page.',
    str_contains($userMessage, 'help') => 'Sure! Let me know what you need help with.',
    default => "Sorry, I didn't quite get that. Try asking something else!",
};

echo json_encode(['reply' => $response]);
?>