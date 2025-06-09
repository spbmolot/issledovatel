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

                                    <i class="fas fa-circle me-2 text-danger"></i>

                                    <span>OpenAI</span>

                                </div>

                                <div class="status-item d-flex align-items-center mb-2" id="deepseek-status" title="Проверка подключения..." style="display:none;">

                                    <i class="fas fa-circle me-2 text-danger"></i>

                                    <span>DeepSeek</span>

                                </div>

                                <div class="status-item d-flex align-items-center mb-2" id="yandex-status" title="Проверка подключения...">

                                    <i class="fas fa-circle me-2 text-danger"></i>

                                    <span>Yandex Disk</span>

                                </div>

                            </div>

                        </div>



                        <div class="sidebar-content">

                            <button class="btn btn-light w-100 mb-3" id="new-chat-btn">

                                <i class="fas fa-plus me-2"></i>Новый чат

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

<style>

.researcher-sidebar {

    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;

}



.status-item.connected i {

    color: #28a745 !important;

}



.example-item {

    transition: all 0.2s;

    cursor: pointer;

    border: 1px solid transparent;

)



.example-item:hover {

    background: #e9ecef !important;

    border-color: #007bff;

    transform: translateY(-1px);

)



.chat-item {

    background: rgba(255,255,255,0.1);

    padding: 0.75rem;

    margin-bottom: 0.5rem;

    border-radius: 0.5rem;

    cursor: pointer;

    transition: all 0.2s;

)



.chat-item:hover {

    background: rgba(255,255,255,0.2);

)



.chat-item.active {

    background: rgba(255,255,255,0.3);

)



.chat-item-title {

    font-weight: 500;

    margin-bottom: 0.25rem;

    font-size: 0.9rem;

)



.chat-item-date {

    font-size: 0.8rem;

    opacity: 0.8;

)



.message {

    display: flex;

    margin-bottom: 1rem;

    gap: 1rem;

)



.message.user {

    flex-direction: row-reverse;

)



.message-avatar {

    width: 40px;

    height: 40px;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    color: white;

    font-size: 1.2rem;

    flex-shrink: 0;

)



.message.user .message-avatar {

    background: #007bff;

)



.message.assistant .message-avatar {

    background: #6f42c1;

)



.message-content {

    flex: 1;

    max-width: 70%;

)



.message-text {

    background: #f8f9fa;

    padding: 1rem;

    border-radius: 1rem;

    border: 1px solid #e9ecef;

)



.message.user .message-text {

    background: #007bff;

    color: white;

    border: none;

)



.message-sources {

    margin-top: 0.5rem;

    font-size: 0.9rem;

    color: #6c757d;

)



.source-link {

    display: inline-block;

    margin-right: 1rem;

    color: #007bff;

    text-decoration: none;

)



.source-link:hover {

    text-decoration: underline;

)



.message-time {

    font-size: 0.8rem;

    color: #6c757d;

    margin-top: 0.5rem;

    text-align: right;

)



.message.user .message-time {

    text-align: left;

)



.loading-overlay.show {

    display: flex !important;

)



#message-input {

    min-height: 45px;

    max-height: 120px;

}



@media (max-width: 768px) {

    .researcher-sidebar {

        margin-bottom: 1rem;

    }

    

    .chat-messages {

        min-height: 300px !important;

        max-height: 400px !important;

    }

}

.message-time {

    font-size: 0.8rem;

    color: #6c757d;

    margin-top: 0.5rem;

}

/* Loading Overlay Styles */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.loading-overlay.show {
    opacity: 1;
}

.loading-content {
    background: white;
    padding: 2rem;
    border-radius: 15px;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.progress-steps {
    max-height: 300px;
    overflow-y: auto;
}

.progress-step {
    display: flex;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f0f0f0;
    animation: slideInLeft 0.3s ease;
}

.progress-step:last-child {
    border-bottom: none;
}

.progress-step-icon {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #28a745;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 0.75rem;
    flex-shrink: 0;
}

.progress-step-icon i {
    color: white;
    font-size: 0.7rem;
}

.progress-step-text {
    flex: 1;
    font-size: 0.9rem;
    color: #333;
}

@keyframes slideInLeft {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

</style>



<!-- JavaScript встроен для избежания проблем с загрузкой -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>

// Главный класс приложения

class ResearcherAI {

    constructor() {

        this.currentChatId = null;

        this.isProcessing = false;

        this.init();

    }



    init() {

        this.bindEvents();

        this.loadChatHistory();

        this.checkApiStatus();

        this.loadSettings();

        

        // Периодически обновляем статус

        setInterval(() => this.checkApiStatus(), 30000);

    }



    bindEvents() {

        // Send message events

        const sendBtn = document.getElementById('send-btn');

        const messageInput = document.getElementById('message-input');

        

        if (sendBtn) {

            sendBtn.addEventListener('click', () => this.sendMessage());

        }

        

        if (messageInput) {

            messageInput.addEventListener('keypress', (e) => {

                if (e.key === 'Enter' && !e.shiftKey) {

                    e.preventDefault();

                    this.sendMessage();

                }

            });



            // Auto-resize

            messageInput.addEventListener('input', () => {

                messageInput.style.height = 'auto';

                messageInput.style.height = messageInput.scrollHeight + 'px';

            });

        }



        // Example queries

        document.querySelectorAll('.example-item').forEach(item => {

            item.addEventListener('click', () => {

                const query = item.getAttribute('data-query');

                if (messageInput) {

                    messageInput.value = query;

                    this.sendMessage();

                }

            });

        });



        // New chat button

        const newChatBtn = document.getElementById('new-chat-btn');

        if (newChatBtn) {

            newChatBtn.addEventListener('click', () => this.createNewChat());

        }



        // Settings

        const settingsBtn = document.getElementById('settings-btn');

        if (settingsBtn) {

            settingsBtn.addEventListener('click', () => this.openSettings());

        }

        

        const saveSettingsBtn = document.getElementById('save-settings');

        if (saveSettingsBtn) {

            saveSettingsBtn.addEventListener('click', () => this.saveSettings());

        }



        // AI Provider switcher

        const providerSelect = document.getElementById('ai-provider');

        if (providerSelect) {

            providerSelect.addEventListener('change', () => this.handleProviderChange());

        }



        // Proxy toggle

        const proxyEnabled = document.getElementById('proxy-enabled');

        if (proxyEnabled) {

            proxyEnabled.addEventListener('change', () => this.handleProxyToggle());

        }

    }



    async checkApiStatus() {

        try {

            const response = await fetch('api/check_status.php');

            if (!response.ok) {

                throw new Error('API недоступен');

            }

            

            const status = await response.json();

            

            this.updateStatusIndicator('openai-status', status.openai);

            this.updateStatusIndicator('yandex-status', status.yandex);

            

            if (status.deepseek !== undefined) {

                this.updateStatusIndicator('deepseek-status', status.deepseek);

            }

            

        } catch (error) {

            console.error('Ошибка проверки статуса:', error);

            this.updateStatusIndicator('openai-status', false);

            this.updateStatusIndicator('yandex-status', false);

        }

    }



    updateStatusIndicator(elementId, isConnected) {

        const element = document.getElementById(elementId);

        if (!element) return;

        

        const circle = element.querySelector('i');

        

        if (isConnected) {

            element.classList.add('connected');

            element.title = 'Подключено';

            if (circle) {

                circle.className = 'fas fa-circle me-2 text-success';

            }

        } else {

            element.classList.remove('connected');

            element.title = 'Не подключено';

            if (circle) {

                circle.className = 'fas fa-circle me-2 text-danger';

            }

        }

    }



    async sendMessage() {

        const input = document.getElementById('message-input');

        const message = input.value.trim();

        if (!message || this.isProcessing) return;

        this.isProcessing = true;
        input.value = '';
        this.hideWelcomeMessage();

        // Add user message and show loading
        this.addMessage('user', message);
        this.showLoading();

        try {
            const response = await this.processQuery(message);
            this.addMessage('assistant', response.response, response.sources);
        } catch (error) {
            console.error('Ошибка обработки запроса:', error);
            this.addMessage('assistant', 'Извините, произошла ошибка. Проверьте настройки API.', []);
        } finally {
            this.hideLoading();
            this.isProcessing = false;
        }

        this.scrollToBottom();

    }



    async processQuery(query) {

        const response = await fetch('api/process_query.php', {

            method: 'POST',

            headers: {

                'Content-Type': 'application/json',

            },

            body: JSON.stringify({

                query: query,

                chat_id: this.currentChatId

            })

        });



        const data = await response.json();

        // Display progress if available
        if (data.progress && Array.isArray(data.progress)) {
            this.displayProgress(data.progress);
        }

        if (!data.success) {

            throw new Error(data.error || 'Неизвестная ошибка');

        }



        return {

            response: data.response || 'Нет ответа от AI',

            sources: data.sources || []

        };

    }
    
    
    displayProgress(progressSteps) {
        const progressContainer = document.getElementById('progress-steps');
        progressContainer.innerHTML = '';
        
        progressSteps.forEach((step, index) => {
            setTimeout(() => {
                const stepElement = document.createElement('div');
                stepElement.className = 'progress-step';
                stepElement.innerHTML = `
                    <div class="progress-step-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="progress-step-text">${step}</div>
                `;
                progressContainer.appendChild(stepElement);
                
                // Auto-scroll to bottom of progress
                progressContainer.scrollTop = progressContainer.scrollHeight;
            }, index * 100); // Stagger the appearance
        });
    }

    

    addMessage(type, text, sources = []) {

        const messagesContainer = document.getElementById('chat-messages');

        const messageDiv = document.createElement('div');

        messageDiv.className = `message ${type}`;



        const avatar = document.createElement('div');

        avatar.className = 'message-avatar';

        avatar.innerHTML = type === 'user' ? '<i class="fas fa-user"></i>' : '<i class="fas fa-robot"></i>';



        const content = document.createElement('div');

        content.className = 'message-content';



        const messageText = document.createElement('div');

        messageText.className = 'message-text';

        messageText.innerHTML = this.formatMessage(text);



        content.appendChild(messageText);



        if (sources && sources.length > 0) {

            const sourcesDiv = document.createElement('div');

            sourcesDiv.className = 'message-sources';

            sourcesDiv.innerHTML = '<strong>Источники:</strong><br>';

            

            sources.forEach(source => {

                const sourceSpan = document.createElement('span');

                sourceSpan.className = 'source-link me-2';

                sourceSpan.textContent = source.name;

                sourcesDiv.appendChild(sourceSpan);

            });



            content.appendChild(sourcesDiv);

        }



        const time = document.createElement('div');

        time.className = 'message-time';

        time.textContent = new Date().toLocaleTimeString();

        content.appendChild(time);



        messageDiv.appendChild(avatar);

        messageDiv.appendChild(content);

        messagesContainer.appendChild(messageDiv);



        this.saveMessageToChat(type, text, sources);

    }



    formatMessage(text) {

        text = text.replace(/\n/g, '<br>');

        text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

        text = text.replace(/\*(.*?)\*/g, '<em>$1</em>');

        return text;

    }



    hideWelcomeMessage() {

        const welcomeMessage = document.querySelector('.welcome-message');

        if (welcomeMessage) {

            welcomeMessage.style.display = 'none';

        }

    }



    scrollToBottom() {

        const messagesContainer = document.getElementById('chat-messages');

        messagesContainer.scrollTop = messagesContainer.scrollHeight;

    }



    showLoading() {

        const overlay = document.getElementById('loading-overlay');

        overlay.classList.remove('d-none');

        overlay.classList.add('show');

    }



    hideLoading() {

        const overlay = document.getElementById('loading-overlay');

        overlay.classList.add('d-none');

        overlay.classList.remove('show');

    }



    async loadChatHistory() {

        try {

            const response = await fetch('api/get_chats.php');

            const chats = await response.json();

            this.renderChatHistory(chats);

        } catch (error) {

            console.error('Ошибка загрузки истории:', error);

        }

    }



    renderChatHistory(chats) {

        const container = document.getElementById('chat-history');

        container.innerHTML = '';



        if (chats.length === 0) {

            container.innerHTML = '<div class="text-center text-muted py-3"><small>Нет сохраненных чатов</small></div>';

            return;

        }



        chats.forEach(chat => {

            const chatItem = document.createElement('div');

            chatItem.className = 'chat-item';

            chatItem.dataset.chatId = chat.id;



            chatItem.innerHTML = `

                <div class="chat-item-title">${chat.title}</div>

                <div class="chat-item-date">${new Date(chat.created_at).toLocaleDateString()}</div>

            `;



            chatItem.addEventListener('click', () => this.loadChat(chat.id));

            container.appendChild(chatItem);

        });

    }



    async loadChat(chatId) {

        try {

            const response = await fetch(`api/get_chat.php?id=${chatId}`);

            const chatData = await response.json();

            

            this.currentChatId = chatId;

            this.renderChatMessages(chatData.messages);

            

            document.querySelectorAll('.chat-item').forEach(item => {

                item.classList.remove('active');

                if (item.dataset.chatId === chatId.toString()) {

                    item.classList.add('active');

                }

            });

        } catch (error) {

            console.error('Ошибка загрузки чата:', error);

        }

    }



    renderChatMessages(messages) {

        const container = document.getElementById('chat-messages');

        container.innerHTML = '';



        messages.forEach(message => {

            this.addMessage(message.type, message.text, message.sources || []);

        });



        this.scrollToBottom();

    }



    async createNewChat() {

        try {

            const response = await fetch('api/create_chat.php', {

                method: 'POST',

                headers: {

                    'Content-Type': 'application/json',

                },

                body: JSON.stringify({

                    title: 'Новый чат'

                })

            });



            const chatData = await response.json();

            this.currentChatId = chatData.id;

            

            const container = document.getElementById('chat-messages');

            container.innerHTML = `

                <div class="welcome-message text-center py-5">

                    <div class="welcome-icon text-primary mb-3">

                        <i class="fas fa-brain" style="font-size: 4rem;"></i>

                    </div>

                    <h3 class="mb-3">Новый чат создан</h3>

                    <p class="text-muted">Задайте вопрос о товарах или ценах</p>

                </div>

            `;

            

            this.loadChatHistory();

        } catch (error) {

            console.error('Ошибка создания чата:', error);

        }

    }



    async saveMessageToChat(type, text, sources) {

        if (!this.currentChatId) return;



        try {

            await fetch('api/save_message.php', {

                method: 'POST',

                headers: {

                    'Content-Type': 'application/json',

                },

                body: JSON.stringify({

                    chat_id: this.currentChatId,

                    type: type,

                    text: text,

                    sources: sources

                })

            });

        } catch (error) {

            console.error('Ошибка сохранения сообщения:', error);

        }

    }



    openSettings() {

        const modal = new bootstrap.Modal(document.getElementById('settingsModal'));

        modal.show();

    }



    loadSettings() {

        // Загружаем настройки из localStorage или с сервера

        const settings = JSON.parse(localStorage.getItem('researcher_settings') || '{}');

        

        const elements = {

            'ai-provider': settings.ai_provider || 'openai',

            'openai-key': settings.openai_key || '',

            'deepseek-key': settings.deepseek_key || '',

            'yandex-token': settings.yandex_token || '',

            'proxy-enabled': settings.proxy_enabled || false,

            'proxy-url': settings.proxy_url || '',

            'yandex-folder': settings.yandex_folder || '/2 АКТУАЛЬНЫЕ ПРАЙСЫ'

        };



        Object.entries(elements).forEach(([id, value]) => {

            const element = document.getElementById(id);

            if (element) {

                if (element.type === 'checkbox') {

                    element.checked = value;

                } else {

                    element.value = value;

                }

            }

        });



        this.handleProviderChange();

        this.handleProxyToggle();

    }



    async saveSettings() {

        const settings = {

            ai_provider: document.getElementById('ai-provider')?.value || 'openai',

            openai_key: document.getElementById('openai-key')?.value || '',

            deepseek_key: document.getElementById('deepseek-key')?.value || '',

            yandex_token: document.getElementById('yandex-token')?.value || '',

            proxy_enabled: document.getElementById('proxy-enabled')?.checked || false,

            proxy_url: document.getElementById('proxy-url')?.value || '',

            yandex_folder: document.getElementById('yandex-folder')?.value || '/2 АКТУАЛЬНЫЕ ПРАЙСЫ'

        };



        try {

            const response = await fetch('api/save_settings.php', {

                method: 'POST',

                headers: {

                    'Content-Type': 'application/json',

                },

                body: JSON.stringify(settings)

            });



            if (response.ok) {

                localStorage.setItem('researcher_settings', JSON.stringify(settings));

                const modal = bootstrap.Modal.getInstance(document.getElementById('settingsModal'));

                modal.hide();

                

                this.checkApiStatus();

                this.showNotification('Настройки сохранены успешно', 'success');

            } else {

                throw new Error('Ошибка сохранения');

            }

        } catch (error) {

            console.error('Ошибка сохранения настроек:', error);

            this.showNotification('Ошибка при сохранении настроек', 'error');

        }

    }



    handleProviderChange() {

        const provider = document.getElementById('ai-provider')?.value;

        const openaiGroup = document.getElementById('openai-key-group');

        const deepseekGroup = document.getElementById('deepseek-key-group');

        const proxyGroup = document.getElementById('proxy-toggle-group');

        const openaiStatus = document.getElementById('openai-status');

        const deepseekStatus = document.getElementById('deepseek-status');



        if (provider === 'openai') {

            if (openaiGroup) openaiGroup.style.display = 'block';

            if (deepseekGroup) deepseekGroup.style.display = 'none';

            if (proxyGroup) proxyGroup.style.display = 'block';

            if (openaiStatus) openaiStatus.style.display = 'flex';

            if (deepseekStatus) deepseekStatus.style.display = 'none';

        } else if (provider === 'deepseek') {

            if (openaiGroup) openaiGroup.style.display = 'none';

            if (deepseekGroup) deepseekGroup.style.display = 'block';

            if (proxyGroup) proxyGroup.style.display = 'none';

            if (openaiStatus) openaiStatus.style.display = 'none';

            if (deepseekStatus) deepseekStatus.style.display = 'flex';

        }

    }



    handleProxyToggle() {

        const enabled = document.getElementById('proxy-enabled')?.checked;

        const proxyUrlGroup = document.getElementById('proxy-url-group');

        

        if (proxyUrlGroup) {

            proxyUrlGroup.style.display = enabled ? 'block' : 'none';

        }

    }



    showNotification(message, type = 'info') {

        const notification = document.createElement('div');

        notification.className = `alert alert-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} notification`;

        notification.style.cssText = `

            position: fixed;

            top: 20px;

            right: 20px;

            z-index: 10000;

            min-width: 300px;

            animation: slideInRight 0.3s ease;

        `;

        notification.innerHTML = `

            <i class="fas fa-${type === 'error' ? 'exclamation-triangle' : type === 'success' ? 'check-circle' : 'info-circle'}"></i>

            ${message}

            <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>

        `;



        document.body.appendChild(notification);



        setTimeout(() => {

            if (notification.parentElement) {

                notification.remove();

            }

        }, 5000);

    }

)



// Инициализация приложения

document.addEventListener('DOMContentLoaded', function() {

    console.log('🎯 Исследователь AI загружен в Bitrix');

    

    // Создаем экземпляр приложения

    window.researcherAI = new ResearcherAI();

    

    // Дополнительные стили для уведомлений

    const notificationStyles = document.createElement('style');

    notificationStyles.textContent = `

        @keyframes slideInRight {

            from {

                opacity: 0;

                transform: translateX(100%);

            }

            to {

                opacity: 1;

                transform: translateX(0);

            }

        }

        

        .notification {

            display: flex;

            align-items: center;

            gap: 0.5rem;

            box-shadow: 0 4px 20px rgba(0,0,0,0.15);

            border-radius: 10px;

        }

        

        .notification .btn-close {

            margin-left: auto;

        }

    `;

    document.head.appendChild(notificationStyles);

});

</script>



<?php

echo "</div>"; 

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");

?>
