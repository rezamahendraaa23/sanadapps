<?php

namespace App\Modules\Admission\Http;

use App\Modules\Admission\Models\Applicant;
use App\Modules\Admission\Models\ApplicantDocument;
use App\Modules\Core\Actions\RecordAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ApplicantFileController
{
    /**
     * Pendaftar hanya dokumennya sendiri lewat sesi OTP.
     */
    public function own(Request $request, ApplicantDocument $document): Response
    {
        abort_unless(Applicant::current()?->id === $document->applicant_id, 403);

        return $this->send($document);
    }

    public function staff(Request $request, ApplicantDocument $document, RecordAudit $audit): Response
    {
        abort_unless($request->user()->can('ppdb.view'), 403);
        $audit->handle('admission.document_viewed', 'applicant_document', $document->id);

        return $this->send($document);
    }

    private function send(ApplicantDocument $document): Response
    {
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->response($document->file_path, null, ['Cache-Control' => 'private, no-store']);
    }
}
