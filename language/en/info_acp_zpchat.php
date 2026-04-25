<?php
if (!defined('IN_PHPBB')) { exit; }

if (empty($lang) || !is_array($lang))
{
    $lang = array();
}

$lang = array_merge($lang, array(
    'ACP_ZPCHAT_TITLE' => 'ZP Chat',
    'ACP_ZPCHAT_SETTINGS' => 'Settings',
    'ACP_ZPCHAT_SETTINGS_TITLE' => 'ZP Chat Settings',
    'ACP_ZPCHAT_SETTINGS_TITLE_EXPLAIN' => 'Configure the real-time chat.',

    'ZPCHAT_ENABLED' => 'Chat enabled',
    'ZPCHAT_ENABLED_EXPLAIN' => 'Enable or disable the real-time chat.',

    'ZPCHAT_EXPIRY_SECONDS' => 'Message expiry (seconds)',
    'ZPCHAT_EXPIRY_SECONDS_EXPLAIN' => 'Time after which messages are automatically removed (default: 60 seconds).',

    'ZPCHAT_REFRESH_INTERVAL' => 'Refresh interval (ms)',
    'ZPCHAT_REFRESH_INTERVAL_EXPLAIN' => 'Chat refresh interval in milliseconds.',

    'ZPCHAT_MAX_MESSAGES' => 'Maximum messages',
    'ZPCHAT_MAX_MESSAGES_EXPLAIN' => 'Maximum number of messages to display in the chat.',

    'ACP_ZPCHAT_APPEARANCE' => 'Appearance',

    'ZPCHAT_BG_COLOR' => 'Chat background color',
    'ZPCHAT_BG_COLOR_EXPLAIN' => 'Hex color of the messages area background (e.g. f5f5f5).',
    'ZPCHAT_BG_OWN' => 'Own messages background',
    'ZPCHAT_BG_OWN_EXPLAIN' => 'Hex color for your own messages background (e.g. e3f2fd).',
    'ZPCHAT_BG_OTHER' => 'Others messages background',
    'ZPCHAT_BG_OTHER_EXPLAIN' => 'Hex color for other users messages background (e.g. ffffff).',
    'ZPCHAT_HEADER_COLOR' => 'Chat header color',
    'ZPCHAT_HEADER_COLOR_EXPLAIN' => 'Hex color for the chat top bar (e.g. 00aaee).',
    'ZPCHAT_BUTTON_COLOR' => 'Chat button color',
    'ZPCHAT_BUTTON_COLOR_EXPLAIN' => 'Hex color for the open/send chat button (e.g. 00aaee).',
    'ZPCHAT_WIDTH' => 'Chat window width (px)',
    'ZPCHAT_WIDTH_EXPLAIN' => 'Chat window width in pixels (200-800).',
    'ZPCHAT_HEIGHT' => 'Chat window height (px)',
    'ZPCHAT_HEIGHT_EXPLAIN' => 'Chat window height in pixels (200-800).',

    'ACP_ZPCHAT_SETTINGS_SAVED' => 'Settings saved successfully.',
    'ACP_ZPCHAT_HISTORY_CLEARED' => 'Message history cleared.',

    'ACP_ZPCHAT_TOTAL_MESSAGES' => 'Messages in database',
    'ACP_ZPCHAT_CLEAR_HISTORY' => 'Clear history',
    'ACP_ZPCHAT_CLEAR_HISTORY_CONFIRM' => 'Are you sure you want to delete all messages?',
    'ACP_ZPCHAT_EXPORT_CSV' => 'Export CSV',
    'ACP_ZPCHAT_MANAGE' => 'Manage',

    'LOGIN_TO_CHAT' => 'Log in to use the chat.',
));