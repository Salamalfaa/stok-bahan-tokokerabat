<?php

namespace App\Http\Controllers;

use App\Models\VoiceAnnouncement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VoiceAnnouncementController extends Controller
{
    public function index(): View
    {
        return view('portal.voice-announcements.index', [
            'announcements' => VoiceAnnouncement::query()->latest()->get(),
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
                'mimetypes:audio/webm,audio/ogg,audio/mp4,audio/mpeg,audio/wav,audio/x-wav',
                'max:20480',
            ],
        ]);

        $audioPath = Storage::disk('local')->putFile('voice-announcements', $validated['audio']);

        if (! $audioPath) {
            return response()->json(['message' => 'Rekaman belum dapat disimpan. Coba kembali.'], 500);
        }

        $announcement = VoiceAnnouncement::query()->create([
            'title' => $validated['title'],
            'duration_seconds' => $validated['duration_seconds'],
            'audio_path' => $audioPath,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Rekaman berhasil disimpan.',
            'announcement' => ['id' => $announcement->id],
        ], 201);
    }

    public function broadcast(VoiceAnnouncement $voiceAnnouncement): JsonResponse
    {
        abort_unless(Storage::disk('local')->exists($voiceAnnouncement->audio_path), 404);

        DB::transaction(function () use ($voiceAnnouncement): void {
            VoiceAnnouncement::query()->orderBy('id')->lockForUpdate()->first();
            VoiceAnnouncement::query()->update(['is_playing' => false]);
            $voiceAnnouncement->update([
                'is_playing' => true,
                'started_at' => now(),
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
        Storage::disk('local')->delete($voiceAnnouncement->audio_path);
        $voiceAnnouncement->delete();

        return response()->json(['message' => 'Rekaman berhasil dihapus.']);
    }

    public function audio(VoiceAnnouncement $voiceAnnouncement): BinaryFileResponse
    {
        abort_unless(Storage::disk('local')->exists($voiceAnnouncement->audio_path), 404);

        return $this->fileResponse($voiceAnnouncement);
    }

    public function publicState(): JsonResponse
    {
        $announcement = $this->activeAnnouncement();

        return response()->json([
            'announcement' => $announcement ? [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'duration_seconds' => $announcement->duration_seconds,
                'started_at' => $announcement->started_at->toIso8601String(),
                'audio_url' => route('voice-announcements.public-audio'),
            ] : null,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function publicAudio(): BinaryFileResponse
    {
        $announcement = $this->activeAnnouncement();
        abort_unless($announcement !== null && Storage::disk('local')->exists($announcement->audio_path), 404);

        return $this->fileResponse($announcement);
    }

    private function activeAnnouncement(): ?VoiceAnnouncement
    {
        $announcement = VoiceAnnouncement::query()
            ->where('is_playing', true)
            ->latest('updated_at')
            ->first();

        if ($announcement?->started_at?->copy()->addSeconds($announcement->duration_seconds)->isPast()) {
            $announcement->update(['is_playing' => false]);

            return null;
        }

        return $announcement;
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
