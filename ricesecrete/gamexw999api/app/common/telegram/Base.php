<?php

namespace app\common\telegram;

use app\common\Redis;
use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\KeyboardButton;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use think\facade\Lang;

use app\common\Utils;
use \Longman\TelegramBot\Entities\User;

abstract class Base extends UserCommand {

    protected $user = null;
    protected $message_id = null;
    protected $chat_id = null;
    protected $chat = null;
    /**
     * @var User|null
     */
    protected $from = null;
    protected $bot = null;
    protected $user_id = null;
    public $db = 'default';

    /**
     * Constructor
     *
     * @param Telegram    $telegram
     * @param Update|null $update
     */
    public function __construct(Telegram $telegram, ?Update $update = null) {
        parent::__construct($telegram, $update);
        if ($message = $this->getMessage() ?: $this->getEditedMessage() ?: $this->getChannelPost() ?: $this->getEditedChannelPost()) {
            $this->chat_id = $message->getChat()->getId();
            $this->chat = $message->getChat();
            $this->message_id = $message->getMessageId();
        } elseif ($this->getCallbackQuery()) {
            $message = $this->getCallbackQuery()->getMessage();
            $this->chat_id = $message->getChat()->getId();
            $this->chat = $message->getChat();
            $this->message_id = $message->getMessageId();
        }
        $this->user_id = $this->getId();
        $this->user = $this->findUser();
    }

    private function getId() {
        $this->from = $this->getFrom();
        $user_id = null;
        if ($this->from) {
            $user_id = $this->from->getId();
        }
        return $user_id;
    }

    private function getFrom() {
        $from = null;
        //Try to figure out if the user is an admin
        $update_methods = [
            'getMessage',
            'getEditedMessage',
            'getChannelPost',
            'getEditedChannelPost',
            'getInlineQuery',
            'getChosenInlineResult',
            'getCallbackQuery',
        ];
        foreach ($update_methods as $update_method) {
            $object = call_user_func([$this->update, $update_method]);
            if ($object !== null && $from = $object->getFrom()) {
                break;
            }
        }

        return $from;
    }

    public function send($keyword) {
        if ($keyword) {
            $keyboard = null;
            if (empty($keyword['img']) && empty($keyword['voice']) && empty($keyword['video']) && empty($keyword['content'])) {
                return false;
            }
            if (isset($keyword['url'])) {
                if (strexists($keyword['url'], 'http://') || strexists($keyword['url'], 'https://')) {
                    $btn = [
                        'text' => trim($keyword['url_label']) != '' ? $keyword['url_label'] : $keyword['title'],
                        'url' => $keyword['url'],
                    ];
                } else {
                    $btn = [
                        'text' => trim($keyword['url_label']) != '' ? $keyword['url_label'] : $keyword['title'],
                        'callback_data' => $keyword['url'],
                    ];
                }
                $keyboard = new InlineKeyboard([$btn]);
            }
            if (isset($keyword['img'])) {
                $result = Request::sendPhoto([
                    'chat_id' => $this->chat_id,
//                            'caption' => $keyword->title,
                    'photo' => $keyword['img'],
                    'reply_markup' => $keyboard,
                ]);
                $this->log($result);
            }
            if (isset($keyword['voice'])) {
                $result = Request::sendAudio([
                    'chat_id' => $this->chat_id,
                    'caption' => $keyword['title'],
                    'audio' => $keyword['voice'],
                    'reply_markup' => $keyboard,
                ]);
                $this->log($result);
            }
            if (isset($keyword['video'])) {
                $result = Request::sendVideo([
                    'chat_id' => $this->chat_id,
                    'caption' => $keyword['title'],
                    'video' => $keyword['video'],
                    'reply_markup' => $keyboard,
                ]);
                $this->log($result);
            }
            if (isset($keyword['content'])) {
                $result = Request::sendMessage([
                    'chat_id' => $this->chat_id,
                    'text' => $keyword['content'],
                    'parse_mode' => 'MarkDownV2',
                    'reply_markup' => $keyboard,
                ]);
                $this->log($result);
            }
            return true;
        }
        return false;
    }

    public function log($result) {
        if (!$result->isOk()) {
            Utils::L('bot_error', $result);
        }
        Utils::L('bot_message', $result);
    }

}
