<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TicketItem extends Model
{
    protected $table = 'ticket_items';

    protected $fillable = [
        'ticket_id',
        'attachment',
        'title',
        'description',
        'item_type',
        'priority',
        'severity',
        'ticket_status',
        'status',
    ];

    protected static function boot()
    {
        parent::boot();

        // Recalculate parent ticket status whenever an item is saved
        static::saved(function ($item) {
            if ($item->ticket) {
                $item->ticket->recalculateStatus();
            }
        });

        // Recalculate parent ticket status whenever an item is deleted
        static::deleted(function ($item) {
            if ($item->ticket) {
                $item->ticket->recalculateStatus();
            }
        });
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function statusHistory()
    {
        return $this->hasMany(TicketStatusHistory::class, 'ticket_item_id');
    }

    public function comments()
    {
        return $this->hasMany(TicketComment::class, 'ticket_item_id');
    }

    public function getAttachmentUrlAttribute()
    {
        if (!$this->attachment) {
            return null;
        }

        if (strpos($this->attachment, 'tickets/') === 0) {
            return asset('storage/' . $this->attachment);
        }

        return asset($this->attachment);
    }
}