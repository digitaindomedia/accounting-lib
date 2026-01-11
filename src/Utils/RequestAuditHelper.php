<?php

namespace Icso\Accounting\Utils;
use Illuminate\Http\Request;
class RequestAuditHelper
{
    protected static array $excludedKeys = [
        'password',
        'password_confirmation',
        '_token',
        'remember_token',
        'files',
        'file',
    ];

    public static function sanitize(Request $request): array
    {
        return collect($request->all())
            ->except(self::$excludedKeys)
            ->map(fn ($value) => self::clean($value))
            ->toArray();
    }

    protected static function clean($value)
    {
        if (is_array($value)) {
            return array_map([self::class, 'clean'], $value);
        }

        if (is_object($value)) {
            return '[OBJECT]';
        }

        if (is_resource($value)) {
            return '[RESOURCE]';
        }

        return $value;
    }
}