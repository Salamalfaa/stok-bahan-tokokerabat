<?php

namespace Tests\Feature;

use App\Models\Pesanan;
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

    public function test_staff_can_save_audio_recorded_in_a_webm_container(): void
    {
        Storage::fake('local');
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        $this->actingAs($operator)
            ->postJson(route('voice-announcements.store'), [
                'title' => 'Pesanan dari rekaman WebM',
                'duration_seconds' => 6,
                'audio' => UploadedFile::fake()->create('panggilan.webm', 20, 'video/webm'),
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Rekaman berhasil disimpan.');

        $announcement = VoiceAnnouncement::query()->sole();
        $this->assertSame('Pesanan dari rekaman WebM', $announcement->title);
        $this->assertSame(6, $announcement->duration_seconds);
        Storage::disk('local')->assertExists($announcement->audio_path);

        $this->get(route('voice-announcements.index'))
            ->assertOk()
            ->assertSee('Pesanan dari rekaman WebM')
            ->assertSee('1 rekaman');
    }

    public function test_staff_are_told_when_an_unsupported_recording_format_is_uploaded(): void
    {
        Storage::fake('local');
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        $this->actingAs($operator)
            ->postJson(route('voice-announcements.store'), [
                'title' => 'Format tidak didukung',
                'duration_seconds' => 6,
                'audio' => UploadedFile::fake()->create('panggilan.txt', 20, 'text/plain'),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.audio.0', 'Format rekaman tidak didukung. Gunakan rekaman mikrofon WebM, Ogg, MP4, MP3, atau WAV.');

        $this->assertDatabaseCount('voice_announcements', 0);
    }

    public function test_validation_failures_do_not_exhaust_the_recording_upload_limit(): void
    {
        Storage::fake('local');
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        $this->actingAs($operator);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson(route('voice-announcements.store'), [
                'title' => 'Format tidak didukung',
                'duration_seconds' => 6,
                'audio' => UploadedFile::fake()->create('panggilan.txt', 20, 'text/plain'),
            ])->assertUnprocessable();
        }

        $this->postJson(route('voice-announcements.store'), [
            'title' => 'Rekaman setelah validasi',
            'duration_seconds' => 6,
            'audio' => UploadedFile::fake()->create('panggilan.webm', 20, 'audio/webm'),
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Rekaman berhasil disimpan.');

        $this->assertDatabaseCount('voice_announcements', 1);
    }

    public function test_successful_recording_uploads_remain_rate_limited_per_user(): void
    {
        Storage::fake('local');
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        $this->actingAs($operator);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson(route('voice-announcements.store'), [
                'title' => 'Rekaman '.$attempt,
                'duration_seconds' => 6,
                'audio' => UploadedFile::fake()->create('panggilan.webm', 20, 'audio/webm'),
            ])->assertCreated();
        }

        $this->postJson(route('voice-announcements.store'), [
            'title' => 'Rekaman berikutnya',
            'duration_seconds' => 6,
            'audio' => UploadedFile::fake()->create('panggilan.webm', 20, 'audio/webm'),
        ])
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Batas penyimpanan rekaman tercapai. Tunggu sekitar satu menit sebelum mencoba lagi.')
            ->assertHeader('Retry-After');

        $this->assertDatabaseCount('voice_announcements', 10);
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

    public function test_replaying_an_announcement_starts_a_new_playback_window(): void
    {
        $this->travelTo('2026-10-20 12:00:00');
        $announcement = VoiceAnnouncement::query()->create([
            'title' => 'Pesanan siap',
            'message' => 'Pesanan sudah siap diambil.',
            'duration_seconds' => 12,
        ]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->postJson(route('voice-announcements.broadcast', $announcement))
            ->assertOk();

        $this->travel(13)->seconds();

        $this->postJson(route('voice-announcements.broadcast', $announcement))
            ->assertOk();

        $this->assertSame('2026-10-20 12:00:13', $announcement->refresh()->started_at->format('Y-m-d H:i:s'));
        $this->assertSame(30, $announcement->duration_seconds);
        $this->assertTrue($announcement->is_playing);
        $this->getJson(route('voice-announcements.public-state'))
            ->assertOk()
            ->assertJsonPath('announcement.started_at', '2026-10-20T12:00:13.000000+07:00');
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

    public function test_operator_can_open_the_voice_announcement_studio(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        $this->actingAs($operator)
            ->get(route('voice-announcements.index'))
            ->assertSee('Rekam suara baru')
            ->assertSee('Mikrofon siap digunakan')
            ->assertSee('MAKS. 03:00')
            ->assertSee('Simpan rekaman')
            ->assertSee('voiceAnnouncementPopup');
    }

    public function test_administrator_sidebar_has_a_separate_scrollable_navigation_and_visible_profile(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->get(route('voice-announcements.index'))
            ->assertOk()
            ->assertSee('portal-sidebar-admin', false)
            ->assertSee('data-sidebar-navigation', false)
            ->assertSeeInOrder([
                '<p class="portal-nav-label">OPERASIONAL</p>',
                '<div class="portal-sidebar-navigation" data-sidebar-navigation>',
                '<nav class="portal-nav" aria-label="Navigasi ruang kerja">',
            ], false)
            ->assertSee('Admin · Profil');
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

    public function test_completing_an_order_starts_a_public_call_that_expires_after_24_hours(): void
    {
        $this->travelTo('2026-10-19 12:00:00');
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        $order = Pesanan::factory()->create([
            'kode_pesanan' => 'TK-VOICE-24H',
            'status' => 'diproses',
        ]);

        $this->actingAs($operator)
            ->patch(route('orders.status.update', $order), ['status' => 'selesai'])
            ->assertRedirectToRoute('orders.index');

        $announcement = VoiceAnnouncement::query()->sole();
        $this->assertSame($order->id, $announcement->order_id);
        $this->assertSame(30, $announcement->duration_seconds);
        $this->assertSame(
            'Perhatian, pesanan dengan kode TK-VOICE-24H sudah selesai dan siap diambil. Terima kasih.',
            $announcement->message,
        );
        $this->assertSame('2026-10-20 12:00:00', $announcement->expires_at->format('Y-m-d H:i:s'));
        $this->assertTrue($announcement->is_playing);

        $this->getJson(route('voice-announcements.public-state'))
            ->assertOk()
            ->assertJsonPath('announcement.message', $announcement->message)
            ->assertJsonPath('announcement.audio_url', null);

        $this->get(route('voice-announcements.index'))
            ->assertOk()
            ->assertSee('Panggilan otomatis')
            ->assertSee('Hapus paksa')
            ->assertSee('TK-VOICE-24H');

        $announcement->update(['duration_seconds' => 12]);
        $this->travel(13)->seconds();

        $this->getJson(route('voice-announcements.public-state'))
            ->assertOk()
            ->assertJsonPath('announcement.message', $announcement->message)
            ->assertJsonPath('announcement.duration_seconds', 30);
        $this->assertTrue($announcement->refresh()->is_playing);

        $this->travel(1)->day();

        $this->getJson(route('voice-announcements.public-state'))
            ->assertOk()
            ->assertExactJson(['announcement' => null]);
        $this->get(route('voice-announcements.index'))
            ->assertOk()
            ->assertDontSee('TK-VOICE-24H');
    }

    public function test_guest_can_receive_public_voice_announcements_without_logging_in(): void
    {
        $order = Pesanan::factory()->create(['kode_pesanan' => 'TK-GUEST-VOICE']);
        VoiceAnnouncement::query()->create([
            'order_id' => $order->id,
            'title' => 'Pesanan TK-GUEST-VOICE selesai',
            'message' => 'Pesanan dengan kode TK-GUEST-VOICE siap diambil.',
            'duration_seconds' => 12,
            'started_at' => now(),
            'expires_at' => now()->addDay(),
            'is_playing' => true,
        ]);

        $this->getJson(route('voice-announcements.public-state'))
            ->assertOk()
            ->assertJsonPath('announcement.title', 'Pesanan TK-GUEST-VOICE selesai');
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('voiceAnnouncementPopup');
    }

    public function test_staff_can_force_delete_an_automatic_order_call(): void
    {
        $order = Pesanan::factory()->create();
        $announcement = VoiceAnnouncement::query()->create([
            'order_id' => $order->id,
            'title' => 'Panggilan otomatis',
            'message' => 'Pesanan siap diambil.',
            'duration_seconds' => 12,
            'expires_at' => now()->addDay(),
        ]);
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        $this->actingAs($operator)
            ->deleteJson(route('voice-announcements.destroy', $announcement))
            ->assertOk()
            ->assertJsonPath('message', 'Panggilan otomatis berhasil dihapus paksa.');

        $this->assertModelMissing($announcement);
    }
}
