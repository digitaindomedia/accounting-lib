<?php

namespace Icso\Accounting\Http\Controllers;

use Icso\Accounting\Models\ActivityLog;
use Icso\Accounting\Utils\Helpers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ActivityLogController extends Controller
{
    protected array $data = [];
    protected array $userNameCache = [];

    public function getAllData(Request $request): JsonResponse
    {
        $params = $this->setQueryParameters($request);
        extract($params);

        $query = ActivityLog::query();

        if (!empty($q)) {
            $search = trim($q);
            $query->where(function ($builder) use ($search) {
                $builder->where('action', 'like', '%' . $search . '%')
                    ->orWhere('model_type', 'like', '%' . $search . '%')
                    ->orWhere('model_id', 'like', '%' . $search . '%')
                    ->orWhere('user_id', 'like', '%' . $search . '%')
                    ->orWhere('ip_address', 'like', '%' . $search . '%')
                    ->orWhere('user_agent', 'like', '%' . $search . '%')
                    ->orWhere('old_values', 'like', '%' . $search . '%')
                    ->orWhere('new_values', 'like', '%' . $search . '%')
                    ->orWhere('request_payload', 'like', '%' . $search . '%');
            });
        }

        if (!empty($userId)) {
            $query->where('user_id', $userId);
        }

        if (!empty($action)) {
            $query->where('action', 'like', '%' . $action . '%');
        }

        if (!empty($modelType)) {
            $query->where(function ($builder) use ($modelType) {
                $builder->where('model_type', $modelType)
                    ->orWhere('model_type', 'like', '%\\' . $modelType);
            });
        }

        if (!empty($fromDate) && !empty($untilDate)) {
            $query->whereBetween('created_at', [$fromDate . ' 00:00:00', $untilDate . ' 23:59:59']);
        } elseif (!empty($fromDate)) {
            $query->where('created_at', '>=', $fromDate . ' 00:00:00');
        } elseif (!empty($untilDate)) {
            $query->where('created_at', '<=', $untilDate . ' 23:59:59');
        }

        $total = (clone $query)->count();
        $logs = $query->orderByDesc('created_at')
            ->orderByDesc('id')
            ->offset($page)
            ->limit($perpage)
            ->get();

        if ($logs->count() > 0) {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $logs->map(fn(ActivityLog $log) => $this->transformLog($log))->values();
            $this->data['has_more'] = Helpers::hasMoreData($total, $page, $logs);
            $this->data['total'] = $total;
            $this->data['filters'] = [
                'q' => $q,
                'from_date' => $fromDate,
                'until_date' => $untilDate,
                'user_id' => $userId,
                'model_type' => $modelType,
                'action' => $action,
                'page' => $page,
                'perpage' => $perpage,
            ];
        } else {
            $this->data['status'] = false;
            $this->data['message'] = 'Data tidak ditemukan';
            $this->data['data'] = [];
            $this->data['has_more'] = false;
            $this->data['total'] = 0;
        }

        return response()->json($this->data);
    }

    public function show(Request $request): JsonResponse
    {
        $id = $request->id;
        if (empty($id)) {
            return response()->json([
                'status' => false,
                'message' => 'ID tidak ditemukan',
                'data' => '',
            ], 400);
        }

        $log = ActivityLog::find($id);
        if (!$log) {
            return response()->json([
                'status' => false,
                'message' => 'Data tidak ditemukan',
                'data' => '',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Data berhasil ditemukan',
            'data' => $this->transformLog($log, true),
        ]);
    }

    private function setQueryParameters(Request $request): array
    {
        $q = $request->q;
        $page = (int) ($request->page ?? 0);
        $perpage = (int) ($request->perpage ?? 10);
        $fromDate = $request->from_date;
        $untilDate = $request->until_date;
        $userId = $request->user_id;
        $modelType = $request->model_type;
        $action = $request->action;

        if ($page < 0) {
            $page = 0;
        }
        if ($perpage <= 0) {
            $perpage = 10;
        }

        return compact('q', 'page', 'perpage', 'fromDate', 'untilDate', 'userId', 'modelType', 'action');
    }

    private function transformLog(ActivityLog $log, bool $includeDetails = false): array
    {
        $oldValues = $log->old_values ?? [];
        $newValues = $log->new_values ?? [];
        $changedFields = $this->getChangedFields($oldValues, $newValues);

        $data = [
            'id' => $log->id,
            'user_id' => $log->user_id,
            'user_name' => $this->resolveUserName($log->user_id),
            'action' => $log->action,
            'event_type' => $this->detectEventType($oldValues, $newValues),
            'model_type' => $log->model_type,
            'model_name' => $this->extractModelName($log->model_type),
            'model_id' => $log->model_id,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'created_at' => $log->created_at,
            'change_summary' => [
                'changed_fields' => $changedFields,
                'changed_fields_count' => count($changedFields),
                'has_old_values' => !empty($oldValues),
                'has_new_values' => !empty($newValues),
            ],
        ];

        if ($includeDetails) {
            $data['details'] = [
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'request_payload' => $log->request_payload ?? [],
                'changed_fields' => $changedFields,
                'changed_map' => $this->buildChangedMap($oldValues, $newValues),
            ];
        }

        return $data;
    }

    private function resolveUserName($userId): string
    {
        if (empty($userId)) {
            return '';
        }

        if (!array_key_exists($userId, $this->userNameCache)) {
            $this->userNameCache[$userId] = Helpers::getNamaUser($userId) ?? '';
        }

        return $this->userNameCache[$userId];
    }

    private function extractModelName(?string $modelType): string
    {
        if (empty($modelType)) {
            return '';
        }

        $segments = explode('\\', $modelType);
        return end($segments) ?: $modelType;
    }

    private function detectEventType($oldValues, $newValues): string
    {
        $hasOld = !empty($oldValues);
        $hasNew = !empty($newValues);

        if (!$hasOld && $hasNew) {
            return 'create';
        }
        if ($hasOld && !$hasNew) {
            return 'delete';
        }
        if ($hasOld && $hasNew) {
            return 'update';
        }

        return 'unknown';
    }

    private function getChangedFields($oldValues, $newValues): array
    {
        $oldFlat = $this->flattenArray(is_array($oldValues) ? $oldValues : []);
        $newFlat = $this->flattenArray(is_array($newValues) ? $newValues : []);
        $keys = array_unique(array_merge(array_keys($oldFlat), array_keys($newFlat)));

        $changed = [];
        foreach ($keys as $key) {
            $old = $oldFlat[$key] ?? null;
            $new = $newFlat[$key] ?? null;
            if ($old !== $new) {
                $changed[] = $key;
            }
        }

        sort($changed);
        return array_values($changed);
    }

    private function buildChangedMap($oldValues, $newValues): array
    {
        $oldFlat = $this->flattenArray(is_array($oldValues) ? $oldValues : []);
        $newFlat = $this->flattenArray(is_array($newValues) ? $newValues : []);
        $keys = array_unique(array_merge(array_keys($oldFlat), array_keys($newFlat)));

        $changes = [];
        foreach ($keys as $key) {
            $old = $oldFlat[$key] ?? null;
            $new = $newFlat[$key] ?? null;
            if ($old !== $new) {
                $changes[$key] = [
                    'old' => $old,
                    'new' => $new,
                ];
            }
        }

        ksort($changes);
        return $changes;
    }

    private function flattenArray(array $data, string $prefix = ''): array
    {
        $flat = [];

        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $flat += $this->flattenArray($value, $path);
            } else {
                $flat[$path] = $value;
            }
        }

        return $flat;
    }
}
