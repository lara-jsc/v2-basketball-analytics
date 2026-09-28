<?php

use App\Enums\UserRole;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Support\Facades\Hash;

it('seeds the demo admin with the configured password', function () {
    config()->set('demo.password', 's3cret-from-env');

    $this->seed(DemoUserSeeder::class);

    $user = User::query()->where('email', 'test@email.com')->firstOrFail();

    expect(Hash::check('s3cret-from-env', $user->password))->toBeTrue();
});

it('seeds each team coach with the configured password', function () {
    config()->set('demo.password', 'coach-pass');
    Team::factory()->create(['code' => 'GSW']);
    Team::factory()->create(['code' => 'LAK']);

    $this->seed(DemoUserSeeder::class);

    foreach (['warriors@email.com', 'lakers@email.com'] as $email) {
        $coach = User::query()->where('email', $email)->firstOrFail();
        expect(Hash::check('coach-pass', $coach->password))->toBeTrue();
    }
});

it('defaults to password123 when DEMO_PASSWORD is unset', function () {
    $this->seed(DemoUserSeeder::class);

    $user = User::query()->where('email', 'test@email.com')->firstOrFail();

    expect(Hash::check('password123', $user->password))->toBeTrue();
});

it('seeds the demo admin with the admin role and team coaches as coaches', function () {
    Team::factory()->create(['code' => 'GSW']);

    $this->seed(DemoUserSeeder::class);

    expect(User::query()->where('email', 'test@email.com')->firstOrFail()->role)->toBe(UserRole::Admin)
        ->and(User::query()->where('email', 'warriors@email.com')->firstOrFail()->role)->toBe(UserRole::Coach);
});
