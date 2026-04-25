<?php
/**
 *
 * @package ZP Chat
 * @copyright (c) 2024 Marco Zannoni
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace marcozp\zpchat\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class main_listener implements EventSubscriberInterface
{
    protected $config;
    protected $user;
    protected $template;
    protected $ext_manager;
    protected $helper;

    public function __construct(
        \phpbb\config\config $config,
        \phpbb\user $user,
        \phpbb\template\template $template,
        \phpbb\extension\manager $ext_manager,
        \phpbb\controller\helper $helper
    )
    {
        $this->config      = $config;
        $this->user        = $user;
        $this->template    = $template;
        $this->ext_manager = $ext_manager;
        $this->helper      = $helper;
    }

    public static function getSubscribedEvents()
    {
        return [
            'core.user_setup'              => 'on_user_setup',
            'core.page_footer'             => 'on_page_footer',
            'core.viewtopic_modify_post_row' => 'on_viewtopic_modify_post_row',
        ];
    }

    public function on_user_setup($event)
    {
        $lang_set_ext = $event['lang_set_ext'];
        $lang_set_ext[] = [
            'ext_name' => 'marcozp/zpchat',
            'lang_set' => 'info_acp_zpchat',
        ];
        $event['lang_set_ext'] = $lang_set_ext;
    }

    protected function assign_chat_vars()
    {
        if (empty($this->config['zpchat_enabled'])) {
            return;
        }

        if ($this->user->data['user_id'] == ANONYMOUS) {
            return;
        }

        $ext_path = $this->ext_manager->get_extension_path('marcozp/zpchat', true);

        $this->template->assign_vars([
            'S_ZPCHAT_ENABLED'        => true,
            'S_ZPCHAT_USER_LOGGED'    => true,
            'ZPCHAT_USERNAME'         => $this->user->data['username'],
            'ZPCHAT_USER_COLOR'       => $this->user->data['user_colour'] ?: '00aaee',
            'ZPCHAT_REFRESH_INTERVAL' => (int) ($this->config['zpchat_refresh_interval'] ?? 2000),
            'ZPCHAT_TPL_PATH'         => $ext_path . 'styles/all/template/',
            'ZPCHAT_THEME_PATH'       => $ext_path . 'styles/all/theme/',
            'ZPCHAT_URL_SEND'         => $this->helper->route('marcozp_zpchat_send'),
            'ZPCHAT_URL_MESSAGES'     => $this->helper->route('marcozp_zpchat_messages'),
            'ZPCHAT_URL_SSE'          => $this->helper->route('marcozp_zpchat_sse'),
            'ZPCHAT_BG_COLOR'         => $this->config['zpchat_bg_color'] ?? 'f5f5f5',
            'ZPCHAT_BG_OWN'           => $this->config['zpchat_bg_own'] ?? 'e3f2fd',
            'ZPCHAT_BG_OTHER'         => $this->config['zpchat_bg_other'] ?? 'ffffff',
            'ZPCHAT_HEADER_COLOR'     => $this->config['zpchat_header_color'] ?? '00aaee',
            'ZPCHAT_BUTTON_COLOR'     => $this->config['zpchat_button_color'] ?? '00aaee',
            'ZPCHAT_WIDTH'            => (int) ($this->config['zpchat_width'] ?? 320),
            'ZPCHAT_HEIGHT'           => (int) ($this->config['zpchat_height'] ?? 400),
        ]);
    }

    public function on_page_footer($event)
    {
        $this->assign_chat_vars();
    }

    public function on_viewtopic_modify_post_row($event)
    {
        if (empty($this->config['zpchat_enabled']) || empty($this->config['zpchat_allow_private'])) {
            return;
        }

        if ($this->user->data['user_id'] == ANONYMOUS) {
            return;
        }

        $post_row = $event['post_row'];
        $user_id = isset($event['user_poster_data']['user_id']) ? (int) $event['user_poster_data']['user_id'] : 0;
        $username = isset($event['user_poster_data']['username']) ? $event['user_poster_data']['username'] : '';

        if ($user_id == 0) {
            return;
        }

        // Non mostrare link per se stessi
        if ($user_id == $this->user->data['user_id']) {
            return;
        }

        $chat_url = $this->helper->route('marcozp_zpchat_send');

        // Aggiungi link chat vicino all'avatar
        $post_row['POSTER_AVATAR'] .= '<a href="#" class="zpchat-direct-link" data-recipient="' . $user_id . '" data-recipient-name="' . htmlspecialchars($username) . '" title="Chat privata con ' . htmlspecialchars($username) . '">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="#00aaee"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12z"/></svg>
        </a>';

        $event['post_row'] = $post_row;
    }
}