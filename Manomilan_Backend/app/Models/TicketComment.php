<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TicketComment extends Model
{
    protected $table = 'ticket_comments';

    protected $fillable = [
        'ticket_id',
        'ticket_item_id',
        'comment_by',
        'user_id',
        'comment',
        'attachment',
        'is_internal',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function ticketItem()
    {
        return $this->belongsTo(TicketItem::class, 'ticket_item_id');
    }
}