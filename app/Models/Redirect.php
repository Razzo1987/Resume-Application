<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Redirect managed from the admin (or created automatically when a slug or a URL segment changes).
 *
 * @property int $id
 * @property string $from_path
 * @property string $to_path
 * @property int $status_code
 * @property bool $is_active
 * @property int $hits
 * @property Carbon|null $last_hit_at
 */
class Redirect extends Model
{
    protected $fillable = ['from_path', 'to_path', 'status_code', 'is_active'];

    protected $attributes = [
        'status_code' => 301,
        'is_active' => true,
        'hits' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'is_active' => 'boolean',
            'hits' => 'integer',
            'last_hit_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Redirect $redirect): void {
            $redirect->from_path = self::normalizePath($redirect->from_path);
            $redirect->to_path = str_starts_with($redirect->to_path, 'http')
                ? $redirect->to_path
                : self::normalizePath($redirect->to_path);
        });
    }

    public static function normalizePath(string $path): string
    {
        $path = '/'.ltrim(trim($path), '/');

        return $path === '/' ? $path : rtrim($path, '/');
    }
}
