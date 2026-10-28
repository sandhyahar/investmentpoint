<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::forceCreate([
            'name' => 'Administrator', 'username' => 'admin-media', 'email' => 'admin-media@example.com',
            'role' => 'admin', 'password' => bcrypt('secret'),
        ]);
    }

    public function test_pictures_are_uploaded_listed_renamed_and_deleted(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson(route('admin.media.store'), ['files' => [UploadedFile::fake()->image('Adani Ports.jpg', 1200, 675)]])
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Adani Ports')
            ->assertJsonPath('data.0.width', 1200);

        $media = Media::sole();
        Storage::disk('public')->assertExists($media->path);
        $this->assertStringStartsWith('media/', $media->path);

        // The picker's list (JSON) and its search
        $this->actingAs($admin)->getJson(route('admin.media.index', ['q' => 'adani']))
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.url', $media->url);
        $this->actingAs($admin)->getJson(route('admin.media.index', ['q' => 'gold']))->assertJsonPath('total', 0);

        // The Media Library page, and the picker on an image field
        $this->actingAs($admin)->get(route('admin.media.index'))->assertOk()->assertSee('Adani Ports')->assertSee('Drop pictures here to upload');
        $this->actingAs($admin)->get(route('admin.news.create'))->assertOk()->assertSee('id="mediaPicker"', false);
        // Dashboard quick button (in place of News Inbox)
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('href="' . route('admin.media.index') . '" class="db-qa"', false);

        $this->actingAs($admin)->putJson(route('admin.media.update', $media), ['name' => 'Adani port at sunset'])->assertOk();
        $this->assertSame('Adani port at sunset', $media->fresh()->name);

        $this->actingAs($admin)->deleteJson(route('admin.media.destroy', $media))->assertOk();
        $this->assertDatabaseCount('media', 0);
        Storage::disk('public')->assertMissing($media->path);
    }

    public function test_a_name_already_in_the_library_is_not_uploaded_again(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $this->actingAs($admin)->postJson(route('admin.media.store'), ['files' => [UploadedFile::fake()->image('ZYDUSLIFE - Zydus Life.jpg')]])->assertOk();
        $first = Media::sole();

        // Same name (other capitals too): refused, and the answer names the picture that is there
        $this->actingAs($admin)->postJson(route('admin.media.store'), ['files' => [UploadedFile::fake()->image('zyduslife - zydus life.png')]])
            ->assertStatus(422)
            ->assertJsonPath('duplicate.id', $first->id)
            ->assertJsonPath('duplicate.name', 'ZYDUSLIFE - Zydus Life')
            ->assertJsonValidationErrors('files.0');
        // Twice in one upload: refused too
        $this->actingAs($admin)->postJson(route('admin.media.store'), ['files' => [UploadedFile::fake()->image('SBIN.jpg'), UploadedFile::fake()->image('SBIN.jpg')]])
            ->assertStatus(422);
        $this->assertDatabaseCount('media', 1);

        // Renaming to a name another picture has: refused; its own name (other capitals): fine
        $this->actingAs($admin)->postJson(route('admin.media.store'), ['files' => [UploadedFile::fake()->image('SBIN.jpg')]])->assertOk();
        $sbi = Media::where('name', 'SBIN')->sole();
        $this->actingAs($admin)->putJson(route('admin.media.update', $sbi), ['name' => 'ZYDUSLIFE - Zydus Life'])->assertStatus(422);
        $this->actingAs($admin)->putJson(route('admin.media.update', $sbi), ['name' => 'sbin'])->assertOk();
    }

    public function test_the_list_can_be_sorted_by_name_or_date(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        foreach (['Bharti Airtel', 'adani Ports', 'Coal India'] as $name) {
            $this->actingAs($admin)->postJson(route('admin.media.store'), ['files' => [UploadedFile::fake()->image("$name.jpg")]])->assertOk();
        }
        $names = fn (string $sort) => collect($this->actingAs($admin)->getJson(route('admin.media.index', ['sort' => $sort]))->json('data'))->pluck('name')->all();

        $this->assertSame(['Coal India', 'adani Ports', 'Bharti Airtel'], $names('newest'));
        $this->assertSame(['Bharti Airtel', 'adani Ports', 'Coal India'], $names('oldest'));
        $this->assertSame(['adani Ports', 'Bharti Airtel', 'Coal India'], $names('az')); // capitals don't count
        $this->assertSame(['Coal India', 'Bharti Airtel', 'adani Ports'], $names('za'));
        $this->assertSame(['Coal India', 'adani Ports', 'Bharti Airtel'], $names('anything')); // unknown: newest
        $this->actingAs($admin)->get(route('admin.media.index', ['sort' => 'az']))->assertOk()
            ->assertSee('<option value="az" selected>', false)->assertSee('Name A → Z');
    }

    public function test_only_pictures_can_be_uploaded(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
            ->postJson(route('admin.media.store'), ['files' => [UploadedFile::fake()->create('report.pdf', 100, 'application/pdf')]])
            ->assertStatus(422);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_bulk_delete_and_guests_are_kept_out(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $this->actingAs($admin)->postJson(route('admin.media.store'), ['files' => [UploadedFile::fake()->image('a.png'), UploadedFile::fake()->image('b.png')]])->assertOk();

        $this->actingAs($admin)->post(route('admin.media.bulk-delete'), ['ids' => Media::pluck('id')->all()])->assertRedirect();
        $this->assertDatabaseCount('media', 0);

        auth()->logout();
        $this->get(route('admin.media.index'))->assertRedirect();
    }
}
