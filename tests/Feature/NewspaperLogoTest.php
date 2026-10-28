<?php

namespace Tests\Feature;

use App\Models\AdminRole;
use App\Models\MediaArticle;
use App\Models\NewspaperLogo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Admin -> Media Articles -> Newspaper Logos: add, edit, hide / show and delete the "Trusted by" newspapers */
class NewspaperLogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->clippings('PAGDANDI', 10);
        $this->clippings('NOBAT', 12);
        $this->actingAs(User::forceCreate(['name' => 'Admin', 'username' => 'admin-logo', 'role' => 'admin', 'password' => Hash::make('x')]));
    }

    private function clippings(string $publication, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            MediaArticle::create(['title' => "INVESTMENT POINT / {$publication} - {$i}", 'publication' => $i ? $publication : $publication . ' /', 'published_on' => '2026-02-20',
                'image' => "https://blogger.googleusercontent.com/img/a/X{$i}/s1600/" . rawurlencode($publication) . "-{$i}.jpg", 'status' => 'active']);
        }
    }

    private static function png(): UploadedFile
    {
        return UploadedFile::fake()->image('logo.png', 600, 200);
    }

    private function names(): array
    {
        return array_column(MediaArticle::trustedBy(), 'label');
    }

    public function test_the_page_lists_the_papers_as_cards_with_add_edit_hide_and_delete(): void
    {
        $this->get(route('admin.media-articles.logos'))->assertOk()
            ->assertSee('Add newspaper')->assertSee('In Trusted by')->assertSee('NOBAT')->assertSee('PAGDANDI')
            ->assertSee('On the home page')->assertSee('Masthead')->assertSee('Edit')->assertSee('Hide')->assertSee('Delete');
    }

    public function test_a_logo_is_uploaded_replaced_and_removed(): void
    {
        $this->post(route('admin.media-articles.logos.store'), ['name' => 'Pagdandi /', 'logo' => self::png()])->assertRedirect()->assertSessionHas('success');
        $logo = NewspaperLogo::firstOrFail();
        $this->assertSame('PAGDANDI', $logo->name); // one name per paper, whatever spelling was sent
        $first = $logo->logo;
        $paper = collect(MediaArticle::trustedBy())->firstWhere('name', 'PAGDANDI');
        $this->assertTrue($paper['official']);
        // The home page shows a small copy of the logo, loaded at once (not lazily)
        $small = 'newspaper-logos/small/' . pathinfo($first, PATHINFO_FILENAME) . '.png';
        $this->get(route('home'))->assertSee(asset('storage/' . $small), false)->assertSee('trusted-mast is-official', false)
            ->assertDontSee(asset('storage/' . $first), false);
        Storage::disk('public')->assertExists($small);

        $this->put(route('admin.media-articles.logos.update'), ['name' => 'PAGDANDI', 'logo' => self::png()])->assertSessionHas('success');
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertMissing($small);

        $this->put(route('admin.media-articles.logos.update'), ['name' => 'PAGDANDI', 'remove_logo' => '1'])->assertSessionHas('success');
        $this->assertNull(NewspaperLogo::firstOrFail()->logo);
        $this->assertFalse(collect(MediaArticle::trustedBy())->firstWhere('name', 'PAGDANDI')['official']);
    }

    /** A big logo (e.g. 2,000 px, 250 KB) is shown in "Trusted by" as a 480 px copy */
    public function test_trusted_by_shows_a_small_copy_of_a_big_logo(): void
    {
        $image = imagecreatetruecolor(2000, 660);
        for ($i = 0; $i < 4000; $i++) { // noise, so the file is big like a real masthead photo
            imagefilledrectangle($image, random_int(0, 1990), random_int(0, 650), random_int(0, 1999), random_int(0, 659), random_int(0, 0xFFFFFF));
        }
        ob_start();
        imagejpeg($image, null, 95);
        Storage::disk('public')->put('newspaper-logos/nobat-AbC123.jpg', ob_get_clean());
        NewspaperLogo::create(['name' => 'NOBAT', 'logo' => 'newspaper-logos/nobat-AbC123.jpg']);

        $this->assertSame(asset('storage/newspaper-logos/small/nobat-AbC123.jpg'), collect(MediaArticle::trustedBy())->firstWhere('name', 'NOBAT')['logo']);
        $small = Storage::disk('public')->get('newspaper-logos/small/nobat-AbC123.jpg');
        $this->assertSame(NewspaperLogo::SMALL_WIDTH, getimagesizefromstring($small)[0]);
        $this->assertLessThan(strlen(Storage::disk('public')->get('newspaper-logos/nobat-AbC123.jpg')) / 4, strlen($small));
        $this->get(route('home'))->assertSee('storage/newspaper-logos/small/nobat-AbC123.jpg" alt="NOBAT newspaper" fetchpriority="low" decoding="async">', false);
    }

    public function test_name_and_position_are_edited(): void
    {
        $this->assertSame(['NOBAT', 'PAGDANDI'], $this->names()); // most articles first

        $this->put(route('admin.media-articles.logos.update'), ['name' => 'PAGDANDI', 'display_name' => 'Pagdandi Daily', 'sort_order' => 1])->assertSessionHas('success');

        $this->assertSame(['Pagdandi Daily', 'NOBAT'], $this->names());
        $this->get(route('home'))->assertSee('Pagdandi Daily');
    }

    /** A TV channel: its logo opens its YouTube playlist (new tab), only in "Trusted by" on the home page */
    public function test_a_logo_can_open_its_own_link_and_shows_only_in_trusted_by(): void
    {
        $playlist = 'https://www.youtube.com/watch?v=CdtS46t6ATI&list=PLuo6rsc7qjCB4C0595Oo226_s9mPfvb7D';
        $this->post(route('admin.media-articles.logos.store'), ['name' => 'GTPL News Jamnagar', 'display_name' => 'GTPL News Jamnagar', 'sort_order' => 1,
            'link' => 'not a link', 'logo' => self::png()])->assertSessionHasErrors('link');
        $this->post(route('admin.media-articles.logos.store'), ['name' => 'GTPL News Jamnagar', 'display_name' => 'GTPL News Jamnagar', 'sort_order' => 1,
            'link' => $playlist, 'logo' => self::png()])->assertRedirect();

        $gtpl = MediaArticle::trustedBy()[0]; // position 1
        $this->assertSame(['GTPL NEWS JAMNAGAR', $playlist], [$gtpl['name'], $gtpl['url']]);
        $this->get(route('home'))->assertOk()->assertSee('href="' . e($playlist) . '" title="GTPL News Jamnagar"  target="_blank" rel="noopener noreferrer"', false);
        $this->get(route('admin.media-articles.logos', ['show' => 'all']))->assertSee('Opens its link');

        // No "Watch on TV" box on the news pages (owner, 23 Oct 2026: only in Trusted by)
        $news = \App\Models\News::create(['title' => 'Nifty ends higher', 'content' => '<p>Text</p>', 'description' => 'Text', 'status' => 'active']);
        $this->get($news->url)->assertOk()->assertDontSee('Watch on TV')->assertDontSee(e($playlist), false);
        $this->get(route('news.index'))->assertOk()->assertDontSee('Watch on TV');

        // Link removed: the logo opens its Media Articles again
        $this->put(route('admin.media-articles.logos.update'), ['name' => 'GTPL NEWS JAMNAGAR', 'display_name' => 'GTPL News Jamnagar', 'sort_order' => 1, 'link' => ''])->assertSessionHasNoErrors();
        $this->assertNull(NewspaperLogo::where('name', 'GTPL NEWS JAMNAGAR')->value('link'));
        $this->assertSame(route('media-articles.index'), MediaArticle::trustedBy()[0]['url']);
    }

    public function test_a_paper_is_added_by_hand_and_deleted(): void
    {
        // A paper with no Media Articles needs its logo
        $this->post(route('admin.media-articles.logos.store'), ['_form' => 'add', 'name' => 'Sandesh'])->assertSessionHasErrors('logo');

        $this->post(route('admin.media-articles.logos.store'), ['name' => 'Sandesh', 'display_name' => 'Sandesh', 'logo' => self::png()])->assertRedirect();
        $this->assertContains('Sandesh', $this->names());
        $sandesh = collect(MediaArticle::trustedBy())->firstWhere('name', 'SANDESH');
        $this->assertTrue($sandesh['manual']);
        $path = NewspaperLogo::where('name', 'SANDESH')->value('logo');

        $this->delete(route('admin.media-articles.logos.destroy'), ['name' => 'SANDESH'])->assertSessionHas('success');
        $this->assertNull(NewspaperLogo::where('name', 'SANDESH')->first());
        Storage::disk('public')->assertMissing($path);
        $this->assertNotContains('Sandesh', $this->names());
    }

    public function test_a_paper_with_articles_is_hidden_by_delete_and_shown_again(): void
    {
        $this->delete(route('admin.media-articles.logos.destroy'), ['name' => 'NOBAT'])->assertSessionHas('success');
        $this->assertSame(['PAGDANDI'], $this->names());
        $this->assertSame(12, MediaArticle::where('publication', 'like', 'NOBAT%')->count()); // its articles stay
        $this->get(route('admin.media-articles.logos', ['show' => 'hidden']))->assertSee('NOBAT')->assertSee('Show again');

        $this->put(route('admin.media-articles.logos.update'), ['name' => 'NOBAT', 'is_hidden' => '0'])->assertSessionHas('success');
        $this->assertSame(['NOBAT', 'PAGDANDI'], $this->names());

        $this->put(route('admin.media-articles.logos.update'), ['name' => 'NOBAT', 'is_hidden' => '1']);
        $this->assertSame(['PAGDANDI'], $this->names());
    }

    public function test_aaj_kal_is_the_same_paper_as_aaj_kaal(): void
    {
        $this->assertSame('AAJ KAAL', MediaArticle::normalizePublication('AAJ KAL /'));
        $this->assertSame('AAJ KAAL', MediaArticle::normalizePublication('AAJ KAAL'));
    }

    public function test_only_pictures_are_accepted_and_viewers_cannot_change_anything(): void
    {
        $this->post(route('admin.media-articles.logos.store'), ['name' => 'PAGDANDI', 'logo' => UploadedFile::fake()->create('logo.svg', 5, 'image/svg+xml')])
            ->assertSessionHasErrors('logo');
        $this->assertSame(0, NewspaperLogo::count());

        $this->flushSession();
        $role = AdminRole::create(['name' => 'Viewer', 'is_active' => true]);
        $role->syncPermissions(['media-articles.view']);
        $this->actingAs(User::forceCreate(['name' => 'V', 'username' => 'viewer', 'role' => 'staff', 'admin_role_id' => $role->id, 'password' => Hash::make('x')]));
        $this->get(route('admin.media-articles.logos'))->assertOk();
        $this->put(route('admin.media-articles.logos.update'), ['name' => 'NOBAT', 'is_hidden' => '1'])->assertForbidden();
        $this->delete(route('admin.media-articles.logos.destroy'), ['name' => 'NOBAT'])->assertForbidden();
        $this->post(route('admin.media-articles.logos.store'), ['name' => 'X', 'logo' => self::png()])->assertForbidden();
    }
}
