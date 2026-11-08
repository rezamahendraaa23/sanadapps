<?php

namespace App\Modules\Academic\Livewire;

use App\Modules\Academic\Actions\EnrollFace;
use App\Modules\Academic\Actions\ManageBiometricConsent;
use App\Modules\Academic\Face\FaceInput;
use App\Modules\Academic\Face\FaceMatcher;
use App\Modules\Academic\Models\BiometricConsent;
use App\Modules\Academic\Models\FaceEnrollment;
use App\Modules\Academic\Support\AcademicException;
use App\Modules\Core\Models\Classroom;
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

    public string $verbal_guardian = '';

    public string $verbal_note = '';

    public bool $verbal_confirm = false;

    public string $bulk_classroom = '';

    public string $bulk_note = '';

    public bool $bulk_confirm = false;

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

    /**
     * Pengenal di browser: yang dikirim hanya angka, foto tidak pernah meninggalkan kamera.
     *
     * @param  list<list<float>>  $descriptors
     */
    public function enrollDescriptors(array $descriptors, EnrollFace $action, FaceMatcher $matcher): void
    {
        $student = $this->student();
        abort_if($student === null, 403);
        abort_unless($matcher->runsInBrowser(), 403);
        $this->resetErrorBag();

        try {
            $action->handle($student, array_map(fn ($descriptor) => new FaceInput(descriptor: is_array($descriptor) ? $descriptor : []), array_values($descriptors)), auth()->id());
        } catch (AcademicException $exception) {
            $this->addError('photos', $exception->getMessage());

            return;
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

    /**
     * Persetujuan lisan satu santri: petugas menyatakan wali sudah setuju dan menulis keterangannya.
     */
    public function recordVerbal(ManageBiometricConsent $consents): void
    {
        $student = $this->student();
        abort_if($student === null, 403);
        $this->resetErrorBag();
        $this->validate(['verbal_note' => 'required|string|min:5|max:300', 'verbal_confirm' => 'accepted'], [
            'verbal_note.required' => 'Tulis kapan dan di mana wali menyatakan setuju.',
            'verbal_confirm.accepted' => 'Centang pernyataan bahwa wali sudah setuju.',
        ]);
        $guardian = $this->verbal_guardian !== '' ? $student->guardians()->where('guardians.id', (int) $this->verbal_guardian)->first() : null;

        $consents->grant($student, $guardian, 'verbal', null, request()->ip(), auth()->id(), trim($this->verbal_note));
        $this->reset('verbal_guardian', 'verbal_note', 'verbal_confirm');
        $this->dispatch('close-modal', 'persetujuan-lisan');
        $this->dispatch('toast', message: 'Persetujuan lisan tercatat.', tone: 'success');
    }

    /**
     * Persetujuan lisan sekaligus, misalnya disepakati di pertemuan wali santri. Hanya santri yang belum punya persetujuan.
     */
    public function recordVerbalBulk(ManageBiometricConsent $consents): void
    {
        $this->resetErrorBag();
        $this->validate(['bulk_note' => 'required|string|min:5|max:300', 'bulk_confirm' => 'accepted', 'bulk_classroom' => 'nullable|integer'], [
            'bulk_note.required' => 'Tulis kapan dan di mana wali menyatakan setuju.',
            'bulk_confirm.accepted' => 'Centang pernyataan bahwa wali sudah setuju.',
        ]);

        $students = Student::query()->visibleTo(auth()->user())->where('status', 'active')
            ->when($this->bulk_classroom !== '', fn ($query) => $query->where('classroom_id', (int) $this->bulk_classroom))
            ->whereNotIn('id', BiometricConsent::query()->active()->select('student_id'))->get();

        foreach ($students as $student) {
            $consents->grant($student, null, 'verbal', null, request()->ip(), auth()->id(), trim($this->bulk_note));
        }

        $this->reset('bulk_classroom', 'bulk_note', 'bulk_confirm');
        $this->dispatch('close-modal', 'persetujuan-lisan-massal');
        $this->dispatch('toast', message: $students->isEmpty() ? 'Semua santri yang dipilih sudah punya persetujuan.' : 'Persetujuan lisan tercatat untuk '.$students->count().' santri.', tone: $students->isEmpty() ? 'info' : 'success');
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
            'classrooms' => Classroom::query()->ordered()->pluck('name', 'id')->all(),
            'guardians' => $student ? $student->guardians()->get()->mapWithKeys(fn (Guardian $guardian) => [$guardian->id => $guardian->name])->all() : [],
            'activeCount' => (clone $active)->count(),
            'consentCount' => BiometricConsent::query()->active()->whereIn('student_id', (clone $active)->select('id'))->count(),
            'enrolledCount' => FaceEnrollment::query()->where('status', 'active')->count(),
            'matcherAvailable' => $matcher->available(),
            'matcherVersion' => $matcher->modelVersion(),
            'runsInBrowser' => $matcher->runsInBrowser(),
        ]);
    }
}
