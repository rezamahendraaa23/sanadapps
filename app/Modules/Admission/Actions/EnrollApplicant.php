<?php

namespace App\Modules\Admission\Actions;

use App\Modules\Admission\Models\Applicant;
use App\Modules\Admission\Support\AdmissionException;
use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Core\Actions\SaveGuardian;
use App\Modules\Core\Actions\SaveStudent;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Finance\Actions\CreateManualInvoice;
use App\Modules\Finance\Actions\ResolveFeeRate;
use App\Modules\Finance\Models\FeeType;
use App\Modules\Wallet\Models\Wallet;
use Illuminate\Support\Facades\DB;

class EnrollApplicant
{
    public function __construct(
        private SaveStudent $students,
        private SaveGuardian $guardians,
        private CreateManualInvoice $invoices,
        private ResolveFeeRate $rates,
        private ModuleRegistry $modules,
        private RecordAudit $audit,
    ) {}

    /**
     * Satu transaksi: santri, akun, wali, dompet, dan tagihan uang masuk (PPD-07, PRD Sistem 8.13).
     *
     * @return array{student: Student, password: string|null, fees: int}
     */
    public function handle(Applicant $applicant, ?int $classroomId, ?int $dormitoryId, int $userId): array
    {
        return DB::transaction(function () use ($applicant, $classroomId, $dormitoryId, $userId): array {
            $locked = Applicant::query()->whereKey($applicant->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'accepted') {
                throw new AdmissionException('Hanya pendaftar yang diterima yang bisa daftar ulang.');
            }

            [$student, $password] = $this->students->handle([
                'name' => $locked->name,
                'gender' => $locked->gender,
                'birth_place' => $locked->birth_place,
                'birth_date' => $locked->birth_date?->toDateString(),
                'address' => $locked->address,
                'status' => 'active',
                'classroom_id' => $classroomId,
                'dormitory_id' => $dormitoryId,
                'extra' => ['ppdb_reg_no' => $locked->reg_no, 'previous_school' => $locked->previous_school],
            ]);

            $guardian = $this->guardians->handle(array_filter(['name' => $locked->guardian_name, 'phone_wa' => $locked->guardian_phone, 'email' => $locked->guardian_email]));
            $guardian->students()->syncWithoutDetaching([$student->id => ['relation' => $locked->guardian_relation, 'is_primary' => true, 'is_payer' => true]]);

            if ($this->modules->enabled('wallet')) {
                Wallet::forStudent($student);
            }

            $fees = $this->createFees($student, $userId);
            $locked->update(['status' => 'enrolled', 'student_id' => $student->id]);
            $this->audit->handle('admission.enrolled', 'applicant', $locked->id, null, ['student_id' => $student->id]);

            return ['student' => $student, 'password' => $password, 'fees' => $fees];
        });
    }

    private function createFees(Student $student, int $userId): int
    {
        if (! $this->modules->enabled('finance')) {
            return 0;
        }

        $today = now(config('sanad.display_timezone'));
        $items = [];

        foreach (FeeType::query()->where('category', 'one_time')->where('is_active', true)->where('is_mandatory', true)->get() as $type) {
            $rate = $this->rates->handle($student, $type, $today);

            if ($rate !== null && $rate['amount'] > 0) {
                $items[] = ['description' => $type->name, 'amount' => $rate['amount'], 'fee_type_id' => $type->id];
            }
        }

        if ($items === []) {
            return 0;
        }

        $invoice = $this->invoices->handle($student, $items, $today->copy()->addDays(14)->toDateString(), $userId);
        $invoice->update(['source' => 'ppdb']);

        return (int) $invoice->total;
    }
}
