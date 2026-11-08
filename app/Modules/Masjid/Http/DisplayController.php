<?php

namespace App\Modules\Masjid\Http;

use App\Modules\Core\Models\OrgPosition;
use App\Modules\Masjid\Actions\ManageScreens;
use App\Modules\Masjid\Models\DisplayCommand;
use App\Modules\Masjid\Models\DisplayContent;
use App\Modules\Masjid\Models\DisplayScreen;
use App\Modules\Masjid\Support\DisplayManifest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class DisplayController
{
    public function page(): View
    {
        return view('masjid.display', ['preview' => null]);
    }

    public function preview(DisplayScreen $screen, DisplayManifest $manifest): View
    {
        return view('masjid.display', ['preview' => $manifest->build($screen)]);
    }

    public function pairStart(ManageScreens $screens): JsonResponse
    {
        return response()->json($screens->startPairing());
    }

    public function pairStatus(Request $request, ManageScreens $screens): JsonResponse
    {
        return response()->json($screens->pairingStatus((string) $request->query('pairing_token')));
    }

    public function manifest(Request $request, DisplayManifest $manifest): JsonResponse
    {
        return response()->json($manifest->build($request->attributes->get('screen')));
    }

    /**
     * Ringan untuk ditanya tiap beberapa detik; sekaligus membawa perintah dan jam server.
     */
    public function version(Request $request, DisplayManifest $manifest): JsonResponse
    {
        /** @var DisplayScreen $screen */
        $screen = $request->attributes->get('screen');

        return response()->json([
            'version' => $manifest->version($screen),
            'server_time' => now()->getTimestampMs(),
            'commands' => DisplayCommand::query()->where('screen_id', $screen->id)->whereNull('acked_at')->orderBy('id')->get(['id', 'command']),
        ]);
    }

    public function heartbeat(Request $request, ManageScreens $screens): JsonResponse
    {
        $data = $request->validate(['version' => 'nullable|string|max:40', 'app_version' => 'nullable|string|max:40', 'clock_offset_ms' => 'nullable|integer']);
        $screens->heartbeat($request->attributes->get('screen'), $data);

        return response()->json(['ok' => true]);
    }

    public function ack(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => 'required|integer']);
        DisplayCommand::query()->where('screen_id', $request->attributes->get('screen')->id)->whereKey($data['id'])->update(['acked_at' => now()]);

        return response()->json(['ok' => true]);
    }

    /**
     * Foto struktur hanya untuk bagan yang dipilih tampil di salah satu layar.
     */
    public function orgPhoto(OrgPosition $position): Response
    {
        $shown = DisplayScreen::query()->where('is_active', true)->whereJsonContains('org_charts', $position->chart->key)->exists();
        abort_unless($position->photo_path && $shown && Storage::disk('local')->exists($position->photo_path), 404);

        return Storage::disk('local')->response($position->photo_path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    public function staffMedia(DisplayContent $content): Response
    {
        abort_unless($content->media_path && Storage::disk('local')->exists($content->media_path), 404);

        return Storage::disk('local')->response($content->media_path, null, ['Cache-Control' => 'private, no-store']);
    }

    public function media(Request $request, DisplayContent $content): Response
    {
        abort_unless($content->status === 'approved' && $content->media_path && Storage::disk('local')->exists($content->media_path), 404);

        return Storage::disk('local')->response($content->media_path, null, ['Cache-Control' => 'public, max-age=604800']);
    }
}
