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

#### Modifica tabella messaggi (Fase 1 MVP - Approccio Semplificato)
```sql
ALTER TABLE phpbb_zpchat_messages
ADD COLUMN recipient_id INT UNSIGNED DEFAULT 0 COMMENT '0 = chat globale, >0 = chat privata',
ADD INDEX idx_recipient (recipient_id);
```

**Logica:**
- `recipient_id = 0` → Chat globale (broadcast a tutti)
- `recipient_id > 0` → Chat privata tra mittente e destinatario

**Filtro conversazione:**
Per una chat privata tra utente A e utente B, i messaggi vengono filtrati con:
```sql
WHERE (user_id = A AND recipient_id = B) OR (user_id = B AND recipient_id = A)
```

#### Configurazioni aggiuntive
```php
['config.add', ['zpchat_allow_private', 1]],  // Abilita chat private
['config.add', ['zpchat_allow_global', 1]],    // Abilita chat globale
['config.update', ['zpchat_version', '1.1.0']],
```

**Nota:** Per Fase 1 MVP non è stata implementata la tabella `zpchat_conversations`. Questo sarà valutato per Fase 2 (group chat).

---

### 2. Controller - Backend

**File:** `controller/main_controller.php`

**Nota:** Per Fase 1 MVP non è stato implementato l'endpoint `create_conversation`. Il frontend gestisce direttamente il passaggio a chat privata usando solo `recipient_id`.

#### 2.1 Nuovo metodo: Filtro conversazione
```php
protected function get_conversation_filter($recipient_id)
{
    if ($recipient_id == 0) {
        // Chat globale
        return 'recipient_id = 0';
    } else {
        // Chat privata: messaggi tra current_user e recipient
        $current_user = $this->user->data['user_id'];
        return '(user_id = ' . (int) $current_user . ' AND recipient_id = ' . (int) $recipient_id . ') OR ' .
               '(user_id = ' . (int) $recipient_id . ' AND recipient_id = ' . (int) $current_user . ')';
    }
}
```

#### 2.2 Modifica endpoint `messages()`
```php
public function messages()
{
    // ... controlli esistenti ...

    $recipient_id = $this->request->variable('recipient_id', 0);
    $last_id = $this->request->variable('last_id', 0);

    $conversation_filter = $this->get_conversation_filter($recipient_id);

    $sql = 'SELECT message_id, user_id, username, message, message_time, user_color, recipient_id
        FROM ' . $this->table_prefix . 'zpchat_messages
        WHERE ' . $conversation_filter;

    if ($last_id > 0) {
        $sql .= ' AND message_id > ' . (int) $last_id;
    }

    $sql .= ' ORDER BY message_id ASC LIMIT ' . ($max_messages + 10);

    // ... resto del codice ...
}
```

#### 2.3 Modifica endpoint `sse()`
Stessa logica di `messages()` con `recipient_id` e filtro conversazione.

#### 2.4 Modifica endpoint `send()`
```php
public function send()
{
    // ... controlli esistenti ...

    $message = $this->request->variable('message', '', true);
    $message = trim(strip_tags($message));
    $recipient_id = $this->request->variable('recipient_id', 0);

    // Verifica se chat privata è abilitata
    if ($recipient_id > 0 && empty($this->config['zpchat_allow_private'])) {
        return new JsonResponse(['success' => false, 'error' => 'Private chat disabled'], 403);
    }

    // Verifica se chat globale è abilitata
    if ($recipient_id == 0 && empty($this->config['zpchat_allow_global'])) {
        return new JsonResponse(['success' => false, 'error' => 'Global chat disabled'], 403);
    }

    $sql_ary = [
        'user_id'      => $this->user->data['user_id'],
        'username'     => $this->user->data['username'],
        'message'      => $message,
        'user_ip'      => $this->user->ip,
        'message_time' => time(),
        'user_color'   => $this->user->data['user_colour'] ?: '00aaee',
        'recipient_id' => (int) $recipient_id,
    ];

    // ... resto del codice ...
}
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
    $username = $event['user_poster_data']['username'];

    // Non mostrare link per se stessi
    if ($user_id == $this->user->data['user_id']) {
        return;
    }

    // Aggiungi link chat vicino all'avatar (solo data-attributes, no URL)
    $post_row['POSTER_AVATAR'] .= '<a href="#" class="zpchat-direct-link" data-recipient="' . $user_id . '" data-recipient-name="' . htmlspecialchars($username) . '" title="Chat privata con ' . htmlspecialchars($username) . '">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="#00aaee"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12z"/></svg>
    </a>';

    $event['post_row'] = $post_row;
}
```

**Nota:** Il link usa data-attributes invece di chiamare un endpoint. Il frontend gestisce direttamente l'avvio della chat privata.

---

### 4. Frontend JavaScript

**File:** `styles/all/template/zpchat.js`

#### 4.1 Nuove proprietà
```javascript
const ZPCHAT = {
    // ... proprietà esistenti ...
    recipientId: 0,          // 0 = globale, >0 = destinatario
    recipientName: '',       // Nome destinatario
    isGlobalChat: true,      // Flag chat corrente
    // ...
};
```

#### 4.2 Nuovo metodo: Avvia chat privata (diretto, senza fetch)
```javascript
startPrivateChat(recipientId, recipientName) {
    this.recipientId = recipientId;
    this.recipientName = recipientName;
    this.isGlobalChat = false;

    // Reset cache
    this.messageCache.clear();
    this.lastId = 0;
    this.messages.innerHTML = '';

    // Aggiorna UI
    this.updateChatHeader();

    // Riavvia polling con nuovo filtro
    if (this.pollTimer) {
        clearTimeout(this.pollTimer);
        this.pollTimer = null;
    }
    this.startPolling();

    // Apri chat se chiusa
    if (!this.isOpen) {
        this.toggleChat();
    }
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
```

#### 4.6 Nuovo metodo: Torna alla chat globale
```javascript
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
```

#### 4.7 Event listener per link avatar
```javascript
// In init()
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
```

#### 4.8 Event listener per pulsante switch globale
```javascript
// In bindEvents()
document.getElementById('zpchat-global-switch')?.addEventListener('click', (e) => {
    e.stopPropagation();
    this.switchToGlobalChat();
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

### Fase 1 - MVP (Chat Privata + Globale) ✅ COMPLETATO
1. ✅ Analisi fattibilità
2. ✅ Migration v1_1_0 con `recipient_id`
3. ✅ Modifiche controller per supportare `recipient_id`
4. ✅ Event listener per link avatar
5. ✅ Modifiche JS per gestire conversazione corrente
6. ✅ ACP opzioni configurazione
7. ⏳ Test completi (da eseguire dopo migration)

**Approccio implementato:**
- Solo campo `recipient_id` nella tabella messaggi (senza tabella `zpchat_conversations`)
- Frontend gestisce direttamente switch tra chat globale e privata
- Nessun endpoint `create_conversation` (gestito lato client)
- Filtro conversazione SQL: `(user_id = A AND recipient_id = B) OR (user_id = B AND recipient_id = A)`

### Fase 2 - Gestione Conversazioni Multiple (opzionale, da valutare)
1. Tabella `zpchat_conversations` per metadati conversazioni
2. UI sidebar con lista conversazioni
3. Notifiche per conversazioni multiple
4. Sistema stato utente (online/offline/occupato)
5. Cronologia conversazioni

### Fase 3 - Group Chat (3+ partecipanti, da valutare)
1. Tabella pivot `zpchat_conversation_participants`
2. UI per creare e gestire group chat
3. Sistema notifiche per group chat
4. Metadati conversazioni (nome, avatar, ultimo messaggio)

---

## Riepilogo Fase 1 MVP

**Versione:** 1.1.0
**Branch:** Inizio-P2Play
**Commit:** cf686d2

**File modificati:**
- `migrations/v1_1_0.php` - Nuova migration con recipient_id
- `controller/main_controller.php` - Filtro conversazione e verifica permessi
- `event/main_listener.php` - Link chat nell'avatar
- `styles/all/template/zpchat.js` - Gestione stato conversazione
- `styles/all/template/zpchat_body.html` - Titolo dinamico e pulsante switch
- `acp/main_module.php` - Opzioni ACP per chat privata/globale
- `language/it/info_acp_zpchat.php` - Stringhe di lingua
- `styles/all/theme/zpchat.css` - Styling link e switch
- `styles/all/template/event/overall_footer_after.html` - Bump versione v9

**Funzionalità implementate:**
- Chat privata 1-to-1 tramite icona nell'avatar
- Switch tra chat globale e privata
- Header dinamico con nome conversazione
- ACP opzioni per abilitare/disabilitare chat globale e privata
- CSS styling per nuovi elementi UI

**Passaggi successivi per test:**
1. Eseguire migration v1_1_0 dal pannello ACP
2. Abilitare "Permetti chat private" e "Permetti chat globale" nel pannello ACP
3. Testare link chat negli avatar
4. Testare switch tra chat globale e privata
5. Testare invio messaggi in chat privata

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
