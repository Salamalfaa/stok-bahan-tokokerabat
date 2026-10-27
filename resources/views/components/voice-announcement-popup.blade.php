<aside
    class="voice-announcement-popup"
    id="voiceAnnouncementPopup"
    data-state-url="{{ route('voice-announcements.public-state') }}"
    aria-live="polite"
    aria-atomic="true"
    hidden
>
    <div class="voice-popup-topline"><span class="voice-popup-live-dot"></span><span>PEMANGGILAN TOKO KERABAT</span><span class="voice-popup-live-label">LANGSUNG</span></div>
    <div class="voice-popup-content">
        <span class="voice-popup-mark" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span></span>
        <div class="voice-popup-copy"><p>Untuk perhatian Anda</p><h2 data-popup-title></h2></div>
        <span class="voice-popup-timer" data-popup-timer>00:00</span>
    </div>
    <div class="voice-popup-progress" aria-hidden="true"><span data-popup-progress></span></div>
    <button class="voice-popup-enable" type="button" data-popup-enable hidden>Aktifkan suara</button>
    <audio data-popup-audio preload="none"></audio>
</aside>
