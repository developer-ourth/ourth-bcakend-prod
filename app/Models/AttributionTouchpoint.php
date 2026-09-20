<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AttributionTouchpoint extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'source_type',
        'ad_id',
        'adset_id',
        'campaign_id',
        'campaign_name',
        'form_id',
        'fbclid',
        'fbp',
        'fbc',
        'client_ip_address',
        'client_user_agent',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'raw_payload',
    ];

    protected $casts = [
        'raw_payload' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
