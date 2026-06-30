<?php

$allowedOrigins = [
	"http://localhost",
	"http://127.0.0.1",
	"http://localhost:5500",
	"http://127.0.0.1:5500"
];

$origin = $_SERVER["HTTP_ORIGIN"] ?? "";
if (in_array($origin, $allowedOrigins, true)) {
	header("Access-Control-Allow-Origin: {$origin}");
	header("Vary: Origin");
}

header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
	http_response_code(204);
	exit;
}

require_once __DIR__ . "/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
	http_response_code(405);
	echo json_encode([
		"success" => false,
		"error" => "Method not allowed"
	], JSON_UNESCAPED_UNICODE);
	exit;
}

try {
	$stmt = $pdo->prepare(
		"SELECT id, name, position, department, group_name, image, display_order, status,
		        group_name AS group_type
		 FROM personnel
		 WHERE status = :status
		 ORDER BY display_order ASC"
	);
	$stmt->execute([
		":status" => "active"
	]);

	$items = $stmt->fetchAll();

	echo json_encode([
		"success" => true,
		"data" => $items
	], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode([
		"success" => false,
		"error" => $e->getMessage()
	], JSON_UNESCAPED_UNICODE);
}
