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

class v1_1_0 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['zpchat_version']) && version_compare($this->config['zpchat_version'], '1.1.0', '>=');
    }

    public static function depends_on()
    {
        return ['\marcozp\zpchat\migrations\v1_0_0'];
    }

    public function update_data()
    {
        return [
            ['config.add', ['zpchat_allow_private', 1]],
            ['config.add', ['zpchat_allow_global', 1]],
            ['config.update', ['zpchat_version', '1.1.0']],
        ];
    }

    public function revert_data()
    {
        return [
            ['config.remove', ['zpchat_allow_private']],
            ['config.remove', ['zpchat_allow_global']],
            ['config.update', ['zpchat_version', '1.0.0']],
        ];
    }

    public function update_schema()
    {
        return [
            'add_columns' => [
                $this->table_prefix . 'zpchat_messages' => [
                    'recipient_id' => ['UINT', 0],
                ],
            ],
            'add_keys' => [
                $this->table_prefix . 'zpchat_messages' => [
                    'idx_recipient' => ['recipient_id'],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_columns' => [
                $this->table_prefix . 'zpchat_messages' => [
                    'recipient_id',
                ],
            ],
            'drop_keys' => [
                $this->table_prefix . 'zpchat_messages' => [
                    'idx_recipient',
                ],
            ],
        ];
    }
}
