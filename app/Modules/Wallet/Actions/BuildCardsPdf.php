<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Core\Models\AcademicYear;
use App\Modules\Core\Models\SchoolProfile;
use App\Modules\Wallet\Models\StudentCard;
use App\Modules\Wallet\Support\CardFields;
use App\Support\Pdf\PdfRenderer;
use App\Support\SchoolIdentity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class BuildCardsPdf
{
    public function __construct(private PdfRenderer $renderer, private SchoolIdentity $identity) {}

    /**
     * @param  Collection<int, StudentCard>  $cards
     */
    public function handle(Collection $cards, string $format = 'sheet'): string
    {
        $profile = SchoolProfile::current();
        $logo = $profile->logo_path && Storage::disk('public')->exists($profile->logo_path) ? $profile->logo_path : null;

        return $this->renderer->render(view('wallet.pdf.cards', [
            'cards' => $cards->load('student.classroom', 'student.dormitory'),
            'profile' => $profile,
            'fields' => CardFields::selected(),
            'format' => $format === 'printer' ? 'printer' : 'sheet',
            'yearName' => AcademicYear::query()->where('is_active', true)->value('name'),
            'identity' => $this->identity,
            'logoData' => $logo ? 'data:'.Storage::disk('public')->mimeType($logo).';base64,'.base64_encode(Storage::disk('public')->get($logo)) : null,
        ])->render());
    }
}
