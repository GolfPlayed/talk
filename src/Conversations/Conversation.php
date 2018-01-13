<?php

namespace Nahid\Talk\Conversations;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $table = 'conversations';
    public $timestamps = true;
    public $fillable = [
        'user_id',
        'name',
        'image',
        'group',
        'private',
        'status',
    ];

    public function participants()
    {
        return $this->hasMany('Nahid\Talk\Conversations\ConversationParticipant', 'conversation_id', 'id');
    }

    /*
     * make a relation between message
     *
     * return collection
     * */
    public function messages()
    {
        return $this->hasMany('Nahid\Talk\Messages\Message', 'conversation_id');
    }

    /*
     * make a relation between first user from conversation
     *
     * return collection
     * */
    public function creator()
    {
        return $this->belongsTo(config('talk.user.model'), 'user_id', 'id');
    }
}
