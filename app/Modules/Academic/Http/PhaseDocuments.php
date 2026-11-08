<?php

namespace App\Modules\Academic\Http;

use App\Modules\Academic\Models\Curriculum;
use App\Modules\Academic\Models\CurriculumPhase;
use App\Modules\Academic\Models\PhaseResult;
use App\Modules\Academic\Support\CertificateDesigns;
use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Student;
use App\Modules\Letters\Support\LetterSettings;
use App\Support\Pdf\PdfRenderer;
use App\Support\SchoolIdentity;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rapor fase dan sertifikat satu rombongan (atau satu santri lewat ?santri=uuid), dan pratinjau desain.
 */
class PhaseDocuments
{
    public function __construct(private PdfRenderer $pdf, private SchoolIdentity $identity) {}

    public function report(Classroom $classroom, Request $request): Response
    {
        $results = $this->results($classroom, $request, false);

        return $this->download($this->pdf->render(view('academic.pdf.phase-report', [...$this->common(), 'results' => $results])->render()), 'rapor-fase-'.Str::slug($classroom->name));
    }

    public function certificate(Classroom $classroom, Request $request): Response
    {
        $results = $this->results($classroom, $request, true);
        $settings = CertificateDesigns::settings();
        $design = CertificateDesigns::key($results->first()->phase->certificate_design ?: $settings['default_design']);

        return $this->download($this->pdf->render(view('academic.certificates.layout', [...$this->common(), 'results' => $results, 'design' => $design])->render(), 'A4'), 'sertifikat-'.Str::slug($classroom->name));
    }

    /**
     * Contoh berisi data rekaan untuk galeri desain; tidak menyentuh database.
     */
    public function preview(string $design): View
    {
        abort_unless(isset(CertificateDesigns::ALL[$design]), 404);
        $phase = new CurriculumPhase(['name' => 'Masa Perkenalan', 'code' => 'PRK', 'min_score' => 70, 'certificate_title' => 'Sertifikat Kelulusan Masa Perkenalan']);
        $phase->setRelation('curriculum', new Curriculum(['name' => 'Program Tahfidz']));
        $result = new PhaseResult(['started_on' => '2026-08-01', 'ended_on' => '2026-10-23', 'final_score' => 86.5, 'status' => 'passed', 'certificate_no' => '001/SRT/PRK/2026']);
        $result->setRelation('student', new Student(['name' => 'Muhammad Abdurrahman Al-Fatih', 'nis' => '20260001']));
        $result->setRelation('phase', $phase);

        return view('academic.certificates.layout', [...$this->common(), 'results' => collect([$result]), 'design' => $design, 'embed' => false]);
    }

    public function logo(int $index): Response
    {
        $path = CertificateDesigns::settings()['logos'][$index] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    /**
     * @return Collection<int, PhaseResult>
     */
    private function results(Classroom $classroom, Request $request, bool $certified): Collection
    {
        abort_if($classroom->phase_id === null, 404);

        $results = PhaseResult::query()->with(['student', 'classroom', 'phase.curriculum'])
            ->where('classroom_id', $classroom->id)->where('phase_id', $classroom->phase_id)->where('started_on', $classroom->phase_started_on)
            ->when($certified, fn ($query) => $query->whereNotNull('certificate_no'))
            ->when(Str::isUuid((string) $request->query('santri')), fn ($query) => $query->whereHas('student', fn ($student) => $student->where('uuid', $request->query('santri'))))
            ->get()->sortBy('student.name')->values();

        abort_if($results->isEmpty(), 404);

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    private function common(): array
    {
        $letters = LetterSettings::all();
        $logos = CertificateDesigns::logoData();

        return [
            'identity' => $this->identity,
            'logos' => $logos,
            'logo' => $logos[0] ?? null,
            'arabicName' => CertificateDesigns::settings()['school_name_ar'],
            'signerName' => $letters['signer_name'],
            'signerTitle' => $letters['signer_title'],
            'city' => $letters['city'],
        ];
    }

    private function download(string $pdf, string $name): Response
    {
        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$name.'.pdf"']);
    }
}
