<?php

echo "Исправляем дублированный метод downloadFile в YandexDiskClient.php...\n";

$filePath = 'classes/YandexDiskClient.php';

if (!file_exists($filePath)) {
    die("Файл {$filePath} не найден!\n");
}

$content = file_get_contents($filePath);

// Ищем все вхождения public function downloadFile
preg_match_all('/public\s+function\s+downloadFile\s*\(/m', $content, $matches, PREG_OFFSET_CAPTURE);

$count = count($matches[0]);
echo "Найдено {$count} объявлений метода downloadFile\n";

if ($count > 1) {
    echo "Обнаружено дублирование! Исправляем...\n";
    
    // Найдем первое и второе вхождение
    $firstPos = $matches[0][0][1];
    
    // Найдем второе объявление и удалим его до конца класса
    $secondPos = $matches[0][1][1];
    
    // Найдем конец второго метода (следующий } на том же уровне)
    $afterSecond = $secondPos;
    $braceCount = 0;
    $inMethod = false;
    
    for ($i = $secondPos; $i < strlen($content); $i++) {
        $char = $content[$i];
        
        if ($char === '{') {
            $braceCount++;
            $inMethod = true;
        } elseif ($char === '}') {
            $braceCount--;
            if ($inMethod && $braceCount === 0) {
                // Конец метода
                $afterSecond = $i + 1;
                break;
            }
        }
    }
    
    // Удаляем второе объявление метода
    $before = substr($content, 0, $secondPos);
    $after = substr($content, $afterSecond);
    
    $fixedContent = $before . $after;
    
    // Создаем резервную копию
    file_put_contents($filePath . '.backup', $content);
    echo "Создана резервная копия: {$filePath}.backup\n";
    
    // Сохраняем исправленный файл
    file_put_contents($filePath, $fixedContent);
    echo "Файл исправлен! Дублированный метод удален.\n";
    
} else {
    echo "Дублирования не обнаружено.\n";
}

echo "Готово!\n";
