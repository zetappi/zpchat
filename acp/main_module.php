<?php
/**
 *
 * @package ZP Chat
 * @copyright (c) 2024 Marco Zannoni
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace marcozp\zpchat\acp;

class main_module
{
    public $u_action;
    public $tpl_name;
    public $page_title;
    public $new_config = [];

    function main($id, $mode)
    {
        global $user, $template, $cache, $config, $request, $db, $phpbb_container;
        $table_prefix = $phpbb_container->getParameter('core.table_prefix');
        global $phpbb_root_path, $phpbb_admin_path, $phpEx;

        $user->add_lang('acp/common');
        $user->add_lang_ext('marcozp/zpchat', 'info_acp_zpchat');

        $this->tpl_name = 'acp_zpchat';
        $this->page_title = 'ACP_ZPCHAT_SETTINGS_TITLE';

        $form_key = 'acp_zpchat';
        add_form_key($form_key);

        $display_vars = array(
            'title'    => 'ACP_ZPCHAT_SETTINGS_TITLE',
            'vars'    => array(
                'legend1'                => 'ACP_ZPCHAT_SETTINGS',
                'zpchat_enabled'         => array('lang' => 'ZPCHAT_ENABLED', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => true),
                'zpchat_allow_global'    => array('lang' => 'ZPCHAT_ALLOW_GLOBAL', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => true),
                'zpchat_allow_private'   => array('lang' => 'ZPCHAT_ALLOW_PRIVATE', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => true),
                'zpchat_expiry_seconds'  => array('lang' => 'ZPCHAT_EXPIRY_SECONDS', 'validate' => 'int:10:3600', 'type' => 'text:5:10', 'explain' => true),
                'zpchat_refresh_interval' => array('lang' => 'ZPCHAT_REFRESH_INTERVAL', 'validate' => 'int:500:10000', 'type' => 'text:5:10', 'explain' => true),
                'zpchat_max_messages'    => array('lang' => 'ZPCHAT_MAX_MESSAGES', 'validate' => 'int:10:500', 'type' => 'text:3:10', 'explain' => true),

                'legend2'                => 'ACP_ZPCHAT_APPEARANCE',
                'zpchat_bg_color'        => array('lang' => 'ZPCHAT_BG_COLOR', 'validate' => 'string', 'type' => 'text:6:6', 'explain' => true),
                'zpchat_bg_own'          => array('lang' => 'ZPCHAT_BG_OWN', 'validate' => 'string', 'type' => 'text:6:6', 'explain' => true),
                'zpchat_bg_other'        => array('lang' => 'ZPCHAT_BG_OTHER', 'validate' => 'string', 'type' => 'text:6:6', 'explain' => true),
                'zpchat_header_color'    => array('lang' => 'ZPCHAT_HEADER_COLOR', 'validate' => 'string', 'type' => 'text:6:6', 'explain' => true),
                'zpchat_button_color'    => array('lang' => 'ZPCHAT_BUTTON_COLOR', 'validate' => 'string', 'type' => 'text:6:6', 'explain' => true),
                'zpchat_width'           => array('lang' => 'ZPCHAT_WIDTH', 'validate' => 'int:200:800', 'type' => 'text:4:10', 'explain' => true),
                'zpchat_height'          => array('lang' => 'ZPCHAT_HEIGHT', 'validate' => 'int:200:800', 'type' => 'text:4:10', 'explain' => true),
            ),
        );

        if ($request->is_set_post('submit'))
        {
            if (!check_form_key($form_key))
            {
                trigger_error('FORM_INVALID' . adm_back_link($this->u_action), E_USER_WARNING);
            }
            $cfg_array = $request->variable('config', array('' => ''), true);
            $error = array();
            validate_config_vars($display_vars['vars'], $cfg_array, $error);

            if (count($error))
            {
                trigger_error(implode('<br />', $error) . adm_back_link($this->u_action), E_USER_WARNING);
            }

            foreach ($display_vars['vars'] as $config_name => $config_definition)
            {
                if (!isset($config_definition['lang']) || strpos($config_name, 'legend') !== false)
                {
                    continue;
                }
                if (!array_key_exists($config_name, $cfg_array))
                {
                    continue;
                }
                set_config($config_name, $cfg_array[$config_name]);
                $this->new_config[$config_name] = $cfg_array[$config_name];
            }

            $cache->destroy('config');
            trigger_error($user->lang['ACP_ZPCHAT_SETTINGS_SAVED'] . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('clear_history'))
        {
            if (!check_form_key($form_key))
            {
                trigger_error('FORM_INVALID' . adm_back_link($this->u_action), E_USER_WARNING);
            }
            $sql = 'DELETE FROM ' . $table_prefix . 'zpchat_messages';
            $db->sql_query($sql);
            trigger_error($user->lang['ACP_ZPCHAT_HISTORY_CLEARED'] . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('export_csv'))
        {
            if (!check_form_key($form_key))
            {
                trigger_error('FORM_INVALID' . adm_back_link($this->u_action), E_USER_WARNING);
            }
            $sql = 'SELECT * FROM ' . $table_prefix . 'zpchat_messages ORDER BY message_id ASC';
            $result = $db->sql_query($sql);

            $csv_data = "ID,User ID,Username,Message,IP Address,Timestamp\n";
            while ($row = $db->sql_fetchrow($result))
            {
                $csv_data .= $row['message_id'] . ',';
                $csv_data .= $row['user_id'] . ',';
                $csv_data .= '"' . str_replace('"', '""', $row['username']) . '",';
                $csv_data .= '"' . str_replace('"', '""', $row['message']) . '",';
                $csv_data .= $row['user_ip'] . ',';
                $csv_data .= date('Y-m-d H:i:s', $row['message_time']) . "\n";
            }
            $db->sql_freeresult($result);

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="zpchat_export_' . date('Ymd_His') . '.csv"');
            echo $csv_data;
            exit;
        }

        $sql = 'SELECT COUNT(*) as total FROM ' . $table_prefix . 'zpchat_messages';
        $result = $db->sql_query($sql);
        $row = $db->sql_fetchrow($result);
        $total_messages = (int) $row['total'];
        $db->sql_freeresult($result);

        $error = array();
        $this->new_config = array();
        foreach ($display_vars['vars'] as $config_key => $vars)
        {
            if (!is_array($vars) && strpos($config_key, 'legend') === false)
            {
                continue;
            }
            $this->new_config[$config_key] = isset($config[$config_key]) ? $config[$config_key] : '';
        }
        validate_config_vars($display_vars['vars'], $this->new_config, $error);

        $template->assign_vars(array(
            'L_TITLE'         => $user->lang[$display_vars['title']],
            'L_TITLE_EXPLAIN' => $user->lang[$display_vars['title'] . '_EXPLAIN'],
            'S_ERROR'         => (count($error)) ? true : false,
            'ERROR_MSG'       => implode('<br />', $error),
            'U_ACTION'        => $this->u_action,
            'TOTAL_MESSAGES'  => $total_messages,
        ));

        foreach ($display_vars['vars'] as $config_key => $vars)
        {
            if (strpos($config_key, 'legend') !== false)
            {
                $legend_label = isset($user->lang[$vars]) ? $user->lang[$vars] : $vars;
                $template->assign_block_vars('options', array(
                    'S_LEGEND'  => true,
                    'LEGEND'    => $legend_label,
                ));
                continue;
            }

            if (!is_array($vars))
            {
                continue;
            }

            $tpl_type = explode(':', isset($vars['type']) ? $vars['type'] : '');
            if (count($tpl_type) < 3)
            {
                $tpl_type = array_pad($tpl_type, 3, 0);
            }

            $l_explain = '';
            if (!empty($vars['explain']))
            {
                $l_explain = $user->lang[$vars['lang'] . '_EXPLAIN'];
            }

            $content = build_cfg_template($tpl_type, $config_key, $this->new_config, $config_key, $vars);

            $template->assign_block_vars('options', array(
                'KEY'           => $config_key,
                'TITLE'         => $user->lang[$vars['lang']],
                'S_EXPLAIN'     => !empty($vars['explain']),
                'TITLE_EXPLAIN' => $l_explain,
                'CONTENT'       => $content,
            ));
        }
    }
}