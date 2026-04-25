# Direct Chat - Specifiche Tecniche

## Obiettivo
Trasformare l'estensione ZP Chat da chat globale (broadcast) a chat user-to-user (privata), mantenendo la possibilità di avere una chat globale come opzione.

---

## Architettura Attuale

### Tabella `phpbb_zpchat_messages`
| Colonna | Tipo | Descrizione |
|---------|------|-------------|
| `message_id` | UINT (PK) | ID univoco messaggio |
| `user_id` | UINT | ID mittente |
| `username` | VCHAR:255 | Username mittente |
| `message` | TEXT | Contenuto messaggio |
| `user_ip` | VCHAR:45 | IP mittente |
| `message_time` | TIMESTAMP | Timestamp invio |
| `user_color` | VCHAR:6 | Colore utente |

**Comportamento:** Tutti i messaggi sono visibili a tutti gli utenti (broadcast globale).

---

## Modifiche Richieste

### 1. Migration v1_1_0 - Database Schema

**File:** `migrations/v1_1_0.php`

#### Nuova tabella per conversazioni (opzionale per Fase 1, consigliata per Fase 2)
```sql
CREATE TABLE phpbb_zpchat_conversations (
    conversation_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id_1 INT UNSIGNED NOT NULL,
    user_id_2 INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_users (user_id_1, user_id_2),
    INDEX idx_user1 (user_id_1),
    INDEX idx_user2 (user_id_2)
);
```

#### Modifica tabella messaggi (Fase 1 MVP)
```sql
ALTER TABLE phpbb_zpchat_messages
ADD COLUMN recipient_id INT UNSIGNED DEFAULT 0 COMMENT '0 = chat globale, >0 = chat privata',
ADD COLUMN conversation_id INT UNSIGNED DEFAULT 0 COMMENT 'ID conversazione (0 = globale)',
ADD INDEX idx_recipient (recipient_id),
ADD INDEX idx_conversation (conversation_id);
```

**Logica:**
- `recipient_id = 0` → Chat globale (broadcast a tutti)
- `recipient_id > 0` → Chat privata tra mittente e destinatario
- `conversation_id = 0` → Chat globale
- `conversation_id > 0` → Chat privata con ID specifico

#### Configurazioni aggiuntive
```php
['config.add', ['zpchat_allow_private', 1]],  // Abilita chat private
['config.add', ['zpchat_allow_global', 1]],    // Abilita chat globale
['config.add', ['zpchat_version', '1.1.0']],
```

---

### 2. Controller - Backend

**File:** `controller/main_controller.php`

#### 2.1 Nuovo endpoint: Creazione conversazione
```php
public function create_conversation($recipient_id)
{
    if (empty($this->config['zpchat_enabled']) || empty($this->config['zpchat_allow_private'])) {
        return new JsonResponse(['success' => false, 'error' => 'Forbidden'], 403);
    }

    if ($this->user->data['user_id'] == ANONYMOUS) {
        return new JsonResponse(['success' => false, 'error' => 'Forbidden'], 403);
    }

    $recipient_id = (int) $recipient_id;
    if ($recipient_id <= 0 || $recipient_id == $this->user->data['user_id']) {
        return new JsonResponse(['success' => false, 'error' => 'Invalid recipient'], 400);
    }

    // Verifica esistenza conversazione
    $user1 = min($this->user->data['user_id'], $recipient_id);
    $user2 = max($this->user->data['user_id'], $recipient_id);

    $sql = 'SELECT conversation_id FROM ' . $this->table_prefix . 'zpchat_conversations
        WHERE user_id_1 = ' . $user1 . ' AND user_id_2 = ' . $user2;
    $result = $this->db->sql_query($sql);
    $row = $this->db->sql_fetchrow($result);
    $this->db->sql_freeresult($result);

    if ($row) {
        return new JsonResponse(['success' => true, 'conversation_id' => (int) $row['conversation_id']]);
    }

    // Crea nuova conversazione
    $sql_ary = [
        'user_id_1' => $user1,
        'user_id_2' => $user2,
        'created_at' => time(),
    ];
    $sql = 'INSERT INTO ' . $this->table_prefix . 'zpchat_conversations ' . $this->db->sql_build_array('INSERT', $sql_ary);
    $this->db->sql_query($sql);

    $conversation_id = (int) $this->db->sql_nextid();

    return new JsonResponse(['success' => true, 'conversation_id' => $conversation_id]);
}
```

#### 2.2 Modifica endpoint `messages()`
```php
public function messages()
{
    // ... controlli esistenti ...

    $conversation_id = $this->request->variable('conversation_id', 0);
    $recipient_id = $this->request->variable('recipient_id', 0);
    $last_id = $this->request->variable('last_id', 0);

    $where_conditions = [];
    
    if ($conversation_id > 0) {
        // Chat privata specifica
        $where_conditions[] = 'conversation_id = ' . (int) $conversation_id;
    } elseif ($recipient_id > 0) {
        // Chat privata senza conversation_id (fallback Fase 1)
        $where_conditions[] = '(recipient_id = 0 OR recipient_id = ' . (int) $recipient_id . ' OR user_id = ' . (int) $recipient_id . ')';
        $where_conditions[] = '(user_id = ' . (int) $this->user->data['user_id'] . ' OR recipient_id = ' . (int) $this->user->data['user_id'] . ')';
    } else {
        // Chat globale
        $where_conditions[] = 'recipient_id = 0';
        $where_conditions[] = 'conversation_id = 0';
    }

    $sql = 'SELECT message_id, user_id, username, message, message_time, user_color, recipient_id, conversation_id
        FROM ' . $this->table_prefix . 'zpchat_messages';
    
    if (!empty($where_conditions)) {
        $sql .= ' WHERE ' . implode(' AND ', $where_conditions);
    }

    if ($last_id > 0) {
        $sql .= (empty($where_conditions) ? ' WHERE ' : ' AND ') . 'message_id > ' . (int) $last_id;
    }

    $sql .= ' ORDER BY message_id ASC LIMIT ' . ($max_messages + 10);

    // ... resto del codice ...
}
```

#### 2.3 Modifica endpoint `sse()`
Stessa logica di `messages()` con `WHERE conditions` per filtrare per conversazione.

#### 2.4 Modifica endpoint `send()`
```php
public function send()
{
    // ... controlli esistenti ...

    $conversation_id = $this->request->variable('conversation_id', 0);
    $recipient_id = $this->request->variable('recipient_id', 0);

    // Validazione: se chat privata, deve avere recipient_id o conversation_id
    if ($recipient_id > 0 || $conversation_id > 0) {
        if (empty($this->config['zpchat_allow_private'])) {
            return new JsonResponse(['success' => false, 'error' => 'Private chat disabled'], 403);
        }
    }

    $sql_ary = [
        'user_id'        => $this->user->data['user_id'],
        'username'       => $this->user->data['username'],
        'message'        => $message,
        'user_ip'        => $this->user->ip,
        'message_time'   => time(),
        'user_color'     => $this->user->data['user_colour'] ?: '00aaee',
        'recipient_id'   => (int) $recipient_id,
        'conversation_id' => (int) $conversation_id,
    ];

    // ... resto del codice ...
}
```

#### 2.5 Nuova rotta
**File:** `config/routing.yml`
```yaml
marcozp_zpchat_create_conversation:
    path: /zpchat/create/{recipient_id}
    defaults:
        _controller: marcozp.zpchat.controller.main:handle
        action: create_conversation
    requirements:
        recipient_id: \d+
```

---

### 3. Event Listener - Link nell'Avatar

**File:** `event/main_listener.php`

#### 3.1 Aggiungere evento
```php
public static function getSubscribedEvents()
{
    return [
        'core.user_setup'              => 'on_user_setup',
        'core.page_footer'             => 'on_page_footer',
        'core.viewtopic_modify_post_row' => 'on_viewtopic_modify_post_row',
    ];
}
```

#### 3.2 Nuovo metodo
```php
public function on_viewtopic_modify_post_row($event)
{
    if (empty($this->config['zpchat_enabled']) || empty($this->config['zpchat_allow_private'])) {
        return;
    }

    if ($this->user->data['user_id'] == ANONYMOUS) {
        return;
    }

    $post_row = $event['post_row'];
    $user_id = $event['user_poster_data']['user_id'];

    // Non mostrare link per se stessi
    if ($user_id == $this->user->data['user_id']) {
        return;
    }

    $chat_url = $this->helper->route('marcozp_zpchat_create_conversation', ['recipient_id' => $user_id]);

    // Aggiungi link chat vicino all'avatar
    $post_row['POSTER_AVATAR'] .= '<a href="#" class="zpchat-direct-link" data-recipient="' . $user_id . '" data-url="' . htmlspecialchars($chat_url) . '" title="Chat privata">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="#00aaee"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12z"/></svg>
    </a>';

    $event['post_row'] = $post_row;
}
```

#### 3.3 Nuova variabile template
Nel metodo `assign_chat_vars()` aggiungere:
```php
'ZPCHAT_URL_CREATE_CONVERSATION' => $this->helper->route('marcozp_zpchat_create_conversation', ['recipient_id' => 0]),
```

---

### 4. Frontend JavaScript

**File:** `styles/all/template/zpchat.js`

#### 4.1 Nuove proprietà
```javascript
const ZPCHAT = {
    // ... proprietà esistenti ...
    conversationId: 0,        // 0 = globale, >0 = privata
    recipientId: 0,          // 0 = globale, >0 = destinatario
    recipientName: '',       // Nome destinatario
    isGlobalChat: true,      // Flag chat corrente
    // ...
};
```

#### 4.2 Nuovo metodo: Avvia chat privata
```javascript
startPrivateChat(recipientId, recipientName) {
    // Crea o ottieni conversazione
    fetch(this.urlCreateConversation.replace('{recipient_id}', recipientId), {
        credentials: 'same-origin',
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            this.conversationId = data.conversation_id;
            this.recipientId = recipientId;
            this.recipientName = recipientName;
            this.isGlobalChat = false;
            
            // Reset cache
            this.messageCache.clear();
            this.lastId = 0;
            this.messages.innerHTML = '';
            
            // Aggiorna UI
            this.updateChatHeader();
            this.startPolling();
            
            // Apri chat se chiusa
            if (!this.isOpen) {
                this.toggleChat();
            }
        }
    });
},
```

#### 4.3 Modifica metodo `sendMessage()`
```javascript
async sendMessage() {
    const message = this.input?.value?.trim();
    if (!message) return;

    this.input.value = '';
    this.input.disabled = true;

    try {
        const formData = new FormData();
        formData.append('message', message);
        
        if (!this.isGlobalChat) {
            formData.append('conversation_id', this.conversationId);
            formData.append('recipient_id', this.recipientId);
        }

        const response = await fetch(this.urlSend, {
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
```

#### 4.4 Modifica metodo `startPolling()`
```javascript
startPolling() {
    if (this.pollTimer) return;
    const poll = async () => {
        let delay = this.pollBaseInterval;
        try {
            let url = `${this.urlMessages}?last_id=${this.lastId}`;
            
            if (!this.isGlobalChat) {
                url += `&conversation_id=${this.conversationId}`;
                url += `&recipient_id=${this.recipientId}`;
            }
            
            const response = await fetch(url, { credentials: 'same-origin' });
            // ... resto del codice ...
        }
        // ...
    };
    poll();
},
```

#### 4.5 Nuovo metodo: Aggiorna header chat
```javascript
updateChatHeader() {
    const header = this.container?.querySelector('.zpchat-header');
    if (header) {
        const title = header.querySelector('.zpchat-title');
        if (title) {
            if (this.isGlobalChat) {
                title.textContent = 'Chat Globale';
            } else {
                title.textContent = `Chat con ${this.recipientName}`;
            }
        }
    }
},
```

#### 4.6 Nuovo metodo: Torna alla chat globale
```javascript
switchToGlobalChat() {
    this.isGlobalChat = true;
    this.conversationId = 0;
    this.recipientId = 0;
    this.recipientName = '';
    
    this.messageCache.clear();
    this.lastId = 0;
    this.messages.innerHTML = '';
    
    this.updateChatHeader();
    this.startPolling();
},
```

#### 4.7 Event listener per link avatar
```javascript
// In init() o bindEvents()
document.addEventListener('click', (e) => {
    const link = e.target.closest('.zpchat-direct-link');
    if (link) {
        e.preventDefault();
        const recipientId = parseInt(link.dataset.recipient, 10);
        const recipientName = link.dataset.recipientName || 'Utente';
        this.startPrivateChat(recipientId, recipientName);
    }
});
```

#### 4.8 Modifica template HTML
**File:** `styles/all/template/zpchat_body.html`

Aggiungere pulsante per tornare alla chat globale e titolo dinamico:
```html
<div class="zpchat-header">
    <span class="zpchat-title">Chat Globale</span>
    <button class="zpchat-close" id="zpchat-close">&times;</button>
    <button class="zpchat-global-switch" id="zpchat-global-switch" style="display:none;">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="#fff"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
        Globale
    </button>
</div>
```

---

### 5. ACP - Configurazioni

**File:** `acp/main_module.php`

Aggiungere nuove opzioni nel pannello di configurazione:
```php
function display_options()
{
    // ... opzioni esistenti ...
    
    $display_vars = [
        'legend1' => 'ACP_ZPCHAT_SETTINGS',
        'zpchat_enabled' => ['lang' => 'ACP_ZPCHAT_ENABLED', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => true],
        'zpchat_allow_global' => ['lang' => 'ACP_ZPCHAT_ALLOW_GLOBAL', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => true],
        'zpchat_allow_private' => ['lang' => 'ACP_ZPCHAT_ALLOW_PRIVATE', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => true],
        // ... resto delle opzioni ...
    ];
}
```

**File:** `language/it/info_acp_zpchat.php`
```php
'ACP_ZPCHAT_ALLOW_GLOBAL' => 'Permetti chat globale',
'ACP_ZPCHAT_ALLOW_GLOBAL_EXPLAIN' => 'Se abilitato, gli utenti possono partecipare alla chat globale visibile a tutti.',
'ACP_ZPCHAT_ALLOW_PRIVATE' => 'Permetti chat private',
'ACP_ZPCHAT_ALLOW_PRIVATE_EXPLAIN' => 'Se abilitato, gli utenti possono avviare chat private cliccando sull\'icona chat nell\'avatar degli altri utenti.',
```

---

### 6. CSS - Stili aggiuntivi

**File:** `styles/all/theme/zpchat.css`

```css
/* Link chat nell'avatar */
.zpchat-direct-link {
    display: inline-block;
    margin-left: 5px;
    padding: 4px;
    border-radius: 4px;
    transition: background-color 0.2s;
}

.zpchat-direct-link:hover {
    background-color: rgba(0, 170, 238, 0.1);
}

.zpchat-direct-link svg {
    vertical-align: middle;
}

/* Pulsante switch chat globale */
.zpchat-global-switch {
    background: none;
    border: none;
    color: white;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.zpchat-global-switch:hover {
    background-color: rgba(255, 255, 255, 0.2);
}

.zpchat-title {
    flex-grow: 1;
    font-weight: bold;
}
```

---

## Piano di Implementazione

### Fase 1 - MVP (Chat Privata + Globale)
1. ✅ Analisi fattibilità
2. ⏳ Migration v1_1_0 con `recipient_id`
3. ⏳ Modifiche controller per supportare `recipient_id`
4. ⏳ Event listener per link avatar
5. ⏳ Modifiche JS per gestire conversazione corrente
6. ⏳ ACP opzioni configurazione
7. ⏳ Test completi

### Fase 2 - Gestione Conversazioni Multiple (opzionale)
1. Tabella `zpchat_conversations`
2. UI sidebar con lista conversazioni
3. Notifiche per conversazioni multiple
4. Cronologia conversazioni

---

## Note di Sicurezza

- **CSRF:** Gli endpoint esistenti sono già protetti tramite cookie di sessione phpBB
- **Autorizzazione:** Verificare che l'utente possa accedere solo alle conversazioni in cui è coinvolto
- **Privacy:** Le chat private devono essere accessibili solo ai partecipanti
- **Rate limiting:** Considerare limitare la creazione di conversazioni per prevenire spam

---

## Compatibilità

- phpBB >= 3.3.0
- PHP >= 7.2
- Migration backward compatible: `recipient_id` default 0 mantiene chat globale funzionante
