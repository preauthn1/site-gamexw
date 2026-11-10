<?php

namespace app\common\telegram;

use Longman\TelegramBot\Request;
use Longman\TelegramBot\Entities\ServerResponse;

class MyidCommand extends Base {

    protected $name = 'myid';                      // Your command's name
    protected $description = '查看我的ID';          // Your command description
    protected $usage = '/myid';                    // Usage of your command
    protected $version = '1.0.0';                  // Version of your command

    public function execute(): ServerResponse {
        $message = $this->getMessage();            // Get Message object
        $chat_id = $message->getChat()->getId();   // Get the current Chat ID

        $data = [// Set up the new message data
            'chat_id' => $chat_id, // Set Chat ID to send the message to
            'text' => 'chat_id:' . $chat_id, // Set message to send
        ];

        return Request::sendMessage($data);        // Send message!
    }

}
