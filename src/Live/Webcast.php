<?php

namespace Nahid\Talk\Live;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class Webcast implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /*
   * Message Model Instance
   *
   * @var object
   * */
    protected $message;

    /*
     * Broadcast class instance
     *
     * @var object
     * */
    protected $broadcast;

    /**
     * Set message collections to the properties.
     */
    public function __construct($message)
    {
        $this->message = $message;
    }

    /*
     * Execute the job and broadcast to the pusher channels
     *
     * @param \Nahid\Talk\Live\Broadcast $broadcast
     * @return void
     */
    public function handle(Broadcast $broadcast)
    {
        $this->broadcast = $broadcast;
        $channelForConversation = $this->broadcast->getConfig('broadcast.app_name').'-conversation-'.$this->message['conversation_id'];
        $this->broadcast->pusher->trigger([$channelForConversation], 'talk-send-message', $this->message);
    }
}
