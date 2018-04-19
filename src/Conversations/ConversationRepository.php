<?php

namespace Nahid\Talk\Conversations;

use App\Models\GolfPlayed\ConversationRemove;
use App\Models\GolfPlayed\HomeCourse;
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

    public function participantsById($id)
    {
        $participants = ConversationParticipant::where('conversation_id', $id);
        if($participants->exists()){
            return $participants->get();
        }
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

        $conversations = Conversation::where('user_id', $user1)
        ->orWhere('user_id', $user2)->pluck('id');
        $conversationsParticipants = ConversationParticipant::whereIn('conversation_id', $conversations)->whereIn('user_id', [$user1, $user2]);

        if($conversationsParticipants->exists()){
            return $conversationsParticipants->first()->conversation_id;
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

        $removed_conversations = ConversationRemove::where('user_id', $user)
            ->where('messages_removed',0)
            ->get()
            ->pluck('conversation_id')
            ->toArray();

        $conversations_as_participant = ConversationParticipant::where('user_id', $user)
            ->whereNotIn('conversation_id', $removed_conversations)->get()->pluck('conversation_id');

        $conversations_as_participant_creators = Conversation::whereIn('id', $conversations_as_participant)->get()->pluck('user_id');

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
        }, 'creator', 'creator.profile', 'participants' => function($q) {
            return $q->where('active', 1);
        }, 'participants.users'])
            ->where(function($q) use($user, $removed_conversations){
                $q->where('user_id', $user);
                $q->whereNotIn('id', $removed_conversations);
            })
            ->orWhereIn('id', $conversations_as_participant)
            ->where('status', 1)
            ->offset($offset)
            ->take($take)
            ->orderBy('updated_at', $order)
            ->get();

        $threads = [];
        foreach ($msgThread as $thread) {
            $collection = (object)null;
            $collection->conversation_id = $thread->id;
            $collection->unread = $thread->messages->where('is_seen', 0)->count();
            $collection->thread = $thread->messages->first();
            $collection->creator = $thread->creator;
            $collection->group = (bool)$thread->group;
            $collection->name = $thread->name ?? null;
            if ($thread->group == 0) {
                $collection->participants = User::with('profile')->where('id', $thread->participants[0]->user_id)->first();
            } else {
                $collection->first_name = $thread->first_name;
                $collection->last_name = $thread->last_name;
                $collection->image = $thread->image;
                $collection->participants = $thread->participants; //->pluck('user_id');
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
    public function getMessagesById($conversation_id, $userId, $offset, $take)
    {

        $removed_messages = ConversationRemove::where('user_id', $userId)
            ->where('conversation_id', $conversation_id)
            ->where('messages_removed', 1)
            ->first();

        $messages = [];
        $recipients = [];
        if(!$removed_messages){
            $messages = Message::where('conversation_id', $conversation_id)
                ->select('*', 'user_id as user')
                ->offset($offset)->take($take)
                ->get()
                ->toArray();

            $messages = collect($messages)->map(function($item, $key){
                $item = collect($item)->map(function($item, $key){
                    if($key == 'user'){
                        $user = User::with('profile')->where('id', '=', $item)->first(['id', 'first_name', 'last_name']);
                        $user->home_course = null;
                        $user->home_course_logo = null;
                        $home_course = HomeCourse::with('course')->where('user_id', $user->id)->first();
                        if(!is_null($home_course)){
                            $user->home_course = $home_course->course->name;
                            $user->home_course_logo = $home_course->course->logo;
                        }
                        return $user;
                    }
                    return $item;
                });
                return $item;
            });

            $participants = ConversationParticipant::where('conversation_id', $conversation_id)->get()->pluck('user_id');
            $recipients = User::with('profile')->whereIn('id', $participants)->get(['id', 'first_name', 'last_name']);
//        $recipients = $conversations->messages['withUser'];
//        $messages = $conversations->messages['messages'];
        } else {

            $messages = Message::where('conversation_id', $conversation_id)
                ->where('id', '>', $removed_messages->last_message_id)
                ->select('*', 'user_id as user')
                ->offset($offset)->take($take)
                ->get()
                ->toArray();

            $messages = collect($messages)->map(function($item, $key){
                $item = collect($item)->map(function($item, $key){
                    if($key == 'user'){
                        $user = User::with('profile')->where('id', '=', $item)->first(['id', 'first_name', 'last_name']);
                        $user->home_course = null;
                        $user->home_course_logo = null;
                        $home_course = HomeCourse::with('course')->where('user_id', $user->id)->first();
                        if(!is_null($home_course)){
                            $user->home_course = $home_course->course->name;
                            $user->home_course_logo = $home_course->course->logo;
                        }
                        return $user;
                    }
                    return $item;
                });
                return $item;
            });
            $participants = ConversationParticipant::where('conversation_id', $conversation_id)->get()->pluck('user_id');
            $recipients = User::with('profile')->whereIn('id', $participants)->get(['id', 'first_name', 'last_name']);
        }
        return [
            'messages' => $messages,
            'withUser' => $recipients,
        ];

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
