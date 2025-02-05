<?php

namespace App\Models;

use Carbon\Carbon;

class Medal extends NexusModel
{
    const GET_TYPE_EXCHANGE = 1;

    const GET_TYPE_GRANT = 2;

    public static array $getTypeText = [
        self::GET_TYPE_EXCHANGE => ['text' => 'Exchange'],
        self::GET_TYPE_GRANT => ['text' => 'Grant'],
    ];

    protected $fillable = ['name', 'description', 'image_large', 'image_small', 'price', 'duration', 'get_type', 'bonus', 'purchase_start', 'purchase_end','stock'];

    public $timestamps = true;

    public static function listGetTypes($onlyKeyValues = false): array
    {
        $results = self::$getTypeText;
        $keyValues = [];
        foreach ($results as $type => &$info) {
            $text = nexus_trans("medal.get_types.$type");
            $keyValues[$type] = $text;
            $info['text'] = $text;
        }
        if ($onlyKeyValues) {
            return $keyValues;
        }
        return $results;
    }

    public function getGetTypeTextAttribute($value): string
    {
        return nexus_trans("medal.get_types." . $this->get_type);
    }

    public function users(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_medals', 'medal_id', 'uid')->withTimestamps();
    }

    public function valid_users(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->users()->where(function ($query) {
            $query->whereNull('user_medals.expire_at')->orWhere('user_medals.expire_at', '>=', Carbon::now());
        });
    }

    public function checkCanBeBuy()
    {
        if ($this->get_type == self::GET_TYPE_GRANT) {
            throw new \RuntimeException(nexus_trans('medal.grant_only'));
        }
        $now = now();
        $purchaseStart = $this->purchase_start ? Carbon::parse($this->purchase_start) : null;
        $purchaseEnd = $this->purchase_end ? Carbon::parse($this->purchase_end) : null;

        if (($purchaseStart && $purchaseStart->gt($now)) || ($purchaseEnd && $purchaseEnd->lt($now))) {
            throw new \RuntimeException(nexus_trans('medal.purchase_start'));
        }
        if ($this->stock !== null && $this->stock <= 0) {
            throw new \RuntimeException(nexus_trans('medal.stock'));
        }
    }

}
