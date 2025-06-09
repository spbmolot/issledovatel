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

        

        setInterval(() => this.checkApiStatus(), 30000);

        console.log('✅ ResearcherAI инициализирован');

    }



    bindEvents() {

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



            messageInput.addEventListener('input', () => {

                messageInput.style.height = 'auto';

                messageInput.style.height = messageInput.scrollHeight + 'px';

            });

        }



        document.querySelectorAll('.example-item').forEach(item => {

            item.addEventListener('click', () => {

                const query = item.getAttribute('data-query');

                if (messageInput) {

                    messageInput.value = query;

                    this.sendMessage();

                }

            });

        });



        const newChatBtn = document.getElementById('new-chat-btn');

        if (newChatBtn) {

            newChatBtn.addEventListener('click', () => this.createNewChat());

        }



        const settingsBtn = document.getElementById('settings-btn');

        if (settingsBtn) {

            settingsBtn.addEventListener('click', () => this.openSettings());

        }

        

        const saveSettingsBtn = document.getElementById('save-settings');

        if (saveSettingsBtn) {

            saveSettingsBtn.addEventListener('click', () => this.saveSettings());

        }



        const providerSelect = document.getElementById('ai-provider');

        if (providerSelect) {

            providerSelect.addEventListener('change', () => {

                this.handleProviderChange();

            });

        }



        const proxyEnabled = document.getElementById('proxy-enabled');

        if (proxyEnabled) {

            proxyEnabled.addEventListener('change', () => this.handleProxyToggle());

        }



        console.log('✅ События привязаны');

    }



    async checkApiStatus() {

        try {

            const response = await fetch('api/check_status.php');

            const data = await response.json();

            

            // Маппинг цветовых статусов на CSS классы

            const mapStatus = (color, message) => {

                switch(color) {

                    case 'green': return { status: 'success', message: message || 'Работает' };

                    case 'yellow': return { status: 'warning', message: message || 'Предупреждение' };

                    case 'red': return { status: 'error', message: message || 'Ошибка' };

                    default: return { status: 'error', message: message || 'Неизвестно' };

                }

            };



            // Обновляем статусы с правильным маппингом

            this.updateStatusIndicator('openai-status', mapStatus(data.openai, data.error_messages?.openai));

            this.updateStatusIndicator('deepseek-status', mapStatus(data.deepseek, data.error_messages?.deepseek));

            this.updateStatusIndicator('yandex-status', mapStatus(data.yandex, data.error_messages?.yandex));

            

        } catch (error) {

            console.error('Ошибка проверки статуса:', error);

            // Устанавливаем статусы ошибки

            this.updateStatusIndicator('openai-status', { status: 'error', message: 'Ошибка соединения' });

            this.updateStatusIndicator('deepseek-status', { status: 'error', message: 'Ошибка соединения' });

            this.updateStatusIndicator('yandex-status', { status: 'error', message: 'Ошибка соединения' });

        }

    }



    updateProviderDisplay(aiProvider) {

        const openaiStatus = document.getElementById('openai-status');

        const deepseekStatus = document.getElementById('deepseek-status');

        

        console.log('🔄 Показываем статус для провайдера:', aiProvider);

        

        if (aiProvider === 'deepseek') {

            if (openaiStatus) openaiStatus.style.display = 'none';

            if (deepseekStatus) deepseekStatus.style.display = 'flex';

        } else {

            if (openaiStatus) openaiStatus.style.display = 'flex';

            if (deepseekStatus) deepseekStatus.style.display = 'none';

        }

    }



    // НОВАЯ ФУНКЦИЯ: Трехцветная индикация статуса

    updateStatusIndicator(elementId, { status, message }) {

        const element = document.getElementById(elementId);

        if (!element) return;

        

        const circle = element.querySelector('i');

        if (!circle) return;

        

        // Убираем все предыдущие классы цветов

        circle.classList.remove('text-success', 'text-warning', 'text-danger');

        

        // Устанавливаем цвет и сообщение в зависимости от статуса

        switch (status) {

            case 'success':

                circle.classList.add('text-success');

                element.title = message;

                element.classList.add('connected');

                break;

                

            case 'warning':

                circle.classList.add('text-warning');

                element.title = message;

                element.classList.remove('connected');

                break;

                

            case 'error':

            default:

                circle.classList.add('text-danger');

                element.title = message;

                element.classList.remove('connected');

                break;

        }

        

        console.log(`📍 ${elementId}: ${status} - ${message}`);

    }



    async sendMessage() {

        const input = document.getElementById('message-input');

        const message = input.value.trim();



        if (!message || this.isProcessing) return;



        this.isProcessing = true;

        input.value = '';

        this.hideWelcomeMessage();



        this.addMessage('user', message);

        this.showLoading();



        try {

            const response = await this.processQuery(message);

            console.log('✅ Получен ответ:', response);

            this.addMessage('assistant', response.text, response.sources);

        } catch (error) {

            console.error('❌ Ошибка обработки запроса:', error);

            this.addMessage('assistant', `Произошла ошибка: ${error.message}`, []);

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

                'Content-Type': 'application/json; charset=utf-8',

            },

            body: JSON.stringify({

                query: query,

                chat_id: this.currentChatId

            })

        });



        const data = await response.json();

        

        if (!response.ok) {

            throw new Error(data.error || `HTTP Error ${response.status}`);

        }



        return data;

    }



    async loadChatHistory() {
        console.log('🔄 Загружаем историю чатов...');
        try {
            const response = await fetch('api/get_chats.php');
            console.log(`📥 Ответ get_chats.php: ${response.status} ${response.statusText}`);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const chats = await response.json();
            console.log(`📋 Получено чатов: ${chats.length}`, chats);
            
            this.renderChatHistory(chats);
            console.log('✅ История чатов обновлена в интерфейсе');
        } catch (error) {
            console.error('❌ Ошибка загрузки истории:', error);
        }
    }



    renderChatHistory(chats) {
        console.log(`🎨 Рендерим ${chats.length} чатов в интерфейс`);
        
        const container = document.getElementById('chat-history');
        if (!container) {
            console.error('❌ Контейнер chat-history не найден!');
            return;
        }
        
        console.log('🧹 Очищаем контейнер чатов');
        container.innerHTML = '';

        if (chats.length === 0) {
            console.log('📝 Нет чатов для отображения');
            container.innerHTML = '<div class="text-center text-muted py-3"><small>Нет сохраненных чатов</small></div>';
            return;
        }

        console.log('🔨 Создаем элементы чатов...');
        chats.forEach((chat, index) => {
            console.log(`  📝 Создаем чат ${index + 1}: ID=${chat.id}, title="${chat.title}"`);
            
            const chatItem = document.createElement('div');
            chatItem.className = 'chat-item d-flex align-items-center mb-2';
            chatItem.dataset.chatId = chat.id;

            const shortTitle = chat.title.length > 25 ? chat.title.substring(0, 25) + '...' : chat.title;

            chatItem.innerHTML = `
                <div class="flex-grow-1 chat-item-content" style="cursor: pointer;">
                    <div class="chat-item-title" title="${this.escapeHtml(chat.title)}">${this.escapeHtml(shortTitle)}</div>
                    <div class="chat-item-date">${new Date(chat.created_at).toLocaleDateString('ru-RU')}</div>
                </div>
                <button class="btn btn-sm btn-outline-light delete-chat-btn ms-2" title="Удалить чат">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16">
                        <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5Zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5Zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6Z"/>
                        <path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1ZM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118ZM2.5 3h11V2h-11v1Z"/>
                    </svg>
                </button>
            `;

            chatItem.querySelector('.chat-item-content').addEventListener('click', () => this.loadChat(chat.id));
            
            chatItem.querySelector('.delete-chat-btn').addEventListener('click', (e) => {
                e.stopPropagation();
                this.deleteChat(chat.id);
            });

            container.appendChild(chatItem);
        });
        
        console.log(`✅ История чатов отрендерена: ${chats.length} чатов добавлено в DOM`);
        console.log('📊 Текущее содержимое контейнера:', container.children.length, 'элементов');
    }



    async deleteChat(chatId) {
        console.log(`🗑️ Начинаем удаление чата ID: ${chatId}`);
        
        if (!confirm('Точно ли хотите удалить этот чат? Все сообщения будут потеряны.')) {
            console.log('❌ Удаление отменено пользователем');
            return;
        }
        
        try {
            console.log('📤 Отправляем запрос на удаление...');
            fetch('api/delete_chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ chat_id: chatId })
            })
            .then(async response => {
                console.log(`📥 Получен ответ: ${response.status}`);
                
                if (!response.ok) {
                    // Получаем текст ошибки для статуса 500
                    const errorText = await response.text();
                    console.log(`❌ Ошибка ${response.status}: ${errorText}`);
                    throw new Error(`HTTP ${response.status}: ${errorText}`);
                }
                
                return response.json();
            })
            .then(result => {
                console.log('✅ Ответ от сервера:', result);
                
                if (this.currentChatId === chatId) {
                    console.log('🔄 Очищаем текущий чат из интерфейса');
                    this.currentChatId = null;
                    document.getElementById('chat-messages').innerHTML = `
                        <div class="welcome-message text-center py-5">
                            <div class="welcome-icon text-primary mb-3">
                                <i class="fas fa-brain" style="font-size: 4rem;"></i>
                            </div>
                            <h3 class="mb-3">Чат удален</h3>
                            <p class="text-muted">Создайте новый чат или выберите из существующих</p>
                        </div>
                    `;
                }
                
                console.log('🔄 Обновляем список чатов...');
                this.loadChatHistory();
                this.showNotification('Чат удален', 'success');
                console.log('✅ Удаление завершено успешно');
            })
            .catch(error => {
                console.error('❌ Ошибка удаления чата:', error);
                this.showNotification('Ошибка удаления чата', 'error');
            });
        } catch (error) {
            console.error('❌ Ошибка удаления чата:', error);
            this.showNotification('Ошибка удаления чата', 'error');
        }
    }



    escapeHtml(text) {

        const div = document.createElement('div');

        div.textContent = text;

        return div.innerHTML;

    }



    async createNewChat() {

        try {

            const response = await fetch('api/create_chat.php', {

                method: 'POST',

                headers: {

                    'Content-Type': 'application/json; charset=utf-8',

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

            console.error('❌ Ошибка создания чата:', error);

        }

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

        content.innerHTML = this.formatMessage(text);



        if (sources && sources.length > 0) {

            const sourcesHtml = this.displaySources(sources);

            content.innerHTML += sourcesHtml;

        }
        
        const time = document.createElement('div');

        time.className = 'message-time';

        time.textContent = new Date().toLocaleTimeString();

        content.appendChild(time);

        messageDiv.appendChild(avatar);

        messageDiv.appendChild(content);

        messagesContainer.appendChild(messageDiv);

        this.saveMessageToChat(type, text, sources);

        this.bindSourcesEventHandlers(messageDiv);

    }



    formatMessage(text) {

        try {

            if (typeof text !== 'string') {

                text = String(text);

            }

            

            text = text.replace(/[^\u0000-\u007F\u0400-\u04FF\u0020-\u007E]/g, '');

            text = this.escapeHtml(text);

            text = text.replace(/\n/g, '<br>');

            text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

            text = text.replace(/\*(.*?)\*/g, '<em>$1</em>');

            

            return text;

        } catch (e) {

            console.error('Ошибка форматирования сообщения:', e);

            return 'Ошибка отображения сообщения';

        }

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



    async saveMessageToChat(type, text, sources) {

        if (!this.currentChatId) return;



        try {

            await fetch('api/save_message.php', {

                method: 'POST',

                headers: {

                    'Content-Type': 'application/json; charset=utf-8',

                },

                body: JSON.stringify({

                    chat_id: this.currentChatId,

                    type: type,

                    text: text,

                    sources: sources

                })

            });
            
            // Обновляем список чатов после первого пользовательского сообщения
            // (чат может быть переименован на сервере)
            if (type === 'user') {
                this.loadChatHistory();
            }

        } catch (error) {

            console.error('❌ Ошибка сохранения сообщения:', error);

        }

    }



    async loadChat(chatId) {

        try {

            console.log('📖 Загрузка чата:', chatId);
            
            // Устанавливаем текущий чат
            this.currentChatId = chatId;
            
            // Получаем данные чата и его сообщения
            const response = await fetch(`api/get_chat.php?id=${chatId}`);
            
            if (!response.ok) {

                throw new Error('Ошибка загрузки чата');

            }
            
            // Проверяем, что ответ не пустой
            const responseText = await response.text();
            console.log('📄 Raw response:', responseText);
            
            if (!responseText.trim()) {
                throw new Error('Пустой ответ от сервера');
            }
            
            const data = JSON.parse(responseText);
            
            console.log('✅ Данные чата загружены:', data);
            
            // Очищаем контейнер сообщений
            const messagesContainer = document.getElementById('chat-messages');

            messagesContainer.innerHTML = '';
            
            // Отображаем сообщения
            if (data.messages && data.messages.length > 0) {
                
                console.log('🔄 Обрабатываем', data.messages.length, 'сообщений');

                data.messages.forEach((message, index) => {

                    console.log(`📝 Сообщение ${index + 1}:`, {
                        id: message.id,
                        type: message.type,
                        sourcesRaw: message.sources,
                        sourcesType: typeof message.sources
                    });

                    // Безопасный парсинг sources
                    let sources = [];
                    if (message.sources && typeof message.sources === 'string' && message.sources.trim() !== '') {
                        try {
                            console.log(`🔍 Парсим sources для сообщения ${message.id}:`, message.sources);
                            sources = JSON.parse(message.sources);
                            console.log(`✅ Sources распарсены для ${message.id}:`, sources);
                        } catch (e) {
                            console.warn('⚠️ Ошибка парсинга sources для сообщения:', message.id, e);
                            console.warn('❌ Проблемный sources:', message.sources);
                            sources = [];
                        }
                    } else if (Array.isArray(message.sources)) {
                        // Если sources уже массив
                        sources = message.sources;
                        console.log(`✅ Sources уже массив для ${message.id}:`, sources);
                    } else {
                        console.log(`ℹ️ Sources пустые для сообщения ${message.id}`);
                    }

                    this.addMessageToUI(message.type, message.text, sources);

                });
            
            }
            
            // Скроллим вниз
            this.scrollToBottom();
            
            // Скрываем приветственное сообщение
            this.hideWelcomeMessage();
            
            console.log('✅ Чат загружен успешно');
            
        } catch (error) {

            console.error('❌ Ошибка загрузки чата:', error);

            this.showNotification('Ошибка загрузки чата', 'error');

        }

    }



    addMessageToUI(type, text, sources = []) {

        const messagesContainer = document.getElementById('chat-messages');

        const messageDiv = document.createElement('div');

        messageDiv.className = `message ${type}-message mb-3`;



        const content = document.createElement('div');

        content.className = 'message-content';

        content.innerHTML = this.formatMessage(text);



        // Добавляем источники если есть
        if (sources && sources.length > 0) {
            const sourcesHtml = this.displaySources(sources);
            content.innerHTML += sourcesHtml;
        }
        
        const time = document.createElement('div');

        time.className = 'message-time';

        time.textContent = new Date().toLocaleTimeString();

        content.appendChild(time);

        messageDiv.appendChild(content);

        messagesContainer.appendChild(messageDiv);

        this.bindSourcesEventHandlers(messageDiv);

    }



    openSettings() {

        const modal = new bootstrap.Modal(document.getElementById('settingsModal'));

        modal.show();

    }



    loadSettings() {

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

                    'Content-Type': 'application/json; charset=utf-8',

                },

                body: JSON.stringify(settings)

            });



            const result = await response.json();



            if (response.ok) {

                localStorage.setItem('researcher_settings', JSON.stringify(settings));

                const modal = bootstrap.Modal.getInstance(document.getElementById('settingsModal'));

                modal.hide();

                

                // Проверяем статус после сохранения

                setTimeout(() => this.checkApiStatus(), 1000);

                this.showNotification('Настройки сохранены успешно', 'success');

            } else {

                throw new Error(result.error || 'Ошибка сохранения');

            }

        } catch (error) {

            console.error('❌ Ошибка сохранения настроек:', error);

            this.showNotification('Ошибка при сохранении: ' + error.message, 'error');

        }

    }



    handleProviderChange() {

        const provider = document.getElementById('ai-provider')?.value;

        const openaiGroup = document.getElementById('openai-key-group');

        const deepseekGroup = document.getElementById('deepseek-key-group');

        const proxyGroup = document.getElementById('proxy-toggle-group');



        if (provider === 'deepseek') {

            if (openaiGroup) openaiGroup.style.display = 'none';

            if (deepseekGroup) deepseekGroup.style.display = 'block';

            if (proxyGroup) proxyGroup.style.display = 'none';

        } else {

            if (openaiGroup) openaiGroup.style.display = 'block';

            if (deepseekGroup) deepseekGroup.style.display = 'none';

            if (proxyGroup) proxyGroup.style.display = 'block';

        }

        

        this.updateProviderDisplay(provider);

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

    // Генерация ссылки на Яндекс.Диск для файла
    generateYandexDiskUrl(fileName) {
        try {
            // Получаем базовую папку из настроек
            const folderElement = document.getElementById('yandex-folder');
            const folderPath = folderElement ? folderElement.value.trim() : '/2 АКТУАЛЬНЫЕ ПРАЙСЫ';
            
            // Кодируем имя файла и путь к папке для URL
            const encodedFolder = encodeURIComponent(folderPath.replace(/^\/+/, ''));
            const encodedFileName = encodeURIComponent(fileName);
            
            // Формируем базовый URL для открытия файла в Яндекс.Диске
            // Используем публичную ссылку для просмотра файла
            const yandexDiskBaseUrl = 'https://disk.yandex.ru/d/';
            
            // Для корректной работы нужно получить публичную ссылку через API
            // Пока что создаем ссылку для поиска файла в папке
            const searchUrl = `https://disk.yandex.ru/client/disk/${encodedFolder}?idApp=client&dialog=slider&idDialog=%2Fdisk%2F${encodedFolder}%2F${encodedFileName}`;
            
            return searchUrl;
        } catch (error) {
            console.error('Ошибка генерации URL для Яндекс.Диска:', error);
            return '#';
        }
    }

    // Отображение источников данных
    displaySources(sources) {
        if (!sources || sources.length === 0) {
            return '';
        }

        const sourceCount = sources.length;
        const maxVisibleSources = 5;
        
        let sourcesHtml = `
            <div class="message-sources">
                <div class="sources-header">
                    <i class="fas fa-database"></i>
                    Источники данных (${sourceCount}):
                </div>
                <div class="sources-container">
        `;

        // Отображаем первые 5 источников
        const visibleSources = sources.slice(0, maxVisibleSources);
        
        visibleSources.forEach((source, index) => {
            const fileName = source.name || source.file_name || 'Неизвестный файл';
            const similarity = source.similarity ? Math.round(source.similarity * 100) : null;
            
            sourcesHtml += `
                <div class="source-item">
                    <a href="#" class="source-link" data-filename="${fileName}">
                        <i class="fas fa-external-link-alt"></i>
                        ${fileName}
                    </a>
                    ${similarity !== null ? `<span class="badge badge-info ms-2">${similarity}%</span>` : ''}
                </div>
            `;
        });

        sourcesHtml += '</div>';

        // Добавляем кнопку "Показать еще", если источников больше 5
        if (sourceCount > maxVisibleSources) {
            const hiddenCount = sourceCount - maxVisibleSources;
            sourcesHtml += `
                <button class="btn btn-sm btn-outline-secondary mt-2 toggle-sources-btn" data-all-sources='${JSON.stringify(sources)}'>
                    <i class="fas fa-chevron-down"></i>
                    Показать еще ${hiddenCount} источников
                </button>
            `;
        }

        sourcesHtml += '</div>';
        return sourcesHtml;
    }

    // Привязка обработчиков событий для источников
    bindSourcesEventHandlers(messageElement) {
        // Обработчики для ссылок на источники
        const sourceLinks = messageElement.querySelectorAll('.source-link');
        sourceLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const fileName = link.dataset.filename;
                this.openYandexDiskFile(fileName);
            });
        });

        // Обработчик для кнопки переключения источников
        const toggleButton = messageElement.querySelector('.toggle-sources-btn');
        if (toggleButton) {
            toggleButton.addEventListener('click', (e) => {
                this.toggleAllSources(e);
            });
        }
    }

    // Переключение отображения всех источников
    toggleAllSources(event) {
        const button = event.target.closest('button');
        const sourcesContainer = button.previousElementSibling;
        const allSources = JSON.parse(button.dataset.allSources || '[]');
        const maxVisibleSources = 5;
        
        if (button.dataset.expanded === 'true') {
            // Скрыть дополнительные источники
            const visibleSources = sourcesContainer.children;
            for (let i = visibleSources.length - 1; i >= maxVisibleSources; i--) {
                visibleSources[i].remove();
            }
            
            const hiddenCount = allSources.length - maxVisibleSources;
            button.innerHTML = `
                <i class="fas fa-chevron-down"></i>
                Показать еще ${hiddenCount} источников
            `;
            button.dataset.expanded = 'false';
        } else {
            // Показать все источники
            const hiddenSources = allSources.slice(maxVisibleSources);
            
            hiddenSources.forEach(source => {
                const fileName = source.name || source.file_name || 'Неизвестный файл';
                const similarity = source.similarity ? Math.round(source.similarity * 100) : null;
                
                const sourceItem = document.createElement('div');
                sourceItem.className = 'source-item';
                sourceItem.innerHTML = `
                    <a href="#" class="source-link" data-filename="${fileName}">
                        <i class="fas fa-external-link-alt"></i>
                        ${fileName}
                    </a>
                    ${similarity !== null ? `<span class="badge badge-info ms-2">${similarity}%</span>` : ''}
                `;
                
                // Привязываем обработчик события для новой ссылки
                const newLink = sourceItem.querySelector('.source-link');
                newLink.addEventListener('click', (e) => {
                    e.preventDefault();
                    const fileName = newLink.dataset.filename;
                    this.openYandexDiskFile(fileName);
                });
                
                sourcesContainer.appendChild(sourceItem);
            });
            
            button.innerHTML = `
                <i class="fas fa-chevron-up"></i>
                Скрыть дополнительные источники
            `;
            button.dataset.expanded = 'true';
        }
    }

    // Показать уведомление
    showToast(message, type = 'info') {
        // Создаем элемент уведомления
        const toast = document.createElement('div');
        toast.className = `alert alert-${type === 'success' ? 'success' : type === 'warning' ? 'warning' : type === 'error' ? 'danger' : 'info'} position-fixed`;
        toast.style.cssText = `
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            animation: slideInRight 0.3s ease-out;
        `;
        
        toast.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : type === 'error' ? 'times-circle' : 'info-circle'} me-2"></i>
                <span>${message}</span>
                <button type="button" class="btn-close ms-auto" onclick="this.parentElement.parentElement.remove()"></button>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        // Автоматически скрыть через 3 секунды
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 3000);
        
        return toast;
    }

    // Открытие файла в Яндекс.Диске
    async openYandexDiskFile(fileName) {
        try {
            // Показываем уведомление о загрузке
            const loadingToast = this.showToast('Получение ссылки на файл...', 'info');
            
            // Вызываем API для получения публичной ссылки
            const response = await fetch('/issledovatel/api/get_file_url.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    filename: fileName
                })
            });
            
            // Скрываем уведомление о загрузке
            if (loadingToast.parentElement) {
                loadingToast.remove();
            }
            
            if (response.ok) {
                const data = await response.json();
                
                if (data.success && data.url) {
                    // Успешно получили ссылку
                    this.showToast('Файл открывается в новой вкладке', 'success');
                    window.open(data.url, '_blank');
                    return;
                }
            }
            
            // Если API не вернул ссылку, используем fallback
            this.showToast('Переход к поиску файла на Яндекс.Диске', 'warning');
            
            // Fallback - открываем страницу поиска
            const folderPath = document.getElementById('yandex-folder-path') ? 
                document.getElementById('yandex-folder-path').value : 
                '/2 АКТУАЛЬНЫЕ ПРАЙСЫ';
            
            const searchUrl = `https://disk.yandex.ru/client/disk${folderPath}?search=${encodeURIComponent(fileName)}`;
            window.open(searchUrl, '_blank');
            
        } catch (error) {
            console.error('Ошибка при получении ссылки на файл:', error);
            
            // Показываем ошибку и используем fallback
            this.showToast('Ошибка получения ссылки. Переход к поиску файла', 'error');
            
            // Fallback - открываем страницу поиска
            const folderPath = document.getElementById('yandex-folder-path') ? 
                document.getElementById('yandex-folder-path').value : 
                '/2 АКТУАЛЬНЫЕ ПРАЙСЫ';
            
            const searchUrl = `https://disk.yandex.ru/client/disk${folderPath}?search=${encodeURIComponent(fileName)}`;
            window.open(searchUrl, '_blank');
        }
    }
}
