<?php

namespace App\Modules\Admission\Actions;

use App\Modules\Admission\Models\AdmissionPeriod;
use App\Modules\Admission\Models\Applicant;
use App\Modules\Admission\Models\ApplicantDocument;
use App\Modules\Admission\Support\AdmissionException;
use App\Modules\Finance\Actions\CompressProofImage;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\InvoiceItem;
use App\Modules\Finance\Support\DocumentNumber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RegisterApplicant
{
    public function __construct(private CompressProofImage $images, private AdmissionFlow $flow) {}

    /**
     * Nomor yang sama melanjutkan drafnya, tidak membuat pendaftar baru.
     */
    public function start(AdmissionPeriod $period, string $phone): Applicant
    {
        return DB::transaction(function () use ($period, $phone): Applicant {
            return Applicant::query()->where('period_id', $period->id)->where('guardian_phone', $phone)->where('status', 'draft')->first()
                ?? Applicant::create(['period_id' => $period->id, 'guardian_phone' => $phone, 'reg_no' => DocumentNumber::next('PPDB')]);
        });
    }

    public function storeDocument(Applicant $applicant, string $type, UploadedFile $file): ApplicantDocument
    {
        if (! isset(Applicant::DOCUMENTS[$type])) {
            throw new AdmissionException('Jenis dokumen tidak dikenal.');
        }

        if ($applicant->status !== 'draft') {
            throw new AdmissionException('Pendaftaran sudah dikirim, dokumen tidak bisa diganti.');
        }

        $existing = $applicant->documents()->where('type', $type)->first();

        if ($existing !== null) {
            Storage::disk('local')->delete($existing->file_path);
        }

        return ApplicantDocument::query()->updateOrCreate(
            ['applicant_id' => $applicant->id, 'type' => $type],
            ['file_path' => $this->images->store($file, 'applicant-documents'), 'status' => 'pending', 'note' => null],
        );
    }

    /**
     * @return list<string> Kekurangan yang menghalangi pengiriman.
     */
    public function problems(Applicant $applicant): array
    {
        $problems = [];

        foreach (['name' => 'Nama calon santri', 'gender' => 'Jenis kelamin', 'birth_date' => 'Tanggal lahir', 'address' => 'Alamat', 'guardian_name' => 'Nama wali'] as $field => $label) {
            if (blank($applicant->{$field})) {
                $problems[] = $label.' belum diisi.';
            }
        }

        $uploaded = $applicant->documents()->pluck('type')->all();

        foreach (Applicant::REQUIRED_DOCUMENTS as $type) {
            if (! in_array($type, $uploaded, true)) {
                $problems[] = Applicant::DOCUMENTS[$type].' belum diunggah.';
            }
        }

        return $problems;
    }

    /**
     * Mengirim pendaftaran dan membuat tagihan biaya daftar (PPD-04). Tanpa biaya langsung berstatus sudah bayar.
     */
    public function submit(Applicant $applicant, bool $consent): Applicant
    {
        if (! $consent) {
            throw new AdmissionException('Centang persetujuan pengolahan data dulu.');
        }

        if (($problems = $this->problems($applicant)) !== []) {
            throw new AdmissionException(implode(' ', $problems));
        }

        $paid = false;

        $applicant = DB::transaction(function () use ($applicant, &$paid): Applicant {
            $locked = Applicant::query()->whereKey($applicant->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'draft') {
                return $locked;
            }

            $period = $locked->period;

            if (! $period->isAccepting()) {
                throw new AdmissionException('Pendaftaran periode ini sudah ditutup.');
            }

            $paid = $period->registration_fee <= 0;
            $locked->update(['status' => $paid ? 'paid' : 'submitted', 'submitted_at' => now(), 'consent_at' => now()]);

            if (! $paid) {
                $invoice = Invoice::create([
                    'invoice_no' => DocumentNumber::next('INV'),
                    'applicant_id' => $locked->id,
                    'due_date' => $period->close_at->setTimezone(config('sanad.display_timezone'))->toDateString(),
                    'source' => 'ppdb',
                ]);
                InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => 'Biaya pendaftaran '.$period->name, 'qty' => 1, 'unit_price' => $period->registration_fee, 'amount' => $period->registration_fee]);
                $invoice->refreshTotals();
                $invoice->save();
            }

            return $locked;
        });

        $this->flow->notify($applicant, $paid ? 'Pendaftaran diterima, dokumen sedang diperiksa.' : 'Silakan bayar biaya pendaftaran di halaman cek status.');

        return $applicant;
    }
}
