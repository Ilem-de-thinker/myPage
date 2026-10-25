<?php

namespace App\Domains\Chat\Models;

use Illuminate\Database\Eloquent\Model;

class ChatAttachment extends Model
{
    protected $fillable = ['chat_message_id', 'path', 'mime_type', 'size', 'original_name'];
}
