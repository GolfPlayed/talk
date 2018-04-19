<?php

namespace Nahid\Talk\Live;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class WebcastConv implements ShouldQueue
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

    /*
   * Conversation Model Instance
   *
   * @var object
   * */
    protected $conversation;

    /*
   * Participants Model Instance
   *
   * @var object
   * */
    protected $participants;


    /**
     * Set message collections to the properties.
     */
    public function __construct($conversation, $participants)
    {
        $this->conversation = $conversation;
        $this->participants = $participants;
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
        $channelForConversation = $this->broadcast->getConfig('broadcast.app_name').'-conversation-'.$this->conversation['id'];
        $channelForInboxSender = $this->broadcast->getConfig('broadcast.app_name').'-inbox-'.$this->conversation['user_id'];
        foreach($this->participants as $participant_user_id){
            $channelForInboxReceivers[] = $this->broadcast->getConfig('broadcast.app_name').'-inbox-'.$participant_user_id;
        }

        $this->broadcast->pusher->trigger([$channelForConversation, implode(',', $channelForInboxReceivers)], 'talk-create-conversation', $this->conversation);
    }
}
