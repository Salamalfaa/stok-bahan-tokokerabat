const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

async function requestJson(url, options = {}) {
	const response = await fetch(url, {
		...options,
		headers: {
			Accept: 'application/json',
			'X-CSRF-TOKEN': csrfToken,
			'X-Requested-With': 'XMLHttpRequest',
			...options.headers,
		},
		cache: 'no-store',
	});
	const result = await response.json().catch(() => ({}));

	if (!response.ok) {
		const firstError = Object.values(result.errors ?? {}).flat()[0];
		throw new Error(firstError ?? result.message ?? 'Permintaan belum berhasil.');
	}

	return result;
}

const studio = document.querySelector('[data-voice-studio]');

if (studio) {
	const resourceUrl = studio.dataset.resourceUrl;
	const form = studio.querySelector('[data-record-form]');
	const startButton = studio.querySelector('[data-record-start]');
	const stopButton = studio.querySelector('[data-record-stop]');
	const saveButton = studio.querySelector('[data-record-save]');
	const fileInput = studio.querySelector('[data-audio-file]');
	const status = studio.querySelector('[data-recording-status]');
	const clock = studio.querySelector('[data-recording-clock]');
	const preview = studio.querySelector('[data-recording-preview]');
	const message = studio.querySelector('[data-form-message]');
	let recorder;
	let audioChunks = [];
	let audioBlob;
	let recordingStream;
	let recordingStartedAt = 0;
	let recordingTimer;
	let previewUrl;

	const formatClock = (seconds) => `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;

	startButton.addEventListener('click', async () => {
		if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
			status.textContent = 'Browser ini belum mendukung rekaman mikrofon.';
			return;
		}

		try {
			recordingStream = await navigator.mediaDevices.getUserMedia({ audio: true });
			const supportedType = ['audio/webm;codecs=opus', 'audio/mp4', 'audio/ogg;codecs=opus']
				.find((type) => MediaRecorder.isTypeSupported(type));
			recorder = new MediaRecorder(recordingStream, supportedType ? { mimeType: supportedType } : undefined);
			audioChunks = [];
			audioBlob = undefined;
			recorder.addEventListener('dataavailable', (event) => {
				if (event.data.size > 0) {
					audioChunks.push(event.data);
				}
			});
			recorder.addEventListener('stop', () => {
				audioBlob = new Blob(audioChunks, { type: recorder.mimeType || 'audio/webm' });
				fileInput.files = new DataTransfer().files;
				if (previewUrl) {
					URL.revokeObjectURL(previewUrl);
				}
				previewUrl = URL.createObjectURL(audioBlob);
				preview.src = previewUrl;
				preview.hidden = false;
				saveButton.disabled = audioBlob.size === 0 || audioBlob.size > 20 * 1024 * 1024;
				status.textContent = audioBlob.size > 20 * 1024 * 1024
					? 'Ukuran rekaman melebihi batas 20 MB.'
					: 'Rekaman siap dipratinjau dan disimpan.';
				recordingStream?.getTracks().forEach((track) => track.stop());
				recordingStream = undefined;
				window.clearInterval(recordingTimer);
			});
			recorder.start();
			recordingStartedAt = Date.now();
			startButton.disabled = true;
			stopButton.disabled = false;
			saveButton.disabled = true;
			status.textContent = 'Sedang merekam...';
			recordingTimer = window.setInterval(() => {
				const elapsed = Math.floor((Date.now() - recordingStartedAt) / 1000);
				clock.textContent = formatClock(elapsed);
				if (elapsed >= Number(form.elements.duration_seconds.value || 30)) {
					recorder.stop();
					stopButton.disabled = true;
					startButton.disabled = false;
				}
			}, 250);
		} catch {
			status.textContent = 'Mikrofon tidak tersedia atau izin belum diberikan.';
		}
	});

	stopButton.addEventListener('click', () => {
		if (recorder?.state === 'recording') {
			recorder.stop();
			stopButton.disabled = true;
			startButton.disabled = false;
			clock.textContent = formatClock(Math.floor((Date.now() - recordingStartedAt) / 1000));
		}
	});

	form.addEventListener('submit', async (event) => {
		event.preventDefault();
		if (!audioBlob) {
			status.textContent = 'Rekam suara terlebih dahulu.';
			return;
		}

		const formData = new FormData(form);
		const extension = audioBlob.type.includes('mp4') ? 'mp4' : audioBlob.type.includes('ogg') ? 'ogg' : 'webm';
		formData.set('audio', new File([audioBlob], `panggilan.${extension}`, { type: audioBlob.type }));
		saveButton.disabled = true;
		message.textContent = 'Menyimpan rekaman...';

		try {
			await requestJson(form.action, { method: 'POST', body: formData });
			window.location.reload();
		} catch (error) {
			message.textContent = error.message;
			saveButton.disabled = false;
		}
	});
}

const feedback = document.querySelector('[data-voice-feedback]');
const announceResult = async (button, url, options, successMessage) => {
	button.disabled = true;
	try {
		const result = await requestJson(url, options);
		if (feedback) {
			feedback.textContent = result.message ?? successMessage;
		}
		if (options.method === 'DELETE') {
			document.querySelector(`[data-announcement-row="${button.dataset.deleteAnnouncement}"]`)?.remove();
		} else if (button.hasAttribute('data-broadcast-announcement') || button.hasAttribute('data-stop-announcement')) {
			window.setTimeout(() => window.location.reload(), 700);
		}
	} catch (error) {
		if (feedback) {
			feedback.textContent = error.message;
		}
		button.disabled = false;
	}
};

document.querySelectorAll('[data-broadcast-announcement]').forEach((button) => {
	button.addEventListener('click', () => announceResult(
		button,
		button.dataset.broadcastUrl,
		{ method: 'POST' },
		'Suara sedang diputar untuk semua pengunjung.',
	));
});

document.querySelectorAll('[data-delete-announcement]').forEach((button) => {
	button.addEventListener('click', () => {
		if (window.confirm('Hapus rekaman ini secara permanen?')) {
			announceResult(button, button.dataset.deleteUrl, { method: 'DELETE' }, 'Rekaman dihapus.');
		}
	});
});

document.querySelectorAll('[data-stop-announcement]').forEach((button) => {
	button.addEventListener('click', () => announceResult(button, studio?.dataset.stopUrl, { method: 'POST' }, 'Siaran dihentikan.'));
});

const popup = document.querySelector('#voiceAnnouncementPopup');

if (popup) {
	const audio = popup.querySelector('[data-popup-audio]');
	const title = popup.querySelector('[data-popup-title]');
	const timer = popup.querySelector('[data-popup-timer]');
	const progress = popup.querySelector('[data-popup-progress]');
	const enableButton = popup.querySelector('[data-popup-enable]');
	const completedAnnouncements = new Set();
	let activeId;
	let activeKey;
	let startedAt = 0;
	let duration = 0;
	let timerInterval;

	const hidePopup = () => {
		window.clearInterval(timerInterval);
		audio.pause();
		audio.removeAttribute('src');
		audio.load();
		popup.hidden = true;
		activeId = undefined;
		activeKey = undefined;
	};

	const updateTimer = () => {
		const elapsed = Math.max(0, (Date.now() - startedAt) / 1000);
		const remaining = Math.max(0, Math.ceil(duration - elapsed));
		timer.textContent = `${String(Math.floor(remaining / 60)).padStart(2, '0')}:${String(remaining % 60).padStart(2, '0')}`;
		progress.style.width = `${Math.min(100, (elapsed / duration) * 100)}%`;
		if (remaining <= 0) {
			completedAnnouncements.add(activeKey);
			hidePopup();
		}
	};

	const showAnnouncement = async (announcement) => {
		const announcementKey = `${announcement.id}:${announcement.started_at}`;
		if (announcementKey === activeKey || completedAnnouncements.has(announcementKey)) {
			return;
		}
		activeId = announcement.id;
		activeKey = announcementKey;
		startedAt = new Date(announcement.started_at).getTime();
		duration = announcement.duration_seconds;
		title.textContent = announcement.title;
		popup.hidden = false;
		enableButton.hidden = true;
		audio.src = announcement.audio_url;
		audio.load();
		const elapsed = Math.max(0, (Date.now() - startedAt) / 1000);
		audio.addEventListener('loadedmetadata', () => {
			if (activeId === announcement.id && elapsed < audio.duration) {
				audio.currentTime = elapsed;
			}
		}, { once: true });
		try {
			await audio.play();
		} catch {
			enableButton.hidden = false;
		}
		window.clearInterval(timerInterval);
		updateTimer();
		timerInterval = window.setInterval(updateTimer, 250);
	};

	audio.addEventListener('ended', () => {
		if (activeId !== undefined) {
			completedAnnouncements.add(activeKey);
		}
		hidePopup();
	});

	enableButton.addEventListener('click', async () => {
		try {
			await audio.play();
			enableButton.hidden = true;
		} catch {
			enableButton.textContent = 'Coba aktifkan suara lagi';
		}
	});

	const pollAnnouncement = async () => {
		if (document.hidden) {
			return;
		}
		try {
			const response = await fetch(popup.dataset.stateUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
			if (!response.ok) {
				return;
			}
			const { announcement } = await response.json();
			if (announcement) {
				await showAnnouncement(announcement);
			} else if (activeId !== undefined) {
				hidePopup();
			}
		} catch {
			return;
		}
	};

	pollAnnouncement();
	window.setInterval(pollAnnouncement, 2000);
}