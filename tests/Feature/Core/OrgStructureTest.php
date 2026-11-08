<?php

use App\Modules\Core\Livewire\OrgStructure;
use App\Modules\Core\Models\Module;
use App\Modules\Core\Models\OrgChart;
use App\Modules\Core\Models\OrgPosition;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Masjid\Actions\ManageScreens;
use App\Modules\Masjid\Livewire\DisplayScreens;
use App\Modules\Masjid\Support\DisplayManifest;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    (new ModuleSeeder)->run();
    (new RoleSeeder)->run();
    Storage::fake('local');
    $this->admin = tap(User::create(['name' => 'Admin', 'email' => 'a@uji.test', 'type' => 'staff']))->assignRole('admin');
});

it('dua bagan bawaan; menambah dari kotak atasan langsung tersambung; hapus menaikkan bawahan', function () {
    $this->actingAs($this->admin);
    $page = Livewire::test(OrgStructure::class)->assertSee('Struktur Yayasan')->assertSee('Pendiri');
    $founder = OrgPosition::where('title', 'Pendiri')->firstOrFail();

    $page->call('add', $founder->uuid)->assertSee('Di bawah: Pendiri')->set('title', 'Ketua Yayasan')->set('person_name', 'KH. Ahmad Dahlan')->call('save')->assertHasNoErrors();
    $chair = OrgPosition::where('title', 'Ketua Yayasan')->firstOrFail();
    $page->call('add', $chair->uuid)->set('title', 'Bendahara')->set('photo', UploadedFile::fake()->image('foto.jpg'))->call('save');
    $page->call('add', $chair->uuid)->set('title', 'Sekretaris')->call('save');

    expect($chair->parent_id)->toBe($founder->id)
        ->and(OrgPosition::where('title', 'Bendahara')->value('parent_id'))->toBe($chair->id)
        ->and($chair->initials())->toBe('AD');

    $page->call('move', OrgPosition::where('title', 'Sekretaris')->value('uuid'), -1);
    expect(OrgChart::where('key', 'yayasan')->first()->tree()[0]['children'][0]['children'][0]['position']->title)->toBe('Sekretaris');

    $page->call('remove', $chair->uuid);
    expect(OrgPosition::where('title', 'Bendahara')->value('parent_id'))->toBe($founder->id)->and(OrgPosition::where('title', 'Ketua Yayasan')->exists())->toBeFalse();

    $page->call('selectChart', 'kesantrian')->assertSee('Rois')->assertSee('Belum tampil di layar TV mana pun');
});

it('bagan dipilih per layar: layar A menampilkan kesantrian, layar B tidak; foto hanya untuk bagan yang tampil', function () {
    Module::whereIn('module_key', ['masjid_prayer', 'masjid_display'])->update(['is_enabled' => true]);
    app(ModuleRegistry::class)->forget();
    OrgChart::ensureDefaults();
    $rois = OrgPosition::where('title', 'Rois')->firstOrFail();
    $rois->update(['person_name' => 'Ust. Fulan', 'photo_path' => UploadedFile::fake()->image('r.jpg')->store('org-photos', 'local')]);
    $founder = OrgPosition::where('title', 'Pendiri')->firstOrFail();
    $founder->update(['photo_path' => UploadedFile::fake()->image('p.jpg')->store('org-photos', 'local')]);

    $screens = app(ManageScreens::class);
    $pair = fn (array $data) => $screens->claim($screens->startPairing()['code'], ['name' => 'TV', 'profile' => 'masjid', ...$data]);
    $lobby = $pair(['name' => 'TV Lobi', 'org_charts' => ['kesantrian'], 'org_mode' => 'always']);
    $masjid = $pair(['name' => 'TV Masjid']);

    $manifest = app(DisplayManifest::class)->build($lobby);
    expect($manifest['structures'])->toHaveCount(1)->and($manifest['structures'][0]['nodes'][0]['name'])->toBe('Ust. Fulan')
        ->and($manifest['screen']['org_mode'])->toBe('always')
        ->and(app(DisplayManifest::class)->build($masjid)['structures'])->toBe([]);
    $this->get(route('display.org-photo', $rois))->assertOk();
    $this->get(route('display.org-photo', $founder))->assertNotFound();

    $this->actingAs($this->admin);
    Livewire::test(OrgStructure::class)->call('selectChart', 'kesantrian')->assertSee('Tampil di: TV Lobi');
    Livewire::test(DisplayScreens::class)->call('editScreen', $masjid->uuid)->assertSet('org_charts', [])
        ->set('org_charts', ['yayasan'])->call('updateScreen')->assertHasNoErrors();
    expect(app(DisplayManifest::class)->build($masjid->fresh())['structures'][0]['key'])->toBe('yayasan');
    $this->get(route('display.org-photo', $founder))->assertOk();
});

it('hanya pengelola data induk', function () {
    $this->actingAs(tap(User::create(['name' => 'U', 'email' => 'u@uji.test', 'type' => 'staff']))->assignRole('ustadz'))
        ->get(route('staff.org.index'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('staff.org.index'))->assertOk();
});
