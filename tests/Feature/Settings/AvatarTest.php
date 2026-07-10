<?php

use App\Livewire\Settings\Profile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('a user can upload an avatar (stored on the private disk)', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Profile::class)
        ->set('avatar', UploadedFile::fake()->image('me.png', 128, 128))
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->avatar_path)->not->toBeNull()
        ->and($user->avatarUrl())->toContain('/media/avatars/');

    Storage::disk('local')->assertExists($user->avatar_path);
});

test('uploading a new avatar deletes the previous file', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Profile::class)
        ->set('avatar', UploadedFile::fake()->image('first.png'));
    $first = $user->refresh()->avatar_path;

    Livewire::actingAs($user)->test(Profile::class)
        ->set('avatar', UploadedFile::fake()->image('second.png'));
    $second = $user->refresh()->avatar_path;

    expect($second)->not->toBe($first);
    Storage::disk('local')->assertMissing($first);
    Storage::disk('local')->assertExists($second);
});

test('a non-image upload is rejected and nothing is stored', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Profile::class)
        ->set('avatar', UploadedFile::fake()->create('resume.pdf', 200))
        ->assertHasErrors('avatar');

    expect($user->refresh()->avatar_path)->toBeNull();
});

test('a user can remove their avatar', function () {
    Storage::fake('local');
    $user = User::factory()->create(['avatar_path' => 'avatars/keep.png']);
    Storage::disk('local')->put('avatars/keep.png', 'x');

    Livewire::actingAs($user)->test(Profile::class)->call('removeAvatar');

    expect($user->refresh()->avatar_path)->toBeNull();
    Storage::disk('local')->assertMissing('avatars/keep.png');
});

test('the user-avatar component renders an image when set, initials otherwise', function () {
    $withAvatar = User::factory()->create(['name' => 'Zoe', 'avatar_path' => 'avatars/z.png']);
    $without = User::factory()->make(['name' => 'Alan', 'avatar_path' => null]);

    $img = Blade::render('<x-user-avatar :user="$u" />', ['u' => $withAvatar]);
    $initials = Blade::render('<x-user-avatar :user="$u" />', ['u' => $without]);

    expect($img)->toContain('<img')->toContain('/media/avatars/')
        ->and($initials)->not->toContain('<img')
        ->and($initials)->toContain('>A<'); // first letter of "Alan"
});
