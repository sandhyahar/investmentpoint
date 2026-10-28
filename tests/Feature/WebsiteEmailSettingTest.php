<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\MailAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class WebsiteEmailSettingTest extends TestCase
{
    use RefreshDatabase;

    /** Gmail stand-in: accepts only this App Password */
    private const GOOD = 'abcdefghijklmnop';

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(MailAccount::class, new class extends MailAccount {
            public array $tried = [];

            public function check(string $password): void
            {
                $this->tried[] = $password;
                if ($password !== 'abcdefghijklmnop') {
                    throw new RuntimeException('Gmail did not accept this App Password.');
                }
            }
        });
        config(['mail.mailers.smtp.username' => 'shreeinvestmentpoint@gmail.com', 'mail.mailers.smtp.password' => 'old-env-password',
            'mail.from.address' => 'shreeinvestmentpoint@gmail.com']);
        $this->actingAs(User::forceCreate(['name' => 'Administrator', 'username' => 'admin-mail', 'email' => 'admin-mail@example.com',
            'role' => 'admin', 'password' => bcrypt('secret')]));
    }

    public function test_the_app_password_is_checked_on_gmail_saved_encrypted_and_used_for_emails(): void
    {
        $this->get(route('admin.settings.edit'))->assertOk()->assertSee('Website Email (Gmail)')->assertSee('shreeinvestmentpoint@gmail.com')
            ->assertSee('data-toggle-password="mail_password"', false); // eye button: show what was pasted

        // Typed as Google shows it, with spaces
        $this->post(route('admin.settings.mail'), ['mail_password' => 'abcd efgh ijkl mnop'])
            ->assertRedirect(route('admin.settings.edit') . '#website-email')
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'accepted by Gmail') && str_contains($m, 'test email was sent to shreeinvestmentpoint@gmail.com'));

        $this->assertSame([self::GOOD], app(MailAccount::class)->tried);
        $this->assertSame(self::GOOD, Setting::mailPassword());
        $this->assertNotSame(self::GOOD, Setting::get('mail_password')); // stored encrypted
        $this->assertSame(self::GOOD, config('mail.mailers.smtp.password'));
        $this->assertCount(1, app('mailer')->getSymfonyTransport()->messages()); // the test email

        // Next request / command: the saved password replaces the .env one
        config(['mail.mailers.smtp.password' => 'old-env-password']);
        Setting::applyMailPassword();
        $this->assertSame(self::GOOD, config('mail.mailers.smtp.password'));
        $this->get(route('admin.settings.edit'))->assertSee('An App Password is saved here')->assertDontSee(self::GOOD);
    }

    public function test_a_wrong_or_badly_typed_password_is_not_saved(): void
    {
        $this->post(route('admin.settings.mail'), ['mail_password' => 'my normal gmail password1'])->assertSessionHasErrors('mail_password');
        $this->post(route('admin.settings.mail'), ['mail_password' => 'zzzz zzzz zzzz zzzz'])
            ->assertSessionHasErrors(['mail_password' => 'Not saved: Gmail did not accept this App Password.']);

        $this->assertNull(Setting::mailPassword());
        $this->assertSame('old-env-password', config('mail.mailers.smtp.password'));
    }

    public function test_removing_it_goes_back_to_the_env_password(): void
    {
        $this->post(route('admin.settings.mail'), ['mail_password' => self::GOOD]);
        $this->post(route('admin.settings.mail'), ['remove' => 1])->assertSessionHas('success');

        $this->assertNull(Setting::mailPassword());
        $this->get(route('admin.settings.edit'))->assertDontSee('An App Password is saved here');
    }
}
