<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_会員登録後、認証メールが送信される(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'テスト太郎',
            'email' => 'verify@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'verify@example.com')->first();

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_メール認証誘導画面に、メール認証サイトへのリンクが表示される(): void
    {
        $user = $this->createUser(['email_verified_at' => null]);

        $response = $this->actingAs($user)->get('/email/verify');

        $response->assertStatus(200);
        $response->assertSee('認証はこちらから');
        $response->assertSee('http://localhost:8025');
    }

    public function test_メール認証を完了すると、勤怠登録画面に遷移する(): void
    {
        $user = $this->createUser(['email_verified_at' => null]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        $response->assertRedirect('/attendance?verified=1');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }
}
