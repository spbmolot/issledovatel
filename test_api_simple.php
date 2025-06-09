<?php
/**
 * Простой тест API функций Исследователя AI
 */

// Отключаем кэширование для тестирования
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: text/html; charset=UTF-8');

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🧪 Простой тест API</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }
        
        .test-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .status-success { color: #28a745; }
        .status-warning { color: #ffc107; }
        .status-error { color: #dc3545; }
        
        .log-area {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            height: 300px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }
        
        .test-btn {
            margin: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="test-card">
                    <h1 class="text-center mb-4">🧪 Простой тест API Исследователя</h1>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5><i class="fas fa-heartbeat"></i> Статус системы</h5>
                            
                            <div class="mb-3">
                                <div class="d-flex align-items-center mb-2">
                                    <i id="status-openai" class="fas fa-circle me-2 status-error"></i>
                                    <span class="fw-bold">OpenAI:</span>
                                    <span id="text-openai" class="ms-2">Проверка...</span>
                                </div>
                                
                                <div class="d-flex align-items-center mb-2">
                                    <i id="status-deepseek" class="fas fa-circle me-2 status-error"></i>
                                    <span class="fw-bold">DeepSeek:</span>
                                    <span id="text-deepseek" class="ms-2">Проверка...</span>
                                </div>
                                
                                <div class="d-flex align-items-center mb-2">
                                    <i id="status-yandex" class="fas fa-circle me-2 status-error"></i>
                                    <span class="fw-bold">Яндекс.Диск:</span>
                                    <span id="text-yandex" class="ms-2">Проверка...</span>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <button class="btn btn-primary test-btn" onclick="testApiStatus()">
                                    <i class="fas fa-sync"></i> Проверить статусы
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h5><i class="fas fa-search"></i> Тест запроса</h5>
                            
                            <div class="mb-3">
                                <input type="text" class="form-control" id="test-query" 
                                       value="найди цены на ламинат" 
                                       placeholder="Введите запрос для тестирования">
                            </div>
                            
                            <div class="mb-3">
                                <button class="btn btn-success test-btn" onclick="testQuery()">
                                    <i class="fas fa-paper-plane"></i> Отправить запрос
                                </button>
                                
                                <button class="btn btn-warning test-btn" onclick="clearLog()">
                                    <i class="fas fa-eraser"></i> Очистить лог
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-12">
                            <h5><i class="fas fa-terminal"></i> Лог тестирования</h5>
                            <div id="log-area" class="log-area">
                                <div class="text-muted">Готов к тестированию...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Логирование
        function log(message, type = 'info') {
            const logArea = document.getElementById('log-area');
            const time = new Date().toLocaleTimeString();
            
            let icon = '📝';
            let colorClass = '';
            
            switch(type) {
                case 'success': icon = '✅'; colorClass = 'text-success'; break;
                case 'error': icon = '❌'; colorClass = 'text-danger'; break;
                case 'warning': icon = '⚠️'; colorClass = 'text-warning'; break;
                case 'info': icon = 'ℹ️'; colorClass = 'text-info'; break;
            }
            
            logArea.innerHTML += `<div class="${colorClass}"><small class="text-muted">[${time}]</small> ${icon} ${message}</div>`;
            logArea.scrollTop = logArea.scrollHeight;
        }

        // Очистка лога
        function clearLog() {
            document.getElementById('log-area').innerHTML = '<div class="text-muted">Лог очищен...</div>';
        }

        // Обновление статуса
        function updateStatus(service, status, message) {
            const statusIcon = document.getElementById(`status-${service}`);
            const statusText = document.getElementById(`text-${service}`);
            
            // Удаляем старые классы
            statusIcon.classList.remove('status-success', 'status-warning', 'status-error');
            
            // Добавляем новый класс
            statusIcon.classList.add(`status-${status}`);
            statusText.textContent = message;
            
            log(`${service.toUpperCase()}: ${message}`, status);
        }

        // Тест статусов API
        async function testApiStatus() {
            log('🔄 Начинаем проверку статусов API...', 'info');
            
            try {
                const response = await fetch('/issledovatel/api/check_status.php');
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                log('📡 Ответ от API получен', 'success');
                
                // Маппинг статусов
                const mapStatus = (color) => {
                    switch(color) {
                        case 'green': return 'success';
                        case 'yellow': return 'warning';
                        case 'red': return 'error';
                        default: return 'error';
                    }
                };
                
                // Обновляем статусы
                updateStatus('openai', mapStatus(data.openai), data.error_messages?.openai || 'Неизвестно');
                updateStatus('deepseek', mapStatus(data.deepseek), data.error_messages?.deepseek || 'Неизвестно');
                updateStatus('yandex', mapStatus(data.yandex), data.error_messages?.yandex || 'Неизвестно');
                
                log(`🎯 Активный AI провайдер: ${data.ai_provider}`, 'info');
                log('✅ Проверка статусов завершена', 'success');
                
            } catch (error) {
                log(`❌ Ошибка проверки статусов: ${error.message}`, 'error');
                
                // Устанавливаем статусы ошибки
                updateStatus('openai', 'error', 'Ошибка соединения');
                updateStatus('deepseek', 'error', 'Ошибка соединения');
                updateStatus('yandex', 'error', 'Ошибка соединения');
            }
        }

        // Тест запроса
        async function testQuery() {
            const query = document.getElementById('test-query').value.trim();
            
            if (!query) {
                log('❌ Введите запрос для тестирования', 'error');
                return;
            }
            
            log(`🔍 Отправляем запрос: "${query}"`, 'info');
            
            try {
                const response = await fetch('/issledovatel/api/process_query.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ query: query })
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                
                if (data.success) {
                    log('✅ Запрос выполнен успешно', 'success');
                    
                    if (data.progress && data.progress.length > 0) {
                        log(`📊 Получено этапов прогресса: ${data.progress.length}`, 'info');
                        data.progress.forEach((step, index) => {
                            log(`  📍 Этап ${index + 1}: ${step}`, 'info');
                        });
                    }
                    
                    if (data.answer) {
                        const answerPreview = data.answer.length > 200 ? 
                            data.answer.substring(0, 200) + '...' : 
                            data.answer;
                        log(`💬 Ответ: ${answerPreview}`, 'success');
                    }
                    
                    if (data.sources && data.sources.length > 0) {
                        log(`📄 Найдено источников: ${data.sources.length}`, 'info');
                    }
                    
                } else {
                    log(`❌ Ошибка в ответе: ${data.error || 'Неизвестная ошибка'}`, 'error');
                }
                
            } catch (error) {
                log(`❌ Ошибка запроса: ${error.message}`, 'error');
            }
        }

        // Автопроверка при загрузке
        document.addEventListener('DOMContentLoaded', function() {
            log('🚀 Страница загружена', 'info');
            testApiStatus();
        });
    </script>
</body>
</html>
