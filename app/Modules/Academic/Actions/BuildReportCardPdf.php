<?php

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Models\ReportCard;
use App\Modules\Academic\Support\AttendanceStats;
use App\Modules\Core\Models\SchoolProfile;
use App\Support\Pdf\PdfRenderer;
use App\Support\SchoolIdentity;
use Illuminate\Support\Facades\Storage;

class BuildReportCardPdf
{
    public function __construct(private PdfRenderer $renderer, private SchoolIdentity $identity) {}

    public function handle(ReportCard $card): string
    {
        $card->load(['items.subject', 'student', 'term.academicYear', 'classroom.homeroomTeacher']);
        $profile = SchoolProfile::current();
        $logo = $profile->logo_path && Storage::disk('public')->exists($profile->logo_path) ? $profile->logo_path : null;
        $attendance = AttendanceStats::perStudent(null, null, $card->term->start_date->toDateString(), $card->term->end_date->toDateString(), [$card->student_id])->first();

        return $this->renderer->render(view('academic.pdf.report-card', [
            'card' => $card,
            'profile' => $profile,
            'identity' => $this->identity,
            'attendance' => $attendance,
            'logoData' => $logo ? 'data:'.Storage::disk('public')->mimeType($logo).';base64,'.base64_encode(Storage::disk('public')->get($logo)) : null,
        ])->render());
    }
}
