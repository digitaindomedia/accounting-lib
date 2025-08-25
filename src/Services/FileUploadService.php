<?php

namespace Icso\Accounting\Services;

use Icso\Accounting\Models\UserFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class FileUploadService
{

    public function upload(UploadedFile $file,$tenant, $userId)
    {
        // Check if the user has exceeded their quota limit
        $totalFileSize = UserFiles::query()->where('tenant_id', $tenant->id)->sum('size');
        $fileSize = $file->getSize();
        $quotaLimit = $tenant->quota_limit;

        if ($totalFileSize + $fileSize > $quotaLimit) {
            throw new \Exception('Kuota penyimpanan file penuh.');
        }

        // Create directory for the user if it doesn't exist
        $userDirectory = 'uploads/';
        $year = now()->format('Y');
        $month = now()->format('m');
        $userDirectory = $userDirectory. '/' . $year . '/' . $month;
        if (!Storage::exists($userDirectory)) {
            Storage::makeDirectory($userDirectory);
           // File::chmod(storage_path($userDirectory), 0777);
        }

        // Store the file in the user's directory
        $filePath = $file->store($userDirectory);

        $arrData = array(
            'user_id' => $userId,
            'filename' => $file->getClientOriginalName(),
            'path' => $filePath,
            'size' => $fileSize,
            'tenant_id' => $tenant->id,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        );
        UserFiles::create($arrData);

        return $filePath;
    }
}
