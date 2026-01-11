<?php

namespace Icso\Accounting\Services;

use Icso\Accounting\Models\ActivityLog;

class ActivityLogService
{
    public function log(array $data): void
    {
        ActivityLog::create([
            'user_id'         => $data['user_id'] ?? null,
            'action'          => $data['action'],
            'model_type'      => $data['model_type'],
            'model_id'        => $data['model_id'] ?? null,
            'old_values'      => $data['old_values'] ?? null,
            'new_values'      => $data['new_values'] ?? null,
            'request_payload' => $data['request_payload'] ?? null,
            'ip_address'      => $data['ip_address'] ?? null,
            'user_agent'      => $data['user_agent'] ?? null,
            'created_at'      => now(),
        ]);
    }

    public static function insertLog(array $data): void
    {
        ActivityLog::create([
            'user_id'         => $data['user_id'] ?? null,
            'action'          => $data['action'],
            'model_type'      => $data['model_type'],
            'model_id'        => $data['model_id'] ?? null,
            'old_values'      => $data['old_values'] ?? null,
            'new_values'      => $data['new_values'] ?? null,
            'request_payload' => $data['request_payload'] ?? null,
            'ip_address'      => $data['ip_address'] ?? null,
            'user_agent'      => $data['user_agent'] ?? null,
            'created_at'      => now(),
        ]);
    }
}