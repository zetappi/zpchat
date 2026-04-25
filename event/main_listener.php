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
            'core.viewtopic_post_row_after' => 'on_viewtopic_post_row_after',
        ];
    }

    public function on_viewtopic_post_row_after($event)
    {
        if (empty($this->config['zpchat_enabled']) || empty($this->config['zpchat_allow_private'])) {
            return;
        }

        if ($this->user->data['user_id'] == ANONYMOUS) {
            return;
        }

        $user_id = $event['user_poster_id'];
        $username = $event['post_row']['POST_AUTHOR'];

        if ($user_id == 0 || $user_id == $this->user->data['user_id']) {
            return;
        }

        // Aggiunge data attributes al post per inserimento link chat via JavaScript
        $event['post_row'] = array_merge($event['post_row'], [
            'ZPCHAT_USER_ID' => $user_id,
            'ZPCHAT_USERNAME' => $username,
        ]);
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
}