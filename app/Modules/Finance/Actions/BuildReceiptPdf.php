<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Core\Models\SchoolProfile;
use App\Modules\Finance\Models\Payment;
use App\Support\Pdf\PdfRenderer;
use App\Support\SchoolIdentity;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Storage;

class BuildReceiptPdf
{
    public function __construct(private PdfRenderer $renderer, private SchoolIdentity $identity) {}

    public function handle(Payment $payment): string
    {
        if (! in_array($payment->status, ['settlement', 'refunded'], true)) {
            throw new FinanceException('Kwitansi tersedia setelah pembayaran lunas.');
        }

        $payment->load(['invoices.items', 'invoices.student', 'topup.wallet.student']);
        $profile = SchoolProfile::current();
        $logo = $profile->logo_path && Storage::disk('public')->exists($profile->logo_path) ? $profile->logo_path : null;

        return $this->renderer->render(view('finance.pdf.receipt', [
            'payment' => $payment,
            'profile' => $profile,
            'identity' => $this->identity,
            'students' => $payment->studentNames(),
            'logoData' => $logo ? 'data:'.Storage::disk('public')->mimeType($logo).';base64,'.base64_encode(Storage::disk('public')->get($logo)) : null,
            'qr' => (new Writer(new ImageRenderer(new RendererStyle(96, 0), new SvgImageBackEnd)))->writeString(route('receipts.verify', $payment)),
        ])->render(), 'A5');
    }
}
