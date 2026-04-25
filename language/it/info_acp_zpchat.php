<?php
if (!defined('IN_PHPBB')) { exit; }

if (empty($lang) || !is_array($lang))
{
    $lang = array();
}

$lang = array_merge($lang, array(
    'ACP_ZPCHAT_TITLE' => 'ZP Chat',
    'ACP_ZPCHAT_SETTINGS' => 'Impostazioni',
    'ACP_ZPCHAT_SETTINGS_TITLE' => 'Impostazioni ZP Chat',
    'ACP_ZPCHAT_SETTINGS_TITLE_EXPLAIN' => 'Configura la chat in tempo reale.',

    'ZPCHAT_ENABLED' => 'Chat abilitata',
    'ZPCHAT_ENABLED_EXPLAIN' => 'Abilita o disabilita la chat in tempo reale.',

    'ZPCHAT_EXPIRY_SECONDS' => 'Scadenza messaggi (secondi)',
    'ZPCHAT_EXPIRY_SECONDS_EXPLAIN' => 'Tempo dopo il quale i messaggi vengono automaticamente rimossi (default: 60 secondi).',

    'ZPCHAT_REFRESH_INTERVAL' => 'Intervallo refresh (ms)',
    'ZPCHAT_REFRESH_INTERVAL_EXPLAIN' => 'Intervallo di aggiornamento della chat in millisecondi.',

    'ZPCHAT_MAX_MESSAGES' => 'Numero massimo messaggi',
    'ZPCHAT_MAX_MESSAGES_EXPLAIN' => 'Numero massimo di messaggi da visualizzare nella chat.',

    'ACP_ZPCHAT_APPEARANCE' => 'Aspetto',

    'ZPCHAT_BG_COLOR' => 'Colore sfondo chat',
    'ZPCHAT_BG_COLOR_EXPLAIN' => 'Colore esadecimale dello sfondo area messaggi (es: f5f5f5).',
    'ZPCHAT_BG_OWN' => 'Colore sfondo propri messaggi',
    'ZPCHAT_BG_OWN_EXPLAIN' => 'Colore esadecimale dello sfondo dei propri messaggi (es: e3f2fd).',
    'ZPCHAT_BG_OTHER' => 'Colore sfondo messaggi altrui',
    'ZPCHAT_BG_OTHER_EXPLAIN' => 'Colore esadecimale dello sfondo dei messaggi altrui (es: ffffff).',
    'ZPCHAT_HEADER_COLOR' => 'Colore testata chat',
    'ZPCHAT_HEADER_COLOR_EXPLAIN' => 'Colore esadecimale della barra superiore della chat (es: 00aaee).',
    'ZPCHAT_BUTTON_COLOR' => 'Colore pulsante chat',
    'ZPCHAT_BUTTON_COLOR_EXPLAIN' => 'Colore esadecimale del pulsante apri/invia chat (es: 00aaee).',
    'ZPCHAT_WIDTH' => 'Larghezza finestra chat (px)',
    'ZPCHAT_WIDTH_EXPLAIN' => 'Larghezza della finestra chat in pixel (200-800).',
    'ZPCHAT_HEIGHT' => 'Altezza finestra chat (px)',
    'ZPCHAT_HEIGHT_EXPLAIN' => 'Altezza della finestra chat in pixel (200-800).',

    'ACP_ZPCHAT_SETTINGS_SAVED' => 'Impostazioni salvate correttamente.',
    'ACP_ZPCHAT_HISTORY_CLEARED' => 'Storico messaggi eliminato.',

    'ACP_ZPCHAT_TOTAL_MESSAGES' => 'Messaggi nel database',
    'ACP_ZPCHAT_CLEAR_HISTORY' => 'Cancella storico',
    'ACP_ZPCHAT_CLEAR_HISTORY_CONFIRM' => 'Sei sicuro di voler eliminare tutti i messaggi?',
    'ACP_ZPCHAT_EXPORT_CSV' => 'Esporta CSV',
    'ACP_ZPCHAT_MANAGE' => 'Gestione',

    'LOGIN_TO_CHAT' => 'Effettua il login per utilizzare la chat.',
));