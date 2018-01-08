<?php

namespace Nahid\Talk\Conversations;

use Illuminate\Database\Eloquent\Model;

class ConversationParticipant extends Model
{
    protected $table = 'conversation_participants';
    public $timestamps = true;
    public $fillable = [
        'conversation_id',
        'user_id',
    ];

    /*
     * make a relation between message
     *
     * return collection
     * */
    public function messages()
    {
        return $this->hasMany('Nahid\Talk\Messages\Message', 'conversation_id')
            ->with('sender');
    }

    /*
     * make a relation between users from conversation
     *
     * return collection
     * */
    public function users()
    {
        return $this->belongsTo(config('talk.user.model', 'App\User'),  'user_id');
    }

}
