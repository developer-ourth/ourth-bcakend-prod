<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'is_read',
        'read_at',
        'send_email',
        'email_sent',
        'send_sms',
        'sms_sent',
        'send_push',
        'push_sent',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'is_read' => 'boolean',
            'read_at' => 'datetime',
            'send_email' => 'boolean',
            'email_sent' => 'boolean',
            'send_sms' => 'boolean',
            'sms_sent' => 'boolean',
            'send_push' => 'boolean',
            'push_sent' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
