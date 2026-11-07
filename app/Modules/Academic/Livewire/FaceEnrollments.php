<?php

namespace App\Modules\Academic\Livewire;

use App\Modules\Academic\Actions\EnrollFace;
use App\Modules\Academic\Actions\ManageBiometricConsent;
use App\Modules\Academic\Face\FaceMatcher;
use App\Modules\Academic\Models\BiometricConsent;
use App\Modules\Academic\Models\FaceEnrollment;
use App\Modules\Academic\Support\AcademicException;
use App\Modules\Core\Models\Guardian;
use App\Modules\Core\Models\Student;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Pendaftaran wajah (U-15). Foto hanya sementara untuk menghasilkan embedding, lalu dihapus.
 */
#[Layout('components.layouts.staff', ['active' => 'face'])]
#[Title('Pendaftaran Wajah')]
class FaceEnrollments extends Component
{
    use WithFileUploads;

    public string $search = '';

    #[Url(as: 'santri', except: '')]
    public string $studentUuid = '';

    /** @var array<int, mixed> */
    public array $photos = [];

    public string $paper_guardian = '';

    public $paper_document = null;

    private function student(): ?Student
    {
        return Str::isUuid($this->studentUuid) ? Student::query()->visibleTo(auth()->user())->where('uuid', $this->studentUuid)->first() : null;
    }

    public function choose(string $uuid): void
    {
        $this->studentUuid = $uuid;
        $this->reset('search', 'photos');
        $this->resetErrorBag();
    }

    public function enroll(EnrollFace $action): void
    {
        $student = $this->student();
        abort_if($student === null, 403);
        $this->resetErrorBag();

        $this->validate(['photos' => 'array', 'photos.*' => 'image|max:8192'], ['photos.*.image' => 'Berkas harus berupa foto.']);

        try {
            $action->handle($student, array_map(fn ($photo) => (string) file_get_contents($photo->getRealPath()), $this->photos), auth()->id());
        } catch (AcademicException $exception) {
            $this->addError('photos', $exception->getMessage());

            return;
        } finally {
            foreach ($this->photos as $photo) {
                $photo->delete();
            }

            $this->photos = [];
        }

        $this->dispatch('toast', message: 'Wajah terdaftar. Fotonya tidak disimpan.', tone: 'success');
    }

    public function erase(ManageBiometricConsent $consents): void
    {
        $student = $this->student();
        abort_if($student === null, 403);
        $consents->eraseEnrollment($student);
        $this->dispatch('toast', message: 'Data wajah dihapus.', tone: 'success');
    }

    public function requestConsent(ManageBiometricConsent $consents): void
    {
        $student = $this->student();
        abort_if($student === null, 403);
        $count = $consents->requestFromGuardians($student);
        $this->dispatch('toast', message: $count > 0 ? "Permintaan terkirim ke {$count} wali." : 'Wali santri ini belum punya akun.', tone: $count > 0 ? 'success' : 'warning');
    }

    public function recordPaper(ManageBiometricConsent $consents): void
    {
        $student = $this->student();
        abort_if($student === null, 403);
        $this->resetErrorBag();
        $this->validate(['paper_guardian' => 'required', 'paper_document' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120'], ['paper_document.required' => 'Unggah scan surat persetujuan.', 'paper_guardian.required' => 'Pilih wali yang menandatangani.']);

        $guardian = $student->guardians()->where('guardians.id', (int) $this->paper_guardian)->first();

        if ($guardian === null) {
            $this->addError('paper_guardian', 'Pilih wali santri ini.');

            return;
        }

        $consents->grant($student, $guardian, 'paper', $this->paper_document->store('biometric-consents', 'local'), request()->ip(), auth()->id());
        $this->reset('paper_guardian', 'paper_document');
        $this->dispatch('close-modal', 'persetujuan-kertas');
        $this->dispatch('toast', message: 'Persetujuan kertas tercatat.', tone: 'success');
    }

    public function render(FaceMatcher $matcher): View
    {
        $student = $this->student();
        $term = trim($this->search);
        $active = Student::query()->where('status', 'active');

        return view('academic.face-enrollments', [
            'student' => $student,
            'results' => mb_strlen($term) >= 2 ? Student::query()->visibleTo(auth()->user())->where('status', 'active')->where(fn ($query) => $query->where('name', 'ilike', '%'.addcslashes($term, '%_').'%')->orWhere('nis', 'like', addcslashes($term, '%_').'%'))->orderBy('name')->limit(8)->get() : collect(),
            'consent' => $student ? BiometricConsent::query()->active()->where('student_id', $student->id)->first() : null,
            'enrollment' => $student ? FaceEnrollment::query()->where('student_id', $student->id)->where('status', 'active')->first() : null,
            'guardians' => $student ? $student->guardians()->get()->mapWithKeys(fn (Guardian $guardian) => [$guardian->id => $guardian->name])->all() : [],
            'activeCount' => (clone $active)->count(),
            'consentCount' => BiometricConsent::query()->active()->whereIn('student_id', (clone $active)->select('id'))->count(),
            'enrolledCount' => FaceEnrollment::query()->where('status', 'active')->count(),
            'matcherAvailable' => $matcher->available(),
            'matcherVersion' => $matcher->modelVersion(),
        ]);
    }
}
