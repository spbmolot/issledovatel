<?php 

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php"); 

$APPLICATION->SetTitle("Исследователь"); 

echo '<div class="static_page">';



global $USER; 

if (!$USER->IsAuthorized() || !$USER->IsAdmin()) { 

    LocalRedirect("/auth/"); 

    exit; 

} 



require_once 'config/database.php';



// Get settings

$stmt = $pdo->prepare("SELECT * FROM researcher_settings WHERE id = 1");

$stmt->execute();

$settings = $stmt->fetch();

?>



<main class="researcher-page">

    <section class="hero bg-light py-4">

        <div class="container">

            <div class="page-header">

                <h1 class="text-center">

                    <i class="fas fa-brain me-2"></i>

                    Исследователь AI

                </h1>

            </div>

            <p class="lead text-center">

                Анализ прайс-листов с использованием искусственного интеллекта

            </p>

        </div>

    </section>



    <section class="content py-4">

        <div class="container-fluid">

            <div class="row">

                <!-- Sidebar -->

                <div class="col-md-3">

                    <div class="researcher-sidebar bg-primary text-white rounded p-3 mb-4">

                        <div class="sidebar-header mb-3">

                            <h5 class="mb-3">

                                <i class="fas fa-brain me-2"></i>

                                Статус системы

                            </h5>

                            <div class="status-panel">
                                <div class="status-item d-flex align-items-center mb-2" id="openai-status" title="Проверка подключения...">
                                    <i class="fas fa-circle me-2 text-danger status-icon"></i>
                                    <span class="status-text">OpenAI</span>
                                </div>
                                <div class="status-item d-flex align-items-center mb-2" id="deepseek-status" title="Проверка подключения..." style="display:none;">
                                    <i class="fas fa-circle me-2 text-danger status-icon"></i>
                                    <span class="status-text">DeepSeek</span>
                                </div>
                                <div class="status-item d-flex align-items-center mb-2" id="yandex-status" title="Проверка подключения...">
                                    <i class="fas fa-circle me-2 text-danger status-icon"></i>
                                    <span class="status-text">Yandex Disk</span>
                                </div>
                            </div>

                        </div>



                        <div class="sidebar-content">

                            <button class="btn btn-light w-100 mb-3" id="new-chat-btn">

                                <i class="fas fa-plus me-2"></i>Новый чат

                            </button>

                            <button class="btn btn-light w-100 mb-3" id="prices-status-btn">

                                <i class="fas fa-file-alt me-2"></i>Прайсы

                            </button>



                            <div class="chat-history" id="chat-history" style="max-height: 400px; overflow-y: auto;">

                                <div class="text-center text-muted py-3">

                                    <small>История чатов загружается...</small>

                                </div>

                            </div>

                        </div>



                        <div class="sidebar-footer mt-3 pt-3 border-top border-light">

                            <button class="btn btn-outline-light btn-sm w-100" id="settings-btn">

                                <i class="fas fa-cog me-2"></i>Настройки

                            </button>

                        </div>

                    </div>

                </div>



                <!-- Main Content -->

                <div class="col-md-9">

                    <div class="researcher-chat bg-white rounded shadow-sm">

                        <div class="chat-messages p-4" id="chat-messages" style="min-height: 500px; max-height: 600px; overflow-y: auto;">

                            <div class="welcome-message text-center py-5">

                                <div class="welcome-icon text-primary mb-3">

                                    <i class="fas fa-brain" style="font-size: 4rem;"></i>

                                </div>

                                <h3 class="mb-3">Добро пожаловать в Исследователь AI</h3>

                                <p class="text-muted mb-4">

                                    Задайте вопрос о товарах или ценах, и я найду релевантную информацию в ваших прайс-листах

                                </p>

                                

                                <div class="example-queries">

                                    <h5 class="mb-3">Примеры запросов:</h5>

                                    <div class="row">

                                        <div class="col-md-4 mb-2">

                                            <div class="example-item bg-light p-3 rounded cursor-pointer" data-query="Найди все предложения на iPhone 15">

                                                <i class="fas fa-mobile-alt me-2 text-primary"></i>

                                                "Найди все предложения на iPhone 15"

                                            </div>

                                        </div>

                                        <div class="col-md-4 mb-2">

                                            <div class="example-item bg-light p-3 rounded cursor-pointer" data-query="Сравни цены на ноутбук Lenovo ThinkPad">

                                                <i class="fas fa-laptop me-2 text-primary"></i>

                                                "Сравни цены на ноутбук Lenovo ThinkPad"

                                            </div>

                                        </div>

                                        <div class="col-md-4 mb-2">

                                            <div class="example-item bg-light p-3 rounded cursor-pointer" data-query="Какие поставщики предлагают мониторы Samsung?">

                                                <i class="fas fa-desktop me-2 text-primary"></i>

                                                "Какие поставщики предлагают мониторы Samsung?"

                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>



                        <div class="chat-input-container p-3 border-top">

                            <div class="input-group">

                                <textarea class="form-control" id="message-input" placeholder="Задайте вопрос о товарах..." rows="1" style="resize: none;"></textarea>

                                <button class="btn btn-primary" id="send-btn">

                                    <i class="fas fa-paper-plane"></i>

                                </button>

                            </div>

                        </div>

                        <!-- Loading Overlay with Progress -->
                        <div id="loading-overlay" class="loading-overlay d-none">
                            <div class="loading-content">
                                <div class="text-center mb-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Загрузка...</span>
                                    </div>
                                    <h5 class="mt-3 text-primary">Анализ прайс-листов</h5>
                                    <p class="text-muted">Пожалуйста, подождите...</p>
                                </div>
                                
                                <!-- Progress Steps -->
                                <div id="progress-steps" class="progress-steps">
                                    <!-- Progress items will be dynamically added here -->
                                </div>
                            </div>
                        </div>

                    </div>

                </div>

            </div>

        </div>

    </section>

</main>



<!-- Settings Modal -->

<div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel" aria-hidden="true">

    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title" id="settingsModalLabel">

                    <i class="fas fa-cog me-2"></i>Настройки Исследователя AI

                </h5>

                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

            </div>

            <div class="modal-body">

                <div class="row">

                    <div class="col-md-6">

                        <h6 class="text-primary mb-3">

                            <i class="fas fa-robot me-2"></i>AI Настройки

                        </h6>

                        

                        <div class="mb-3">

                            <label class="form-label">AI Провайдер</label>

                            <select class="form-select" id="ai-provider">

                                <option value="openai">OpenAI GPT-4</option>

                                <option value="deepseek">DeepSeek (без прокси)</option>

                            </select>

                            <div class="form-text">

                                DeepSeek работает из России без прокси

                            </div>

                        </div>



                        <div class="mb-3" id="openai-key-group">

                            <label class="form-label">OpenAI API Key</label>

                            <input type="password" class="form-control" id="openai-key" placeholder="sk-...">

                        </div>



                        <div class="mb-3" id="deepseek-key-group" style="display:none;">

                            <label class="form-label">DeepSeek API Key</label>

                            <input type="password" class="form-control" id="deepseek-key" placeholder="sk-...">

                            <div class="form-text">

                                Получите ключ на <a href="https://platform.deepseek.com/profile" target="_blank">platform.deepseek.com</a>

                            </div>

                        </div>



                        <div class="mb-3" id="proxy-toggle-group">

                            <div class="form-check form-switch">

                                <input class="form-check-input" type="checkbox" id="proxy-enabled">

                                <label class="form-check-label" for="proxy-enabled">

                                    Использовать прокси

                                </label>

                            </div>

                            <div class="form-text">

                                Включите для работы с OpenAI из России

                            </div>

                        </div>



                        <div class="mb-3" id="proxy-url-group" style="display:none;">

                            <label class="form-label">Proxy для OpenAI</label>

                            <input type="text" class="form-control" id="proxy-url" placeholder="proxy1.fineproxy.org:8085">

                            <button type="button" class="btn btn-secondary btn-sm mt-2" id="auto-config-btn">

                                <i class="fas fa-magic me-1"></i>Автонастройка FineProxy

                            </button>

                        </div>

                    </div>



                    <div class="col-md-6">

                        <h6 class="text-success mb-3">

                            <i class="fab fa-yandex me-2"></i>Yandex Disk

                        </h6>

                        

                        <div class="mb-3">

                            <label class="form-label">OAuth Token</label>

                            <input type="password" class="form-control" id="yandex-token" placeholder="y0_...">

                            <div class="form-text">

                                <a href="https://oauth.yandex.ru/authorize?response_type=token&client_id=1d0b9dd4d652455a9eb710d450ff456e" target="_blank">Получить токен</a>

                            </div>

                        </div>



                        <div class="mb-3">

                            <label class="form-label">Папка с прайс-листами</label>

                            <input type="text" class="form-control" id="yandex-folder" value="/2 АКТУАЛЬНЫЕ ПРАЙСЫ">

                        </div>

                    </div>

                </div>

            </div>

            <div class="modal-footer">

                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>

                <button type="button" class="btn btn-primary" id="save-settings">

                    <i class="fas fa-save me-1"></i>Сохранить

                </button>

            </div>

        </div>

    </div>

</div>

<!-- Prices Modal -->
<div class="modal fade" id="pricesModal" tabindex="-1" aria-labelledby="pricesModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="pricesModalLabel">Статус прайс-листов</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th style="width:90px">Статус</th>
              <th>Файл</th>
              <th>Изменён</th>
              <th style="width:140px">Действие</th>
            </tr>
          </thead>
          <tbody id="prices-table-body"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Loading Overlay -->

<div class="loading-overlay position-fixed top-0 start-0 w-100 h-100 d-none" id="loading-overlay" style="background: rgba(0,0,0,0.7); z-index: 9999;">

    <div class="d-flex align-items-center justify-content-center h-100">

        <div class="text-center text-white">

            <div class="spinner-border text-primary mb-3" role="status">

                <span class="visually-hidden">Загрузка...</span>

            </div>

            <p class="h5">Обрабатываю запрос...</p>

        </div>

    </div>

</div>



<!-- CSS Styles -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-RXf+QSDCUQs6JcpMDohbl+FZCC673dR0djYgD8Mqm0Y9zTDb4adZF+xSoMQmjlAXMj0Tke5mQ+CHgXgV1lg5iQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="assets/css/researcher.css">



<!-- JavaScript встроен для избежания проблем с загрузкой -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script src="assets/js/researcher.js"></script>

<script>
// Инициализация приложения
document.addEventListener('DOMContentLoaded', function() {
    console.log(' Исследователь AI загружен в Bitrix');
    // Создаем экземпляр приложения
    window.researcherAI = new ResearcherAI();
});
</script>



<?php

echo "</div>"; 

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");

?>
