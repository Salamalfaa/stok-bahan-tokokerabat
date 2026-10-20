<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VoiceAnnouncement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VoiceAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_save_a_recording_to_private_storage(): void
    {
        Storage::fake('local');
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        $this->actingAs($operator)
            ->postJson(route('voice-announcements.store'), [
                'title' => 'Pesanan meja empat sudah siap',
                'duration_seconds' => 25,
                'audio' => UploadedFile::fake()->create('panggilan.webm', 20, 'audio/webm'),
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Rekaman berhasil disimpan.');

        $announcement = VoiceAnnouncement::query()->sole();
        $this->assertSame('Pesanan meja empat sudah siap', $announcement->title);
        $this->assertSame(25, $announcement->duration_seconds);
        Storage::disk('local')->assertExists($announcement->audio_path);
        $this->assertDatabaseHas('voice_announcements', ['is_playing' => false]);
    }

    public function test_admin_can_start_a_public_announcement_and_guests_can_receive_its_audio(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('voice-announcements/call.webm', 'audio-data');
        $announcement = VoiceAnnouncement::query()->create([
            'title' => 'Pesanan siap',
            'audio_path' => 'voice-announcements/call.webm',
            'duration_seconds' => 30,
        ]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->postJson(route('voice-announcements.broadcast', $announcement))
            ->assertOk();

        $announcement->refresh();
        $this->assertTrue($announcement->is_playing);
        $this->assertNotNull($announcement->started_at);

        $this->getJson(route('voice-announcements.public-state'))
            ->assertOk()
            ->assertJsonPath('announcement.id', $announcement->id)
            ->assertJsonPath('announcement.title', 'Pesanan siap');
        $this->get(route('voice-announcements.public-audio'))
            ->assertOk()
            ->assertHeader('cache-control', 'no-store, private');
    }

    public function test_public_state_hides_inactive_and_expired_recordings(): void
    {
        Storage::fake('local');
        $expiredAnnouncement = VoiceAnnouncement::query()->create([
            'title' => 'Panggilan kedaluwarsa',
            'audio_path' => 'voice-announcements/expired.webm',
            'duration_seconds' => 10,
            'is_playing' => true,
            'started_at' => now()->subSeconds(11),
        ]);

        $this->getJson(route('voice-announcements.public-state'))
            ->assertOk()
            ->assertExactJson(['announcement' => null]);

        $this->assertFalse($expiredAnnouncement->refresh()->is_playing);
        $this->get(route('voice-announcements.public-audio'))->assertNotFound();
    }

    public function test_only_admin_and_operator_can_manage_voice_announcements(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->get(route('voice-announcements.index'))->assertRedirectToRoute('login');
        $this->actingAs($customer)->get(route('voice-announcements.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('voice-announcements.index'))->assertForbidden();
    }

    public function test_only_active_recording_audio_is_available_to_the_public(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('voice-announcements/saved.webm', 'audio-data');
        VoiceAnnouncement::query()->create([
            'title' => 'Panggilan privat',
            'audio_path' => 'voice-announcements/saved.webm',
            'duration_seconds' => 30,
        ]);

        $this->getJson(route('voice-announcements.public-state'))
            ->assertOk()
            ->assertExactJson(['announcement' => null]);
        $this->get(route('voice-announcements.public-audio'))->assertNotFound();
    }

    public function test_staff_can_delete_a_saved_recording_and_its_audio_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('voice-announcements/remove.webm', 'audio-data');
        $announcement = VoiceAnnouncement::query()->create([
            'title' => 'Hapus saya',
            'audio_path' => 'voice-announcements/remove.webm',
            'duration_seconds' => 30,
        ]);
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        $this->actingAs($operator)
            ->deleteJson(route('voice-announcements.destroy', $announcement))
            ->assertOk();

        $this->assertModelMissing($announcement);
        Storage::disk('local')->assertMissing('voice-announcements/remove.webm');
    }
}
