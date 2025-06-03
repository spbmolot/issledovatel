
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

        

        setInterval(() => this.checkApiStatus(), 60000);

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

        console.log('🔍 Проверяем статус API (трехцветная система)...');

        try {

            const response = await fetch('api/check_status.php', {

                method: 'GET',

                headers: {

                    'Cache-Control': 'no-cache'

                }

            });

            

            if (!response.ok) {

                throw new Error(`HTTP ${response.status}`);

            }

            

            const status = await response.json();

            console.log('📊 Статус API:', status);

            

            // Обновляем трехцветные индикаторы

            this.updateColorIndicator('openai-status', status.openai, status.error_messages?.openai);

            this.updateColorIndicator('yandex-status', status.yandex, status.error_messages?.yandex);

            this.updateColorIndicator('deepseek-status', status.deepseek, status.error_messages?.deepseek);

            

            this.updateProviderDisplay(status.ai_provider);

            

        } catch (error) {

            console.error('❌ Ошибка проверки статуса:', error);

            this.updateColorIndicator('openai-status', 'red', 'Ошибка проверки');

            this.updateColorIndicator('yandex-status', 'red', 'Ошибка проверки');

            this.updateColorIndicator('deepseek-status', 'red', 'Ошибка проверки');

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

    updateColorIndicator(elementId, colorStatus, errorMessage = null) {

        const element = document.getElementById(elementId);

        if (!element) return;

        

        const circle = element.querySelector('i');

        if (!circle) return;

        

        // Убираем все предыдущие классы цветов

        circle.classList.remove('text-success', 'text-warning', 'text-danger');

        

        // Устанавливаем цвет и сообщение в зависимости от статуса

        switch (colorStatus) {

            case 'green':

                circle.classList.add('text-success');

                element.title = errorMessage || '🟢 Работает нормально';

                element.classList.add('connected');

                break;

                

            case 'yellow':

                circle.classList.add('text-warning');

                element.title = errorMessage || '🟡 Есть проблемы';

                element.classList.remove('connected');

                break;

                

            case 'red':

            default:

                circle.classList.add('text-danger');

                element.title = errorMessage || '🔴 Не работает';

                element.classList.remove('connected');

                break;

        }

        

        console.log(`📍 ${elementId}: ${colorStatus} - ${errorMessage}`);

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

        try {

            const response = await fetch('api/get_chats.php');

            const chats = await response.json();

            this.renderChatHistory(chats);

        } catch (error) {

            console.error('❌ Ошибка загрузки истории:', error);

        }

    }



    renderChatHistory(chats) {

        const container = document.getElementById('chat-history');

        if (!container) return;

        

        container.innerHTML = '';



        if (chats.length === 0) {

            container.innerHTML = '<div class="text-center text-muted py-3"><small>Нет сохраненных чатов</small></div>';

            return;

        }



        chats.forEach(chat => {

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

                    <i class="fas fa-trash-alt"></i>

                </button>

            `;



            chatItem.querySelector('.chat-item-content').addEventListener('click', () => this.loadChat(chat.id));

            

            chatItem.querySelector('.delete-chat-btn').addEventListener('click', (e) => {

                e.stopPropagation();

                this.deleteChat(chat.id);

            });



            container.appendChild(chatItem);

        });

        

        console.log('✅ История чатов отрендерена: ' + chats.length + ' чатов');

    }



    async deleteChat(chatId) {

        if (!confirm('Удалить этот чат?')) return;

        

        try {

            const response = await fetch('api/delete_chat.php', {

                method: 'POST',

                headers: {

                    'Content-Type': 'application/json',

                },

                body: JSON.stringify({

                    chat_id: chatId

                })

            });



            if (response.ok) {

                if (this.currentChatId === chatId) {

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

                this.loadChatHistory();

                this.showNotification('Чат удален', 'success');

            } else {

                throw new Error('Ошибка удаления');

            }

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

        } catch (error) {

            console.error('❌ Ошибка сохранения сообщения:', error);

        }

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



        if (provider === 'openai') {

            if (openaiGroup) openaiGroup.style.display = 'block';

            if (deepseekGroup) deepseekGroup.style.display = 'none';

            if (proxyGroup) proxyGroup.style.display = 'block';

        } else if (provider === 'deepseek') {

            if (openaiGroup) openaiGroup.style.display = 'none';

            if (deepseekGroup) deepseekGroup.style.display = 'block';

            if (proxyGroup) proxyGroup.style.display = 'none';

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

}



// Инициализация приложения

document.addEventListener('DOMContentLoaded', function() {

    console.log('🎯 Исследователь AI загружен в Bitrix');

    

    // Создаем экземпляр приложения

    window.researcherAI = new ResearcherAI();

    

    // Дополнительные стили для уведомлений и трехцветных статусов

    const statusStyles = document.createElement('style');

    statusStyles.textContent = `

        @keyframes slideInRight {

            from { opacity: 0; transform: translateX(100%); }

            to { opacity: 1; transform: translateX(0); }

        }

        

        .notification {

            display: flex;

            align-items: center;

            gap: 0.5rem;

            box-shadow: 0 4px 20px rgba(0,0,0,0.15);

            border-radius: 10px;

        }

        

        .notification .btn-close { margin-left: auto; }

        

        /* Трехцветные статусы */

        .status-item i.text-success { color: #28a745 !important; }

        .status-item i.text-warning { color: #ffc107 !important; }

        .status-item i.text-danger { color: #dc3545 !important; }

        

        /* Анимация для предупреждений */

        .status-item i.text-warning {

            animation: pulse-warning 2s infinite;

        }

        

        @keyframes pulse-warning {

            0% { opacity: 1; }

            50% { opacity: 0.6; }

            100% { opacity: 1; }

        }

        

        /* Скроллинг в чатах */

        .chat-history {

            max-height: 320px !important;

            overflow-y: auto !important;

            overflow-x: hidden;

            padding-right: 5px;

        }

        

        .chat-history::-webkit-scrollbar { width: 4px; }

        .chat-history::-webkit-scrollbar-track { 

            background: rgba(255,255,255,0.1); 

            border-radius: 2px; 

        }

        .chat-history::-webkit-scrollbar-thumb { 

            background: rgba(255,255,255,0.3); 

            border-radius: 2px; 

        }

        .chat-history::-webkit-scrollbar-thumb:hover { 

            background: rgba(255,255,255,0.5); 

        }

        

        .delete-chat-btn {

            opacity: 0;

            transition: opacity 0.2s;

            padding: 0.25rem 0.5rem !important;

            font-size: 0.75rem !important;

        }

        

        .chat-item:hover .delete-chat-btn { opacity: 1; }

    `;

    document.head.appendChild(statusStyles);

});

