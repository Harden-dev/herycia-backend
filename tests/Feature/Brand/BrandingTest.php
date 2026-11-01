<?php

namespace Tests\Feature\Brand;

use App\Mail\User\WelcomeMail;
use App\Models\User;
use App\Support\MailLogo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Le produit s'appelle MANYAN : e-mails, logo et messages visibles des clients. */
class BrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_mail_renders_with_manyan_name_and_logo(): void
    {
        config(['app.name' => 'MANYAN', 'app.url' => 'https://api.exemple.ci', 'mail.logo_url' => null]);
        $user = User::factory()->create(['name' => 'Awa']);

        $mail = new WelcomeMail($user);
        $html = $mail->render();

        $this->assertSame('Bienvenue sur MANYAN', $mail->envelope()->subject);
        $this->assertStringContainsString('MANYAN', $html);
        $this->assertStringContainsString('https://api.exemple.ci/logo.png', $html);
        $this->assertStringNotContainsStringIgnoringCase('salono', $html);
    }

    public function test_mail_logo_prefers_configured_url(): void
    {
        config(['app.url' => 'https://api.exemple.ci', 'mail.logo_url' => 'https://cdn.exemple.ci/manyan.png']);

        $this->assertSame('https://cdn.exemple.ci/manyan.png', MailLogo::url());
    }
}
