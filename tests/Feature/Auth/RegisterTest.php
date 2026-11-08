<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'name' => '山田太郎',
            'email' => 'taro@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    public function test_名前が未入力の場合バリデーションメッセージが表示される(): void
    {
        $response = $this->post('/register', $this->validData(['name' => '']));

        $response->assertSessionHasErrors(['name' => 'お名前を入力してください']);
    }

    public function test_メールアドレスが未入力の場合バリデーションメッセージが表示される(): void
    {
        $response = $this->post('/register', $this->validData(['email' => '']));

        $response->assertSessionHasErrors(['email' => 'メールアドレスを入力してください']);
    }

    public function test_パスワードが8文字未満の場合バリデーションメッセージが表示される(): void
    {
        $response = $this->post('/register', $this->validData([
            'password' => 'pass1',
            'password_confirmation' => 'pass1',
        ]));

        $response->assertSessionHasErrors(['password' => 'パスワードは8文字以上で入力してください']);
    }

    public function test_パスワードが一致しない場合バリデーションメッセージが表示される(): void
    {
        $response = $this->post('/register', $this->validData([
            'password' => 'password123',
            'password_confirmation' => 'password456',
        ]));

        $response->assertSessionHasErrors(['password_confirmation' => 'パスワードと一致しません']);
    }

    public function test_パスワードが未入力の場合バリデーションメッセージが表示される(): void
    {
        $response = $this->post('/register', $this->validData([
            'password' => '',
            'password_confirmation' => '',
        ]));

        $response->assertSessionHasErrors(['password' => 'パスワードを入力してください']);
    }

    public function test_フォームに内容が入力されていた場合データが正常に保存される(): void
    {
        $response = $this->post('/register', $this->validData());

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('users', [
            'name' => '山田太郎',
            'email' => 'taro@example.com',
        ]);
        $this->assertTrue(User::where('email', 'taro@example.com')->exists());
    }
}
