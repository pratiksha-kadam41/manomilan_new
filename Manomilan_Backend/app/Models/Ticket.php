<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $table = 'tickets';

    protected $fillable = [
        'ticket_no',
        'application_id',
        'module_id',
        'user_id',
        'subject',
        'description',
        'category',
        'ticket_status',
        'attachment',
        'status',
    ];

    protected static function boot()
    {
        parent::boot();

        static::retrieved(function ($ticket) {
            $ticket->recalculateStatus();
        });
    }

    public function recalculateStatus()
    {
        $items = $this->items;

        if ($items->count() == 0) {
            return;
        }

        $allResolved = $items->every(function ($item) {
            return $item->ticket_status === 'Resolved';
        });

        $anyInProgress = $items->contains(function ($item) {
            return $item->ticket_status === 'In Progress';
        });

        $newStatus = 'Open';

        if ($allResolved) {
            $newStatus = 'Resolved';
        } elseif ($anyInProgress) {
            $newStatus = 'In Progress';
        }

        if ($this->ticket_status != $newStatus) {
            $this->ticket_status = $newStatus;
            $this->saveQuietly();
        }
    }

    /**
     * Save the model to the database without raising any events.
     *
     * @param  array  $options
     * @return bool
     */
    public function saveQuietly(array $options = [])
    {
        $dispatcher = static::getEventDispatcher();

        if ($dispatcher) {
            static::unsetEventDispatcher();
        }

        $saved = $this->save($options);

        if ($dispatcher) {
            static::setEventDispatcher($dispatcher);
        }

        return $saved;
    }

    public function application()
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    public function module()
    {
        return $this->belongsTo(Module::class, 'module_id');
    }

    public function items()
    {
        return $this->hasMany(TicketItem::class, 'ticket_id');
    }

    public function comments()
    {
        return $this->hasMany(TicketComment::class, 'ticket_id');
    }

    public function statusHistory()
    {
        return $this->hasMany(TicketStatusHistory::class, 'ticket_id');
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

    public function getCreatedAtIstAttribute()
    {
        return $this->created_at
            ? \Carbon\Carbon::parse($this->created_at)->setTimezone('Asia/Kolkata')
            : null;
    }

    public function getUpdatedAtIstAttribute()
    {
        return $this->updated_at
            ? \Carbon\Carbon::parse($this->updated_at)->setTimezone('Asia/Kolkata')
            : null;
    }
}