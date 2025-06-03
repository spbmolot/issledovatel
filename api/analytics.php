<?php
// api/analytics.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $days = $_GET['days'] ?? 30;
    $analytics = getAnalytics($pdo, $days);
    
    // Get popular keywords
    $stmt = $pdo->prepare("
        SELECT keywords, COUNT(*) as usage_count
        FROM query_log 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND keywords IS NOT NULL
        GROUP BY keywords
        ORDER BY usage_count DESC
        LIMIT 10
    ");
    $stmt->execute([$days]);
    $popularKeywords = $stmt->fetchAll();
    
    // Get recent activity
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as queries
        FROM query_log 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ");
    $stmt->execute([$days]);
    $dailyActivity = $stmt->fetchAll();
    
    echo json_encode([
        'summary' => $analytics,
        'popular_keywords' => $popularKeywords,
        'daily_activity' => $dailyActivity
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>