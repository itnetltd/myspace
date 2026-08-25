<?php

namespace App\Http\Controllers;

use App\Models\WorkOrderEvidence;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkOrderEvidenceController extends Controller
{
    use AuthorizesRequests;

    public function show(WorkOrderEvidence $evidence): StreamedResponse
    {
        $this->authorize('download', $evidence);
        abort_if(blank($evidence->file_path) || ! Storage::disk('local')->exists($evidence->file_path), 404);

        return Storage::disk('local')->download($evidence->file_path);
    }
}
