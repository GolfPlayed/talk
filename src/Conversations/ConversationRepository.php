<?php

namespace Nahid\Talk\Conversations;

use SebastianBerc\Repositories\Repository;
use Nahid\Talk\Messages\Message;
use App\User;

class ConversationRepository extends Repository
{
    /*
     * this method is default method for repository package
     *
     * @return  \Nahid\Talk\Conersations\Conversation
     * */
    public function takeModel()
    {
        return Conversation::class;
    }

    /*
     * check this given conversation exists
     *
     * @param   int $id
     * @return  bool
     * */
    public function existsById($id)
    {
        $conversation = $this->find($id);
        if ($conversation) {
            return true;
        }

        return false;
    }

    /*
     * check this given two users are already in a conversation
     *
     * @param   int $user1
     * @param   int $user2
     * @return  int|bool
     * */
    public function isExistsAmongTwoUsers($user1, $user2)
    {
        $conversation = Conversation::where('user_id', $user1)
        ->orWhere('user_id', $user2);

        if($conversation->exists()){
            // TODO: Add lookup for participants
            return $conversation->first()->id;
        }

        return false;
    }

    /*
     * check this given user is involved with this given $conversation
     *
     * @param   int $conversationId
     * @param   int $userId
     * @return  bool
     * */
    public function isUserExists($conversationId, $userId)
    {
        $exists = Conversation::where('id', $conversationId)
            ->where(function ($query) use ($userId) {
                $query->where('user_one', $userId)->orWhere('user_two', $userId);
            })
            ->exists();

        return $exists;
    }

    /*
     * retrieve all message thread without soft deleted message with latest one message and
     * sender and receiver user model
     *
     * @param   int $user
     * @param   int $offset
     * @param   int $take
     * @return  collection
     * */
    public function threads($user, $order, $offset, $take)
    {
        $conv = new Conversation();
        $conv->authUser = $user;

        $msgThread = $conv->with(['messages' => function ($q) use ($user) {
            return $q->where(function ($q) use ($user) {
                $q->where('user_id', $user)
                ->where('deleted_from_sender', 0);
            })
            ->orWhere(function ($q) use ($user) {
                $q->where('user_id', '!=', $user);
                $q->where('deleted_from_receiver', 0);
            })
            ->latest();
        }, 'creator', 'participants' => function($q) {
            return $q->where('active', 1);
        }])
        ->where('user_id', $user)
        ->where('status', 1);
        ->offset($offset)
        ->take($take)
        ->orderBy('updated_at', $order)
        ->get();

        $threads = [];
        foreach ($msgThread as $thread) {
            $collection = (object) null;
            $collection->thread = $thread->messages->first();
            $collection->creator = $thread->creator;
            $collection->group = (bool)$thread->group;
            if($thread->group == 0){
                $collection->name = $thread->name;
                $collection->image = $thread->image;
                $collection->participants = User::where('id', $thread->participants[0]->user_id)->first();
            }else{
                $collection->participants = $thread->participants->pluck('user_id');
            }
            $threads[] = $collection;
        }

        return collect($threads);
    }

    /*
     * retrieve all message thread with latest one message and sender and receiver user model
     *
     * @param   int $user
     * @param   int $offset
     * @param   int $take
     * @return  collection
     * */
    public function threadsAll($user, $offset, $take)
    {
        $msgThread = Conversation::with(['messages' => function ($q) use ($user) {
            return $q->latest();
        }, 'userone', 'usertwo'])
            ->where('user_one', $user)->orWhere('user_two', $user)->offset($offset)->take($take)->get();

        $threads = [];

        foreach ($msgThread as $thread) {
            $conversationWith = ($thread->userone->id == $user) ? $thread->usertwo : $thread->userone;
            $message = $thread->messages->first();
            $message->user = $conversationWith;
            $threads[] = $message;
        }

        return collect($threads);
    }

    /*
     * get all conversations by given conversation id
     *
     * @param   int $conversationId
     * @param   int $userId
     * @param   int $offset
     * @param   int $take
     * @return  collection
     * */
    public function getMessagesById($conversationId, $userId, $offset, $take)
    {

        $conversation = Conversation::where('id', $conversationId)->first();
        if(!is_null($conversation)){
            if($conversation->group){

                $participants = ConversationParticipant::where('conversation_id', $conversationId)->get()->pluck('user_id');
                $withUsers = User::whereIn('id', $participants)->get(['id', 'name']);
                foreach($withUsers as $wu){
                    $wu->profile = $wu->profile();
                }

                if(!empty($withUsers)){
                    return [
                        'messages' => Message::where('deleted_from_sender', 0)
                        ->where('deleted_from_receiver', 0)
                        ->where('conversation_id', $conversationId)
                        ->offset($offset)->take($take)
                        ->get(),
                        'withUser' => $withUsers,
                    ];
                }

            }else{
                $participant = ConversationParticipant::where('conversation_id', $conversationId)->first();
                $withUser = User::where('id', $participant->user_id)->first(['id', 'name']);
                $withUser->profile = $withUser->profile();
                if(!is_null($withUser)){
                    return [
                        'messages' => Message::where('deleted_from_sender', 0)
                        ->where('deleted_from_receiver', 0)
                        ->where('conversation_id', $conversationId)
                        ->offset($offset)->take($take)
                        ->get(),
                        'withUser' => $withUser,
                    ];
                }
            }
        }



    }

    /*
     * get all conversations with soft deleted message by given conversation id
     *
     * @param   int $conversationId
     * @param   int $offset
     * @param   int $take
     * @return  collection
     * */
    public function getMessagesAllById($conversationId, $offset, $take)
    {
        return $this->with(['messages' => function ($q) use ($offset, $take) {
            return $q->offset($offset)->take($take);
        }, 'userone', 'usertwo'])->find($conversationId);
    }
}
