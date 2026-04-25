<?php
/**
 *
 * @package ZP Chat
 * @copyright (c) 2024 Marco Zannoni
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace marcozp\zpchat\controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class main_controller
{
    protected $config;
    protected $db;
    protected $language;
    protected $request;
    protected $user;
    protected $table_prefix;

    public function __construct(
        \phpbb\config\config $config,
        \phpbb\db\driver\driver_interface $db,
        \phpbb\language\language $language,
        \phpbb\request\request $request,
        \phpbb\user $user,
        $table_prefix
    ) {
        $this->config = $config;
        $this->db = $db;
        $this->language = $language;
        $this->request = $request;
        $this->user = $user;
        $this->table_prefix = $table_prefix;
    }

    public function handle($action = '')
    {
        $this->language->add_lang('info_acp_zpchat', 'marcozp/zpchat');

        if ($action === 'sse') {
            return $this->sse();
        } elseif ($action === 'messages') {
            return $this->messages();
        } elseif ($action === 'send') {
            return $this->send();
        }

        return new Response('Invalid action', 400);
    }

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

    public function sse()
    {
        if (empty($this->config['zpchat_enabled'])) {
            return new Response('', 403);
        }

        if ($this->user->data['user_id'] == ANONYMOUS) {
            return new Response('', 403);
        }

        $last_id = $this->request->variable('last_id', 0);
        $recipient_id = $this->request->variable('recipient_id', 0);
        $expiry  = !empty($this->config['zpchat_expiry_seconds']) ? (int) $this->config['zpchat_expiry_seconds'] : 60;
        $db      = $this->db;
        $table   = $this->table_prefix;
        $user    = $this->user;

        $max_messages = !empty($this->config['zpchat_max_messages']) ? (int) $this->config['zpchat_max_messages'] : 100;

        $conversation_filter = $this->get_conversation_filter($recipient_id);

        $response = new StreamedResponse(function () use ($last_id, $expiry, $db, $table, $user, $max_messages, $conversation_filter) {
            $sql = 'SELECT message_id, user_id, username, message, message_time, user_color, recipient_id
                FROM ' . $table . 'zpchat_messages
                WHERE ' . $conversation_filter;
            if ($last_id > 0) {
                $sql .= ' AND message_id > ' . (int) $last_id;
            }
            $sql .= ' ORDER BY message_id ASC LIMIT ' . ($max_messages + 10);

            $result   = $db->sql_query($sql);
            $messages = [];
            $max_id   = $last_id;

            while ($row = $db->sql_fetchrow($result)) {
                $messages[] = [
                    'message_id'   => (int) $row['message_id'],
                    'user_id'      => (int) $row['user_id'],
                    'username'     => $row['username'],
                    'message'      => $row['message'],
                    'message_time' => (int) $row['message_time'],
                    'user_color'   => $row['user_color'],
                    'recipient_id' => (int) $row['recipient_id'],
                ];
                $max_id = (int) $row['message_id'];
            }
            $db->sql_freeresult($result);

            $data = json_encode(['messages' => $messages, 'last_id' => $max_id, 'expiry' => $expiry]);
            echo "data: {$data}\n\n";
            flush();
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    public function messages()
    {
        if (empty($this->config['zpchat_enabled'])) {
            return new JsonResponse(['success' => false, 'error' => 'Forbidden'], 403);
        }

        if ($this->user->data['user_id'] == ANONYMOUS) {
            return new JsonResponse(['success' => false, 'error' => 'Forbidden'], 403);
        }

        $last_id = $this->request->variable('last_id', 0);
        $recipient_id = $this->request->variable('recipient_id', 0);
        $expiry  = !empty($this->config['zpchat_expiry_seconds']) ? (int) $this->config['zpchat_expiry_seconds'] : 60;
        $max_messages = !empty($this->config['zpchat_max_messages']) ? (int) $this->config['zpchat_max_messages'] : 100;

        $conversation_filter = $this->get_conversation_filter($recipient_id);

        $sql = 'SELECT message_id, user_id, username, message, message_time, user_color, recipient_id
            FROM ' . $this->table_prefix . 'zpchat_messages
            WHERE ' . $conversation_filter;
        if ($last_id > 0) {
            $sql .= ' AND message_id > ' . (int) $last_id;
        }
        $sql .= ' ORDER BY message_id ASC LIMIT ' . ($max_messages + 10);

        $result   = $this->db->sql_query($sql);
        $messages = [];
        $max_id   = $last_id;

        while ($row = $this->db->sql_fetchrow($result)) {
            $messages[] = [
                'message_id'   => (int) $row['message_id'],
                'user_id'      => (int) $row['user_id'],
                'username'     => $row['username'],
                'message'      => $row['message'],
                'message_time' => (int) $row['message_time'],
                'user_color'   => $row['user_color'],
                'recipient_id' => (int) $row['recipient_id'],
            ];
            $max_id = (int) $row['message_id'];
        }
        $this->db->sql_freeresult($result);

        return new JsonResponse(['messages' => $messages, 'last_id' => $max_id, 'expiry' => $expiry]);
    }

    public function send()
    {
        if (empty($this->config['zpchat_enabled'])) {
            return new JsonResponse(['success' => false, 'error' => 'Forbidden'], 403);
        }

        if ($this->user->data['user_id'] == ANONYMOUS) {
            return new JsonResponse(['success' => false, 'error' => 'Forbidden'], 403);
        }

        $message = $this->request->variable('message', '', true);
        $message = trim(strip_tags($message));
        $recipient_id = $this->request->variable('recipient_id', 0);

        if (empty($message) || strlen($message) > 500) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid message'], 400);
        }

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

        $sql = 'INSERT INTO ' . $this->table_prefix . 'zpchat_messages ' . $this->db->sql_build_array('INSERT', $sql_ary);
        $this->db->sql_query($sql);

        $expiry = !empty($this->config['zpchat_expiry_seconds']) ? (int) $this->config['zpchat_expiry_seconds'] : 60;
        $this->clean_old_messages($expiry);

        $max_messages = !empty($this->config['zpchat_max_messages']) ? (int) $this->config['zpchat_max_messages'] : 100;
        $this->trim_messages($max_messages);

        return new JsonResponse(['success' => true]);
    }

    protected function clean_old_messages($expiry)
    {
        $cutoff = time() - $expiry;
        $sql = 'DELETE FROM ' . $this->table_prefix . 'zpchat_messages 
            WHERE message_time < ' . (int) $cutoff;
        $this->db->sql_query($sql);
    }

    protected function trim_messages($max_messages)
    {
        $sql = 'SELECT message_id FROM ' . $this->table_prefix . 'zpchat_messages 
            ORDER BY message_id DESC';
        $result = $this->db->sql_query($sql);
        
        $ids = [];
        $count = 0;
        while ($row = $this->db->sql_fetchrow($result)) {
            $ids[] = (int) $row['message_id'];
            $count++;
            if ($count >= $max_messages) {
                break;
            }
        }
        $this->db->sql_freeresult($result);

        if (count($ids) > 0) {
            $sql = 'DELETE FROM ' . $this->table_prefix . 'zpchat_messages 
                WHERE message_id < ' . min($ids);
            $this->db->sql_query($sql);
        }
    }

}