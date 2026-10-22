<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthCaseInsensitiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_user_can_login_with_any_case_variation(): void
    {
        $uniqueId = uniqid();
        $accountUpper = "AGENT.TEST.{$uniqueId}";
        $emailUpper = "{$accountUpper}@TS.INTERNAL";
        $nameUpper = "AGENT TEST {$uniqueId}";
        $password = 'Password123!';

        $user = User::create([
            'name' => $nameUpper,
            'email' => $emailUpper,
            'password' => Hash::make($password),
        ]);
        $user->assignRole('Agent');

        // 1. Lowercase account without domain
        $res1 = $this->postJson('/api/login', [
            'email' => strtolower($accountUpper),
            'password' => $password,
        ]);
        $res1->assertStatus(200);
        $this->assertEquals($user->id, $res1->json('user.id'));

        // 2. Lowercase full email with domain
        $res2 = $this->postJson('/api/login', [
            'email' => strtolower($emailUpper),
            'password' => $password,
        ]);
        $res2->assertStatus(200);
        $this->assertEquals($user->id, $res2->json('user.id'));

        // 3. Mixed case
        $res3 = $this->postJson('/api/login', [
            'email' => ucfirst(strtolower($accountUpper)),
            'password' => $password,
        ]);
        $res3->assertStatus(200);
        $this->assertEquals($user->id, $res3->json('user.id'));

        // 4. Uppercase account
        $res4 = $this->postJson('/api/login', [
            'email' => $accountUpper,
            'password' => $password,
        ]);
        $res4->assertStatus(200);
        $this->assertEquals($user->id, $res4->json('user.id'));

        // 5. Name in lowercase
        $res5 = $this->postJson('/api/login', [
            'email' => strtolower($nameUpper),
            'password' => $password,
        ]);
        $res5->assertStatus(200);
        $this->assertEquals($user->id, $res5->json('user.id'));

        // 6. Wrong password fails
        $resFail = $this->postJson('/api/login', [
            'email' => strtolower($accountUpper),
            'password' => 'WrongPassword',
        ]);
        $resFail->assertStatus(422);
    }
}
