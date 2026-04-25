(function () {
    'use strict';

    const ZPCHAT = {
        container: null,
        toggle: null,
        messages: null,
        form: null,
        input: null,
        status: null,
        lastId: 0,
        expirySeconds: 60,
        refreshInterval: 2000,
        username: '',
        userColor: '000000',
        isOpen: false,
        messageCache: new Map(),
        timeouts: new Map(),
        urlSend: '',
        urlMessages: '',
        bgColor: 'f5f5f5',
        bgOwn: 'e3f2fd',
        bgOther: 'ffffff',
        headerColor: '00aaee',
        buttonColor: '00aaee',
        chatWidth: 320,
        chatHeight: 400,
        pollTimer: null,
        pollErrors: 0,
        maxPollErrors: 10,
        pollBaseInterval: 2000,
        unreadCount: 0,
        unreadIds: new Set(),
        isMuted: false,
        unmuteBtn: null,
        recipientId: 0,
        recipientName: '',
        isGlobalChat: true,
        currentUserId: 0,

        init() {
            const container = document.getElementById('zpchat-container');
            if (!container) {
                return;
            }

            this.container = container;
            this.toggle = document.getElementById('zpchat-toggle');
            this.messages = document.getElementById('zpchat-messages');
            this.form = document.getElementById('zpchat-form');
            this.input = document.getElementById('zpchat-input');
            this.status = document.getElementById('zpchat-status');

            this.refreshInterval = parseInt(container.dataset.refresh, 10) || 2000;
            this.currentUserId  = parseInt(container.dataset.userId || '0', 10) || 0;
            this.username      = container.dataset.username || '';
            this.userColor     = container.dataset.userColor || '000000';
            this.urlSend       = container.dataset.urlSend || '';
            this.urlMessages   = container.dataset.urlMessages || '';
            this.bgColor       = container.dataset.bgColor || 'f5f5f5';
            this.bgOwn         = container.dataset.bgOwn || 'e3f2fd';
            this.bgOther       = container.dataset.bgOther || 'ffffff';
            this.headerColor   = container.dataset.headerColor || '00aaee';
            this.buttonColor   = container.dataset.buttonColor || '00aaee';
            this.chatWidth     = parseInt(container.dataset.width, 10) || 320;
            this.chatHeight    = parseInt(container.dataset.height, 10) || 400;
            this.pollBaseInterval = this.refreshInterval;

            this.isMuted = localStorage.getItem('zpchat_muted') === '1';

            this.applyStyles();
            this.createToggle();
            this.createUnmuteBtn();
            this.insertChatLinks();
            this.bindDirectChatLinks();
            this.bindEvents();

            if (this.isMuted) {
                this.applyMute();
            } else {
                this.startPolling();
            }
        },

        applyStyles() {
            this.container.style.width = this.chatWidth + 'px';
            this.container.style.height = this.chatHeight + 'px';
            if (this.messages) {
                this.messages.style.backgroundColor = '#' + this.bgColor;
            }
            const header = this.container.querySelector('.zpchat-header');
            if (header) {
                header.style.backgroundColor = '#' + this.headerColor;
            }
            const submitBtn = this.container.querySelector('.zpchat-submit');
            if (submitBtn) {
                submitBtn.style.backgroundColor = '#' + this.buttonColor;
            }
        },

        createToggle() {
            if (this.toggle) {
                this.toggle.classList.remove('zpchat-hidden');
            } else {
                const btn = document.createElement('button');
                btn.id = 'zpchat-toggle';
                btn.className = 'zpchat-toggle';
                btn.innerHTML = `<svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12z"/></svg>`;
                btn.style.backgroundColor = '#' + this.buttonColor;
                btn.addEventListener('click', () => this.toggleChat());
                document.body.appendChild(btn);
                this.toggle = btn;
            }
            if (!this.badge) {
                const badge = document.createElement('span');
                badge.id = 'zpchat-badge';
                badge.className = 'zpchat-badge';
                badge.style.display = 'none';
                this.toggle.appendChild(badge);
                this.badge = badge;
            }
        },

        bindEvents() {
            this.form?.addEventListener('submit', (e) => {
                e.preventDefault();
                this.sendMessage();
            });

            this.container?.querySelector('.zpchat-header')?.addEventListener('click', (e) => {
                if (e.target.closest('.zpchat-close')) return;
                this.toggleChat();
            });

            this.input?.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });

            document.getElementById('zpchat-close')?.addEventListener('click', (e) => {
                e.stopPropagation();
                this.isOpen = false;
                this.container?.classList.remove('zpchat-open');
                this.container?.classList.add('zpchat-minimized');
                this.toggle?.classList.remove('zpchat-hidden');
                this.clearNotification();
            });

            document.getElementById('zpchat-mute')?.addEventListener('click', (e) => {
                e.stopPropagation();
                this.muteChat();
            });

            document.getElementById('zpchat-global-switch')?.addEventListener('click', (e) => {
                e.stopPropagation();
                this.switchToGlobalChat();
            });
        },

        toggleChat() {
            this.isOpen = !this.isOpen;
            this.container?.classList.toggle('zpchat-open', this.isOpen);
            this.container?.classList.toggle('zpchat-minimized', !this.isOpen);
            this.toggle?.classList.toggle('zpchat-hidden', this.isOpen);

            if (this.isOpen) {
                this.clearNotification();
                this.messages?.scrollTo({ top: this.messages.scrollHeight, behavior: 'smooth' });
            }
        },

        startPolling() {
            if (this.pollTimer) return;
            const poll = async () => {
                let delay = this.pollBaseInterval;
                try {
                    let url = `${this.urlMessages}?last_id=${this.lastId}`;
                    if (!this.isGlobalChat) {
                        url += `&recipient_id=${this.recipientId}`;
                    }
                    const response = await fetch(url, { credentials: 'same-origin' });
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    const data = await response.json();
                    if (data.messages) {
                        this.lastId = data.last_id;
                        this.expirySeconds = data.expiry;
                        this.processMessages(data.messages);
                    }
                    this.pollErrors = 0;
                    this.setConnected(true);
                } catch (e) {
                    this.pollErrors++;
                    delay = Math.min(this.pollBaseInterval * Math.pow(2, this.pollErrors), 30000);
                    if (this.pollErrors >= this.maxPollErrors) {
                        console.error('ZPChat: Too many errors, stopping.');
                        this.setConnected(false);
                        this.pollTimer = null;
                        return;
                    }
                }
                this.pollTimer = setTimeout(poll, delay);
            };
            poll();
        },

        setConnected(connected) {
            this.status?.classList.toggle('disconnected', !connected);
            if (this.status) {
                this.status.textContent = connected ? '' : 'Disconnesso';
            }
        },

        async sendMessage() {
            const message = this.input?.value?.trim();
            if (!message) return;

            this.input.value = '';
            this.input.disabled = true;

            try {
                const url = this.urlSend;
                const formData = new FormData();
                formData.append('message', message);
                if (!this.isGlobalChat) {
                    formData.append('recipient_id', this.recipientId);
                }

                const response = await fetch(url, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                });

                const data = await response.json();

                if (!data.success) {
                    console.error('ZPChat: Send failed', data.error);
                }
            } catch (e) {
                console.error('ZPChat: Send error', e);
            } finally {
                this.input.disabled = false;
                this.input.focus();
            }
        },

        processMessages(newMessages) {
            if (!newMessages?.length) return;

            let newOtherMessages = 0;
            newMessages.forEach((msg) => {
                if (this.messageCache.has(msg.message_id)) return;

                this.messageCache.set(msg.message_id, msg);
                this.renderMessage(msg);

                if (msg.username !== this.username) {
                    newOtherMessages++;
                    this.unreadIds.add(msg.message_id);
                }
            });

            if (newOtherMessages > 0 && !this.isOpen) {
                this.unreadCount += newOtherMessages;
                this.showNotification();
            }

            this.updateToggleActivity();
            this.scheduleExpiry();
            this.scrollToBottom();
        },

        updateToggleActivity() {
            this.toggle?.classList.toggle('zpchat-has-messages', this.messageCache.size > 0);
        },

        renderMessage(msg) {
            const isOwn = msg.username === this.username;
            const color = msg.user_color || '888888';
            const textColor = this.getContrastColor(color);

            const div = document.createElement('div');
            div.className = `zpchat-message zpchat-${isOwn ? 'own' : 'other'}`;
            div.dataset.id = msg.message_id;

            const time = new Date(msg.message_time * 1000).toLocaleTimeString('it-IT', {
                hour: '2-digit',
                minute: '2-digit',
            });

            const bgMsg = isOwn ? this.bgOwn : this.bgOther;
            const nickHtml = `<span class="zpchat-nick" style="background-color: #${color}; color: ${textColor}">${this.escapeHtml(msg.username)}</span>`;
            const borderSide = isOwn ? 'border-right' : 'border-left';
            div.innerHTML = `
                <div class="zpchat-message-inner" style="${borderSide}: 3px solid #${color}; background-color: #${bgMsg}">
                    ${isOwn ? '' : nickHtml}
                    <span class="zpchat-text">${this.escapeHtml(msg.message)}</span>
                    ${isOwn ? '<span class="zpchat-nick-right">' + nickHtml + '</span>' : ''}
                    <span class="zpchat-time">${time}</span>
                </div>
            `;

            this.messages?.appendChild(div);
        },

        scheduleExpiry() {
            this.timeouts.forEach((timeout) => clearTimeout(timeout));
            this.timeouts.clear();

            const messages = this.messages?.querySelectorAll('.zpchat-message');
            if (!messages?.length) return;

            messages.forEach((msgEl) => {
                const id = parseInt(msgEl.dataset.id, 10);
                const msg = this.messageCache.get(id);
                
                if (!msg) return;

                const createdAt = msg.message_time;
                const expiresAt = createdAt + this.expirySeconds;
                const remaining = Math.max(0, (expiresAt * 1000) - Date.now());

                if (remaining <= 0) {
                    msgEl.remove();
                    this.messageCache.delete(id);
                    this.timeouts.delete(id);
                    this.updateToggleActivity();
                    if (this.unreadIds.has(id)) {
                        this.unreadIds.delete(id);
                        this.unreadCount = Math.max(0, this.unreadCount - 1);
                        if (this.unreadCount === 0) {
                            this.clearNotification();
                        } else if (this.badge) {
                            this.badge.textContent = this.unreadCount > 99 ? '99+' : this.unreadCount;
                        }
                    }
                } else if (remaining <= this.expirySeconds * 1000) {
                    const timeout = setTimeout(() => {
                        msgEl.classList.add('fading');
                        setTimeout(() => {
                            msgEl.remove();
                            this.messageCache.delete(id);
                            this.timeouts.delete(id);
                            this.updateToggleActivity();
                            if (this.unreadIds.has(id)) {
                                this.unreadIds.delete(id);
                                this.unreadCount = Math.max(0, this.unreadCount - 1);
                                if (this.unreadCount === 0) {
                                    this.clearNotification();
                                } else if (this.badge) {
                                    this.badge.textContent = this.unreadCount > 99 ? '99+' : this.unreadCount;
                                }
                            }
                        }, 500);
                    }, remaining);

                    this.timeouts.set(id, timeout);
                }
            });
        },

        getContrastColor(hexColor) {
            const r = parseInt(hexColor.slice(0, 2), 16);
            const g = parseInt(hexColor.slice(2, 4), 16);
            const b = parseInt(hexColor.slice(4, 6), 16);
            const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
            return luminance > 0.5 ? '#000000' : '#ffffff';
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        showNotification() {
            if (this.toggle) {
                this.toggle.classList.add('zpchat-shake');
            }
            if (this.badge) {
                this.badge.textContent = this.unreadCount > 99 ? '99+' : this.unreadCount;
                this.badge.style.display = 'flex';
            }
        },

        createUnmuteBtn() {
            const btn = document.createElement('button');
            btn.className = 'zpchat-unmute';
            btn.title = 'Riattiva chat';
            btn.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" fill="#fff"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>';
            btn.style.display = 'none';
            btn.addEventListener('click', () => this.unmuteChat());
            document.body.appendChild(btn);
            this.unmuteBtn = btn;
        },

        muteChat() {
            this.isMuted = true;
            localStorage.setItem('zpchat_muted', '1');
            this.applyMute();
        },

        unmuteChat() {
            this.isMuted = false;
            localStorage.removeItem('zpchat_muted');
            this.unmuteBtn.style.display = 'none';
            this.toggle?.classList.remove('zpchat-hidden');
            this.startPolling();
        },

        applyMute() {
            this.isOpen = false;
            this.container?.classList.remove('zpchat-open');
            this.container?.classList.add('zpchat-minimized');
            this.toggle?.classList.add('zpchat-hidden');
            this.clearNotification();
            if (this.pollTimer) {
                clearTimeout(this.pollTimer);
                this.pollTimer = null;
            }
            this.unmuteBtn.style.display = 'flex';
        },

        clearNotification() {
            this.unreadCount = 0;
            this.unreadIds.clear();
            if (this.toggle) {
                this.toggle.classList.remove('zpchat-shake');
            }
            if (this.badge) {
                this.badge.style.display = 'none';
                this.badge.textContent = '';
            }
        },

        scrollToBottom() {
            if (!this.isOpen) return;
            this.messages?.scrollTo({
                top: this.messages.scrollHeight,
                behavior: 'smooth'
            });
        },

        insertChatLinks() {
            // Cerca tutti i post e inserisce link chat dopo gli avatar
            const posts = document.querySelectorAll('.post');
            posts.forEach(post => {
                // Cerca il link al profilo dell'utente per ottenere l'ID
                const profileLink = post.querySelector('.postprofile a[href*="memberlist"]');
                if (!profileLink) return;

                // Estrae l'user_id dal link del profilo
                const userIdMatch = profileLink.href.match(/mode=viewprofile&u=(\d+)/);
                if (!userIdMatch) return;

                const userId = parseInt(userIdMatch[1], 10);
                if (userId === 0 || userId === this.currentUserId) return;

                // Cerca l'username
                const username = post.querySelector('.postprofile .username')?.textContent || post.querySelector('.postprofile strong')?.textContent;
                if (!username) return;

                // Cerca l'avatar
                const avatar = post.querySelector('.postprofile .avatar img, .postprofile .avatar');
                if (!avatar) return;

                // Verifica se il link è già stato inserito
                if (avatar.parentElement.querySelector('.zpchat-direct-link')) return;

                // Crea e inserisce il link chat
                const chatLink = document.createElement('a');
                chatLink.href = '#';
                chatLink.className = 'zpchat-direct-link';
                chatLink.dataset.recipient = userId;
                chatLink.dataset.recipientName = username;
                chatLink.title = 'Chat privata con ' + username;
                chatLink.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" fill="#00aaee"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12z"/></svg>';
                chatLink.style.marginLeft = '5px';
                avatar.parentElement.appendChild(chatLink);
            });
        },

        bindDirectChatLinks() {
            document.addEventListener('click', (e) => {
                const link = e.target.closest('.zpchat-direct-link');
                if (link) {
                    e.preventDefault();
                    const recipientId = parseInt(link.dataset.recipient, 10);
                    const recipientName = link.dataset.recipientName || 'Utente';
                    this.startPrivateChat(recipientId, recipientName);
                }
            });
        },

        startPrivateChat(recipientId, recipientName) {
            this.recipientId = recipientId;
            this.recipientName = recipientName;
            this.isGlobalChat = false;

            this.messageCache.clear();
            this.lastId = 0;
            this.messages.innerHTML = '';

            this.updateChatHeader();

            if (this.pollTimer) {
                clearTimeout(this.pollTimer);
                this.pollTimer = null;
            }
            this.startPolling();

            if (!this.isOpen) {
                this.toggleChat();
            }
        },

        switchToGlobalChat() {
            this.isGlobalChat = true;
            this.recipientId = 0;
            this.recipientName = '';
            
            this.messageCache.clear();
            this.lastId = 0;
            this.messages.innerHTML = '';
            
            this.updateChatHeader();
            
            if (this.pollTimer) {
                clearTimeout(this.pollTimer);
                this.pollTimer = null;
            }
            this.startPolling();
        },

        updateChatHeader() {
            const title = this.container?.querySelector('.zpchat-title');
            const globalSwitch = document.getElementById('zpchat-global-switch');

            if (title) {
                if (this.isGlobalChat) {
                    title.textContent = 'Chat Globale';
                } else {
                    title.textContent = `Chat con ${this.recipientName}`;
                }
            }

            if (globalSwitch) {
                globalSwitch.style.display = this.isGlobalChat ? 'none' : 'flex';
            }
        },
    };

    document.addEventListener('DOMContentLoaded', () => ZPCHAT.init());

}());