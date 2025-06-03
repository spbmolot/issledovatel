
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

        setInterval(() => this.checkApiStatus(), 30000); // Каждые 30 секунд

    }



    bindEvents() {

        // Send message events

        document.getElementById('send-btn').addEventListener('click', () => this.sendMessage());

        document.getElementById('message-input').addEventListener('keypress', (e) => {

            if (e.key === 'Enter' && !e.shiftKey) {

                e.preventDefault();

                this.sendMessage();

            }

        });



        // Example queries

        document.querySelectorAll('.example-item').forEach(item => {

            item.addEventListener('click', () => {

                const query = item.getAttribute('data-query');

                document.getElementById('message-input').value = query;

                this.sendMessage();

            });

        });



        // New chat button

        document.getElementById('new-chat-btn').addEventListener('click', () => this.createNewChat());



        // Settings

        document.getElementById('settings-btn').addEventListener('click', () => this.openSettings());

        document.getElementById('save-settings').addEventListener('click', () => this.saveSettings());



        // Auto-resize input

        const messageInput = document.getElementById('message-input');

        messageInput.addEventListener('input', () => {

            messageInput.style.height = 'auto';

            messageInput.style.height = messageInput.scrollHeight + 'px';

        });



        // Добавляем обработчик для автонастройки прокси

        this.setupProxyAutoConfig();

    }



    setupProxyAutoConfig() {

        // Ждем загрузки DOM и находим кнопку автонастройки

        document.addEventListener('DOMContentLoaded', () => {

            this.addAutoConfigButton();

        });

        

        // Если DOM уже загружен

        if (document.readyState === 'loading') {

            this.addAutoConfigButton();

        }

    }



    addAutoConfigButton() {

        const proxyField = document.getElementById('proxy-url');

        if (proxyField && !document.getElementById('auto-config-btn')) {

            const autoButton = document.createElement('button');

            autoButton.id = 'auto-config-btn';

            autoButton.type = 'button';

            autoButton.className = 'btn btn-secondary btn-sm mt-2';

            autoButton.textContent = 'Авто-настройка';

            autoButton.onclick = () => this.setupFineProxy();

            

            proxyField.parentNode.appendChild(autoButton);

        }

    }



    async setupFineProxy() {

        const button = document.getElementById('auto-config-btn');

        const originalText = button.textContent;

        

        try {

            button.textContent = 'Настройка...';

            button.disabled = true;

            

            // Настраиваем IP для прокси

            const setupResponse = await fetch('api/proxy_setup.php', {

                method: 'POST',

                headers: { 'Content-Type': 'application/json' },

                body: JSON.stringify({ ip: '176.57.216.68' })

            });

            

            const setupResult = await setupResponse.json();

            

            if (!setupResult.success) {

                throw new Error('Ошибка настройки IP: ' + setupResult.message);

            }

            

            // Получаем рекомендуемый прокси

            const proxyResponse = await fetch('api/proxy_setup.php');

            const proxyResult = await proxyResponse.json();

            

            if (proxyResult.recommended) {

                // Вставляем прокси в поле

                document.getElementById('proxy-url').value = proxyResult.recommended;

                this.showNotification('Прокси настроен: ' + proxyResult.recommended, 'success');

            } else {

                throw new Error('Не удалось получить прокси');

            }

            

        } catch (error) {

            console.error('Ошибка автонастройки:', error);

            this.showNotification('Ошибка автонастройки: ' + error.message, 'error');

        } finally {

            button.textContent = originalText;

            button.disabled = false;

        }

    }



    async checkApiStatus() {

        try {

            const response = await fetch('api/check_status.php');

            const status = await response.json();

            

            this.updateStatusIndicator('openai-status', status.openai, status.error_messages?.openai);

            this.updateStatusIndicator('yandex-status', status.yandex, status.error_messages?.yandex);

            

            if (status.error_messages?.general) {

                this.showNotification(status.error_messages.general, 'error');

            }

        } catch (error) {

            console.error('Error checking API status:', error);

            this.updateStatusIndicator('openai-status', false, 'Ошибка проверки');

            this.updateStatusIndicator('yandex-status', false, 'Ошибка проверки');

        }

    }



    updateStatusIndicator(elementId, isConnected, errorMessage = null) {

        const element = document.getElementById(elementId);

        if (!element) return;

        

        const circle = element.querySelector('i, svg');

        

        if (isConnected) {

            element.classList.add('connected');

            element.title = 'Подключено';

            if (circle) {

                circle.style.color = '#27ae60'; // Green

            }

        } else {

            element.classList.remove('connected');

            const title = errorMessage || 'Не подключено';

            element.title = title;

            if (circle) {

                circle.style.color = '#e74c3c'; // Red

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



        this.addMessage('user', message);

        this.showLoading('Анализирую ваш запрос...');



        try {

            const response = await this.processQuery(message);

            this.addMessage('assistant', response.text, response.sources);

        } catch (error) {

            console.error('Error processing query:', error);

            this.addMessage('assistant', 'Извините, произошла ошибка при обработке запроса. Проверьте настройки API.', []);

        } finally {

            this.hideLoading();

            this.isProcessing = false;

        }



        this.scrollToBottom();

    }



    async processQuery(query) {

        try {

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

            if (!response.ok) {

                throw new Error(data.error || 'API Error');

            }



            return data;

        } catch (error) {

            throw error;

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

                const sourceLink = document.createElement('a');

                sourceLink.className = 'source-link';

                sourceLink.href = '#';

                sourceLink.textContent = source.name;

                sourceLink.title = source.path;

                sourcesDiv.appendChild(sourceLink);

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



    showLoading(message = 'Обрабатываю запрос...') {

        const overlay = document.getElementById('loading-overlay');

        const text = overlay.querySelector('p');

        text.textContent = message;

        overlay.classList.add('show');

    }



    hideLoading() {

        const overlay = document.getElementById('loading-overlay');

        overlay.classList.remove('show');

    }



    async loadChatHistory() {

        try {

            const response = await fetch('api/get_chats.php');

            const chats = await response.json();

            this.renderChatHistory(chats);

        } catch (error) {

            console.error('Error loading chat history:', error);

        }

    }



    renderChatHistory(chats) {

        const container = document.getElementById('chat-history');

        container.innerHTML = '';



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

            console.error('Error loading chat:', error);

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

                <div class="welcome-message">

                    <div class="welcome-icon">

                        <i class="fas fa-brain"></i>

                    </div>

                    <h3>Новый чат создан</h3>

                    <p>Задайте вопрос о товарах или ценах, и я найду релевантную информацию в ваших прайс-листах</p>

                    <div class="example-queries">

                        <h5>Примеры запросов:</h5>

                        <div class="example-item" data-query="Найди все предложения на iPhone 15">

                            "Найди все предложения на iPhone 15"

                        </div>

                        <div class="example-item" data-query="Сравни цены на ноутбук Lenovo ThinkPad">

                            "Сравни цены на ноутбук Lenovo ThinkPad"

                        </div>

                        <div class="example-item" data-query="Какие поставщики предлагают мониторы Samsung?">

                            "Какие поставщики предлагают мониторы Samsung?"

                        </div>

                    </div>

                </div>

            `;

            

            document.querySelectorAll('.example-item').forEach(item => {

                item.addEventListener('click', () => {

                    const query = item.getAttribute('data-query');

                    document.getElementById('message-input').value = query;

                    this.sendMessage();

                });

            });

            

            this.loadChatHistory();

        } catch (error) {

            console.error('Error creating new chat:', error);

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

            console.error('Error saving message:', error);

        }

    }



    openSettings() {

        const modal = new bootstrap.Modal(document.getElementById('settingsModal'));

        modal.show();

        

        // Добавляем кнопку автонастройки если её нет

        setTimeout(() => this.addAutoConfigButton(), 100);

    }



    loadSettings() {

        const settings = JSON.parse(localStorage.getItem('researcher_settings') || '{}');

        

        if (settings.openai_key) {

            document.getElementById('openai-key').value = settings.openai_key;

        }

        if (settings.yandex_token) {

            document.getElementById('yandex-token').value = settings.yandex_token;

        }

        if (settings.proxy_url) {

            document.getElementById('proxy-url').value = settings.proxy_url;

        }

        if (settings.yandex_folder) {

            document.getElementById('yandex-folder').value = settings.yandex_folder;

        }

    }



    async saveSettings() {

        const settings = {

            openai_key: document.getElementById('openai-key').value,

            yandex_token: document.getElementById('yandex-token').value,

            proxy_url: document.getElementById('proxy-url').value,

            yandex_folder: document.getElementById('yandex-folder').value

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

                throw new Error('Failed to save settings');

            }

        } catch (error) {

            console.error('Error saving settings:', error);

            this.showNotification('Ошибка при сохранении настроек', 'error');

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



// Initialize the application when DOM is loaded

document.addEventListener('DOMContentLoaded', () => {

    window.researcherAI = new ResearcherAI();

});



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




// Добавляем управление провайдерами

document.addEventListener('DOMContentLoaded', function() {

    const providerSelect = document.getElementById('ai-provider');

    const openaiGroup = document.getElementById('openai-key').parentNode;

    const deepseekGroup = document.getElementById('deepseek-key-group');

    const proxyGroup = document.getElementById('proxy-url').parentNode;

    

    if (providerSelect) {

        providerSelect.addEventListener('change', function() {

            const provider = this.value;

            

            if (provider === 'openai') {

                openaiGroup.style.display = 'block';

                if (deepseekGroup) deepseekGroup.style.display = 'none';

                proxyGroup.style.display = 'block';

                

                // Показываем индикатор OpenAI

                const openaiStatus = document.getElementById('openai-status');

                const deepseekStatus = document.getElementById('deepseek-status');

                if (openaiStatus) openaiStatus.style.display = 'flex';

                if (deepseekStatus) deepseekStatus.style.display = 'none';

                

            } else if (provider === 'deepseek') {

                openaiGroup.style.display = 'none';

                if (deepseekGroup) deepseekGroup.style.display = 'block';

                proxyGroup.style.display = 'none';

                

                // Показываем индикатор DeepSeek

                const openaiStatus = document.getElementById('openai-status');

                const deepseekStatus = document.getElementById('deepseek-status');

                if (openaiStatus) openaiStatus.style.display = 'none';

                if (deepseekStatus) deepseekStatus.style.display = 'flex';

            }

        });

        

        // Инициализируем отображение

        providerSelect.dispatchEvent(new Event('change'));

    }

});



// Обновляем функции загрузки и сохранения настроек

ResearcherAI.prototype.loadSettings = function() {

    const settings = JSON.parse(localStorage.getItem('researcher_settings') || '{}');

    

    if (settings.ai_provider) {

        const providerSelect = document.getElementById('ai-provider');

        if (providerSelect) {

            providerSelect.value = settings.ai_provider;

            providerSelect.dispatchEvent(new Event('change'));

        }

    }

    

    if (settings.openai_key) {

        document.getElementById('openai-key').value = settings.openai_key;

    }

    

    if (settings.deepseek_key) {

        const deepseekField = document.getElementById('deepseek-key');

        if (deepseekField) deepseekField.value = settings.deepseek_key;

    }

    

    if (settings.yandex_token) {

        document.getElementById('yandex-token').value = settings.yandex_token;

    }

    if (settings.proxy_url) {

        document.getElementById('proxy-url').value = settings.proxy_url;

    }

    if (settings.yandex_folder) {

        document.getElementById('yandex-folder').value = settings.yandex_folder;

    }

};



ResearcherAI.prototype.saveSettings = function() {

    const settings = {

        ai_provider: document.getElementById('ai-provider')?.value || 'openai',

        openai_key: document.getElementById('openai-key').value,

        deepseek_key: document.getElementById('deepseek-key')?.value || '',

        yandex_token: document.getElementById('yandex-token').value,

        proxy_url: document.getElementById('proxy-url').value,

        yandex_folder: document.getElementById('yandex-folder').value

    };



    return fetch('api/save_settings.php', {

        method: 'POST',

        headers: {

            'Content-Type': 'application/json',

        },

        body: JSON.stringify(settings)

    }).then(response => {

        if (response.ok) {

            localStorage.setItem('researcher_settings', JSON.stringify(settings));

            const modal = bootstrap.Modal.getInstance(document.getElementById('settingsModal'));

            modal.hide();

            

            this.checkApiStatus();

            this.showNotification('Настройки сохранены успешно', 'success');

        } else {

            throw new Error('Failed to save settings');

        }

    }).catch(error => {

        console.error('Error saving settings:', error);

        this.showNotification('Ошибка при сохранении настроек', 'error');

    });

};

