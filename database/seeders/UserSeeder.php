<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => '山田 太郎',
            'email' => 'user1@example.com',
        ]);

        User::factory()->create([
            'name' => '佐藤 花子',
            'email' => 'user2@example.com',
        ]);

        User::factory()->create([
            'name' => '管理者 一郎',
            'email' => 'user3@example.com',
            'admin_status' => true,
        ]);
    }
}
