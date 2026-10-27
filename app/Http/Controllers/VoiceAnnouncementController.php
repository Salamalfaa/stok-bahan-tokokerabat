<?php

namespace App\Http\Controllers;

use App\Models\VoiceAnnouncement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class VoiceAnnouncementController extends Controller
{
    public function index(): View
    {
        return view('portal.voice-announcements.index', [
            'announcements' => VoiceAnnouncement::query()
                ->whereNull('order_id')
                ->where(fn (Builder $query): Builder => $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now()))
                ->latest()
                ->get(),
            'orderAnnouncements' => VoiceAnnouncement::query()
                ->whereNotNull('order_id')
                ->where('expires_at', '>', now())
                ->latest()
                ->get(),
            'activeAnnouncement' => $this->activeAnnouncement(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'duration_seconds' => ['required', 'integer', 'min:1', 'max:180'],
            'audio' => [
                'required',
                'file',
                'mimetypes:audio/webm,video/webm,audio/ogg,video/ogg,application/ogg,audio/mp4,video/mp4,audio/mpeg,audio/wav,audio/x-wav,audio/vnd.wave',
                'max:20480',
            ],
        ], [
            'audio.mimetypes' => 'Format rekaman tidak didukung. Gunakan rekaman mikrofon WebM, Ogg, MP4, MP3, atau WAV.',
            'audio.max' => 'Ukuran rekaman melebihi batas 20 MB.',
        ]);

        $audioPath = Storage::disk('local')->putFile('voice-announcements', $validated['audio']);

        if (! $audioPath) {
            Log::error('Voice recording could not be written to private storage.', [
                'user_id' => $request->user()->id,
            ]);

            return response()->json(['message' => 'Rekaman gagal disimpan ke penyimpanan. Periksa ruang penyimpanan, lalu coba lagi.'], 500);
        }

        try {
            $announcement = VoiceAnnouncement::query()->create([
                'title' => $validated['title'],
                'duration_seconds' => $validated['duration_seconds'],
                'audio_path' => $audioPath,
                'created_by' => $request->user()->id,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($audioPath);

            throw $exception;
        }

        return response()->json([
            'message' => 'Rekaman berhasil disimpan.',
            'announcement' => ['id' => $announcement->id],
        ], 201);
    }

    public function broadcast(VoiceAnnouncement $voiceAnnouncement): JsonResponse
    {
        abort_if($voiceAnnouncement->expires_at?->lte(now()), 404);
        abort_unless(
            $voiceAnnouncement->message !== null
                || ($voiceAnnouncement->audio_path !== null && Storage::disk('local')->exists($voiceAnnouncement->audio_path)),
            404,
        );

        DB::transaction(function () use ($voiceAnnouncement): void {
            VoiceAnnouncement::query()->orderBy('id')->lockForUpdate()->first();
            VoiceAnnouncement::query()->update(['is_playing' => false]);
            $voiceAnnouncement->refresh();
            $voiceAnnouncement->update([
                'is_playing' => true,
                'started_at' => now(),
                'duration_seconds' => $this->playbackDurationSeconds($voiceAnnouncement),
            ]);
        });

        return response()->json(['message' => 'Suara sedang diputar untuk semua pengunjung.']);
    }

    public function stop(): JsonResponse
    {
        VoiceAnnouncement::query()->where('is_playing', true)->update(['is_playing' => false]);

        return response()->json(['message' => 'Siaran suara dihentikan.']);
    }

    public function destroy(VoiceAnnouncement $voiceAnnouncement): JsonResponse
    {
        $isOrderAnnouncement = $voiceAnnouncement->order_id !== null;

        if ($voiceAnnouncement->audio_path !== null) {
            Storage::disk('local')->delete($voiceAnnouncement->audio_path);
        }

        $voiceAnnouncement->delete();

        return response()->json([
            'message' => $isOrderAnnouncement
                ? 'Panggilan otomatis berhasil dihapus paksa.'
                : 'Rekaman berhasil dihapus.',
        ]);
    }

    public function audio(VoiceAnnouncement $voiceAnnouncement): BinaryFileResponse
    {
        abort_if($voiceAnnouncement->expires_at?->lte(now()), 404);
        abort_unless(
            $voiceAnnouncement->audio_path !== null && Storage::disk('local')->exists($voiceAnnouncement->audio_path),
            404,
        );

        return $this->fileResponse($voiceAnnouncement);
    }

    public function publicState(): JsonResponse
    {
        $announcement = $this->activeAnnouncement();

        return response()->json([
            'announcement' => $announcement ? [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'message' => $announcement->message,
                'duration_seconds' => $this->playbackDurationSeconds($announcement),
                'started_at' => $announcement->started_at->format('Y-m-d\TH:i:s.uP'),
                'audio_url' => $announcement->message === null ? route('voice-announcements.public-audio') : null,
            ] : null,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function publicAudio(): BinaryFileResponse
    {
        $announcement = $this->activeAnnouncement();
        abort_unless(
            $announcement !== null
                && $announcement->audio_path !== null
                && Storage::disk('local')->exists($announcement->audio_path),
            404,
        );

        return $this->fileResponse($announcement);
    }

    private function activeAnnouncement(): ?VoiceAnnouncement
    {
        $announcement = VoiceAnnouncement::query()
            ->where('is_playing', true)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()))
            ->latest('updated_at')
            ->first();

        if ($announcement?->started_at?->copy()->addSeconds($this->playbackDurationSeconds($announcement))->isPast()) {
            $announcement->update(['is_playing' => false]);

            return null;
        }

        return $announcement;
    }

    private function playbackDurationSeconds(VoiceAnnouncement $announcement): int
    {
        return $announcement->message !== null
            ? max($announcement->duration_seconds, 30)
            : $announcement->duration_seconds;
    }

    private function fileResponse(VoiceAnnouncement $announcement): BinaryFileResponse
    {
        $response = response()->file(Storage::disk('local')->path($announcement->audio_path), [
            'Content-Type' => Storage::disk('local')->mimeType($announcement->audio_path) ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
