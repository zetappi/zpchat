<?php
/**
 *
 * @package ZP Chat
 * @copyright (c) 2024 Marco Zannoni
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace marcozp\zpchat\acp;

class main_info
{
    function module()
    {
        return array(
            'filename'    => '\marcozp\zpchat\acp\main_module',
            'title'        => 'ACP_ZPCHAT_TITLE',
            'version'    => '1.0.0',
            'modes'        => array(
                'main'    => array(
                    'title'    => 'ACP_ZPCHAT_SETTINGS',
                    'auth'    => 'ext_marcozp/zpchat && acl_a_board',
                    'cat'    => array('ACP_CAT_DOT_MODS')
                ),
            ),
        );
    }

    function install()
    {
    }

    function uninstall()
    {
    }
}