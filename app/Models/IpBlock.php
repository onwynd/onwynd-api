<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IpBlock extends Model
{
    protected $fillable = [
        'ip_or_cidr', 'reason', 'is_active', 'blocked_by', 'expires_at',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function blockedByUser()
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    /** Check if a given IP is currently blocked by any active record. */
    public static function isBlocked(string $ip): bool
    {
        return self::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get()
            ->contains(fn ($b) => self::ipMatchesCidr($ip, $b->ip_or_cidr));
    }

    public static function ipMatchesCidr(string $ip, string $cidr): bool
    {
        if (! str_contains($cidr, '/')) {
            return $ip === $cidr;
        }
        [$subnet, $bits] = explode('/', $cidr);
        $ipLong     = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }
        $mask = -1 << (32 - (int)$bits);
        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
