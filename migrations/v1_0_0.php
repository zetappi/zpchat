<?php
/**
 *
 * @package ZP Chat
 * @copyright (c) 2024 Marco Zannoni
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace marcozp\zpchat\migrations;

if (!defined('IN_PHPBB')) {
    exit;
}

class v1_0_0 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['zpchat_version']) && version_compare($this->config['zpchat_version'], '1.0.0', '>=');
    }

    public static function depends_on()
    {
        return ['\phpbb\db\migration\data\v33x\v339'];
    }

    public function update_data()
    {
        return [
            ['config.add', ['zpchat_enabled', 0]],
            ['config.add', ['zpchat_expiry_seconds', 60]],
            ['config.add', ['zpchat_refresh_interval', 2000]],
            ['config.add', ['zpchat_max_messages', 100]],
            ['config.add', ['zpchat_bg_color', 'f5f5f5']],
            ['config.add', ['zpchat_bg_own', 'e3f2fd']],
            ['config.add', ['zpchat_bg_other', 'ffffff']],
            ['config.add', ['zpchat_header_color', '00aaee']],
            ['config.add', ['zpchat_button_color', '00aaee']],
            ['config.add', ['zpchat_width', 320]],
            ['config.add', ['zpchat_height', 400]],
            ['config.add', ['zpchat_version', '1.0.0']],

            ['module.add', ['acp', 'ACP_CAT_DOT_MODS', 'ACP_ZPCHAT_TITLE']],
            ['module.add', [
                'acp',
                'ACP_ZPCHAT_TITLE',
                [
                    'module_basename' => '\\marcozp\\zpchat\\acp\\main_module',
                    'module_langname' => 'ACP_ZPCHAT_SETTINGS',
                    'module_mode' => 'main',
                    'module_auth' => 'ext_marcozp/zpchat && acl_a_board',
                ],
            ]],
        ];
    }

    public function revert_data()
    {
        return [
            ['config.remove', ['zpchat_enabled']],
            ['config.remove', ['zpchat_expiry_seconds']],
            ['config.remove', ['zpchat_refresh_interval']],
            ['config.remove', ['zpchat_max_messages']],
            ['config.remove', ['zpchat_bg_color']],
            ['config.remove', ['zpchat_bg_own']],
            ['config.remove', ['zpchat_bg_other']],
            ['config.remove', ['zpchat_header_color']],
            ['config.remove', ['zpchat_button_color']],
            ['config.remove', ['zpchat_width']],
            ['config.remove', ['zpchat_height']],
            ['config.remove', ['zpchat_version']],
        ];
    }

    public function update_schema()
    {
        return [
            'add_tables' => [
                $this->table_prefix . 'zpchat_messages' => [
                    'COLUMNS' => [
                        'message_id' => ['UINT', null, 'auto_increment'],
                        'user_id' => ['UINT', 0],
                        'username' => ['VCHAR:255', ''],
                        'message' => ['TEXT', ''],
                        'user_ip' => ['VCHAR:45', ''],
                        'message_time' => ['TIMESTAMP', 0],
                        'user_color' => ['VCHAR:6', '000000'],
                    ],
                    'PRIMARY_KEY' => 'message_id',
                    'KEYS' => [
                        'idx_message_time' => ['INDEX', 'message_time'],
                    ],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_tables' => [
                $this->table_prefix . 'zpchat_messages',
            ],
        ];
    }
}