<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TicketStatusHistory extends Model
{
    protected $table = 'ticket_status_history';

    public $timestamps = false;

    protected $fillable = [
        'ticket_id',
        'ticket_item_id',
        'old_status',
        'new_status',
        'remarks',
        'changed_by',
        'created_at',
    ];

    protected $dates = [
        'created_at',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });
    }

    public function getCreatedAtIstAttribute()
    {
        return $this->created_at
            ? \Carbon\Carbon::parse($this->created_at)->setTimezone('Asia/Kolkata')
            : null;
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function ticketItem()
    {
        return $this->belongsTo(TicketItem::class, 'ticket_item_id');
    }
}