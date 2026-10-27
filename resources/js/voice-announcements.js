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
	const contentType = response.headers.get('content-type') ?? '';
	const result = contentType.includes('application/json')
		? await response.json()
		: { message: response.status === 413
			? 'Ukuran unggahan melebihi batas server. Gunakan rekaman yang lebih singkat.'
			: `Server tidak dapat memproses permintaan (HTTP ${response.status}).` };

	if (!response.ok) {
		const firstError = Object.values(result.errors ?? {}).flat()[0];
		throw new Error(firstError ?? result.message ?? `Permintaan belum berhasil (HTTP ${response.status}).`);
	}

	if (!contentType.includes('application/json')) {
		throw new Error('Server mengirim respons yang tidak dikenali. Muat ulang halaman dan coba lagi.');
	}

	return result;
}

const studio = document.querySelector('[data-voice-studio]');

if (studio) {
	const form = studio.querySelector('[data-record-form]');
	const startButton = studio.querySelector('[data-record-start]');
	const stopButton = studio.querySelector('[data-record-stop]');
	const saveButton = studio.querySelector('[data-record-save]');
	const status = studio.querySelector('[data-recording-status]');
	const clock = studio.querySelector('[data-recording-clock]');
	const preview = studio.querySelector('[data-recording-preview]');
	const message = studio.querySelector('[data-form-message]');
	let recorder;
	let audioChunks = [];
	let audioBlob;
	let recordingStream;
	let recordingStartedAt = 0;
	let recordedDurationSeconds = 0;
	let recordingLimitSeconds = 30;
	let recordingTimer;
	let previewUrl;
	const durationInput = form.elements.duration_seconds;

	const formatClock = (seconds) => `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
	const setRecordingStatus = (text, state = 'ready') => {
		status.textContent = text;
		status.dataset.state = state;
	};
	const stopRecordingStream = () => {
		recordingStream?.getTracks().forEach((track) => track.stop());
		recordingStream = undefined;
	};

	startButton.addEventListener('click', async () => {
		if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
			setRecordingStatus('Browser ini belum mendukung rekaman mikrofon.', 'error');
			return;
		}

		recordingLimitSeconds = Number(durationInput.value);
		if (!Number.isInteger(recordingLimitSeconds) || recordingLimitSeconds < 1 || recordingLimitSeconds > 180) {
			setRecordingStatus('Batas rekaman harus antara 1 sampai 180 detik.', 'error');
			durationInput.focus();
			return;
		}

		window.clearInterval(recordingTimer);
		audioBlob = undefined;
		saveButton.disabled = true;
		message.textContent = '';
		preview.hidden = true;
		if (previewUrl) {
			URL.revokeObjectURL(previewUrl);
			previewUrl = undefined;
		}
		setRecordingStatus('Meminta akses mikrofon...');

		try {
			recordingStream = await navigator.mediaDevices.getUserMedia({ audio: true });
			const supportedType = ['audio/webm;codecs=opus', 'audio/mp4', 'audio/ogg;codecs=opus']
				.find((type) => MediaRecorder.isTypeSupported(type));
			recorder = new MediaRecorder(recordingStream, supportedType ? { mimeType: supportedType } : undefined);
			audioChunks = [];
			clock.textContent = '00:00';
			durationInput.disabled = true;
			recorder.addEventListener('dataavailable', (event) => {
				if (event.data.size > 0) {
					audioChunks.push(event.data);
				}
			});
			recorder.addEventListener('stop', () => {
				audioBlob = new Blob(audioChunks, { type: recorder.mimeType || 'audio/webm' });
				recordedDurationSeconds = Math.max(1, Math.ceil((Date.now() - recordingStartedAt) / 1000));
				if (previewUrl) {
					URL.revokeObjectURL(previewUrl);
				}
				previewUrl = URL.createObjectURL(audioBlob);
				preview.src = previewUrl;
				preview.hidden = false;
				saveButton.disabled = audioBlob.size === 0 || audioBlob.size > 20 * 1024 * 1024;
				const recordingFailed = audioBlob.size === 0 || audioBlob.size > 20 * 1024 * 1024;
				setRecordingStatus(
					audioBlob.size > 20 * 1024 * 1024
						? 'Ukuran rekaman melebihi batas 20 MB.'
						: audioBlob.size === 0
							? 'Rekaman kosong. Periksa mikrofon lalu rekam kembali.'
							: 'Rekaman siap dipratinjau dan disimpan.',
					recordingFailed ? 'error' : 'ready',
				);
				stopRecordingStream();
				window.clearInterval(recordingTimer);
				durationInput.disabled = false;
			});
			recorder.addEventListener('error', () => {
				stopRecordingStream();
				window.clearInterval(recordingTimer);
				durationInput.disabled = false;
				startButton.disabled = false;
				stopButton.disabled = true;
				saveButton.disabled = true;
				setRecordingStatus('Rekaman gagal diproses. Periksa mikrofon lalu coba kembali.', 'error');
			});
			recorder.start(250);
			recordingStartedAt = Date.now();
			startButton.disabled = true;
			stopButton.disabled = false;
			saveButton.disabled = true;
			setRecordingStatus('Sedang merekam...', 'recording');
			recordingTimer = window.setInterval(() => {
				const elapsed = Math.floor((Date.now() - recordingStartedAt) / 1000);
				clock.textContent = formatClock(elapsed);
				if (elapsed >= recordingLimitSeconds) {
					recorder.stop();
					stopButton.disabled = true;
					startButton.disabled = false;
				}
			}, 250);
		} catch (error) {
			stopRecordingStream();
			window.clearInterval(recordingTimer);
			startButton.disabled = false;
			stopButton.disabled = true;
			durationInput.disabled = false;
			setRecordingStatus(error.name === 'NotAllowedError'
				? 'Izin mikrofon ditolak. Izinkan akses mikrofon lalu coba lagi.'
				: 'Mikrofon tidak tersedia atau rekaman gagal dimulai.', 'error');
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
			setRecordingStatus('Rekam suara terlebih dahulu.', 'error');
			return;
		}

		const formData = new FormData(form);
		const extension = audioBlob.type.includes('mp4') ? 'mp4' : audioBlob.type.includes('ogg') ? 'ogg' : 'webm';
		formData.set('duration_seconds', String(recordedDurationSeconds));
		formData.set('audio', new File([audioBlob], `panggilan.${extension}`, { type: audioBlob.type }));
		saveButton.disabled = true;
		message.textContent = 'Menyimpan rekaman...';

		try {
			const result = await requestJson(form.action, { method: 'POST', body: formData });
			message.textContent = result.message ?? 'Rekaman berhasil disimpan. Memuat daftar rekaman...';
			window.setTimeout(() => window.location.reload(), 500);
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
			const isOrderCall = button.closest('.voice-order-call-row') !== null;
			document.querySelector(`[data-announcement-row="${button.dataset.deleteAnnouncement}"]`)?.remove();
			if (isOrderCall) {
				window.setTimeout(() => window.location.reload(), 700);
			}
		} else if (button.hasAttribute('data-broadcast-announcement')) {
			button.disabled = false;
		} else if (button.hasAttribute('data-stop-announcement')) {
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
		const confirmation = button.closest('.voice-order-call-row')
			? 'Hapus panggilan otomatis ini secara permanen sebelum 24 jam berakhir?'
			: 'Hapus rekaman ini secara permanen?';
		if (window.confirm(confirmation)) {
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
	let activeAnnouncement;
	let startedAt = 0;
	let duration = 0;
	let timerInterval;

	const hidePopup = () => {
		window.clearInterval(timerInterval);
		audio.pause();
		audio.removeAttribute('src');
		audio.load();
		window.speechSynthesis?.cancel();
		popup.removeAttribute('data-playback');
		progress.classList.remove('is-speaking');
		progress.style.width = '';
		popup.hidden = true;
		activeId = undefined;
		activeKey = undefined;
		activeAnnouncement = undefined;
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
		window.clearInterval(timerInterval);
		audio.pause();
		window.speechSynthesis?.cancel();
		activeId = announcement.id;
		activeKey = announcementKey;
		activeAnnouncement = announcement;
		startedAt = new Date(announcement.started_at).getTime();
		duration = announcement.duration_seconds;
		const isSpeechAnnouncement = announcement.message !== null;
		progress.classList.remove('is-speaking');
		progress.style.width = '';
		timer.textContent = '00:00';
		title.textContent = announcement.title;
		popup.dataset.playback = isSpeechAnnouncement ? 'speech' : 'audio';
		popup.hidden = false;
		enableButton.hidden = true;
		const elapsed = Math.max(0, (Date.now() - startedAt) / 1000);

		if (!isSpeechAnnouncement && elapsed >= duration) {
			completedAnnouncements.add(announcementKey);
			hidePopup();
			return;
		}

		if (!isSpeechAnnouncement) {
			audio.src = announcement.audio_url;
			audio.load();
			audio.addEventListener('loadedmetadata', () => {
				if (activeKey !== announcementKey) {
					return;
				}
				if (elapsed >= audio.duration) {
					completedAnnouncements.add(announcementKey);
					hidePopup();
					return;
				}
				audio.currentTime = elapsed;
			}, { once: true });
		}

		try {
			await playAnnouncement();
		} catch {
			if (activeKey !== announcementKey) {
				return;
			}
			enableButton.hidden = false;
		}
		if (activeKey !== announcementKey) {
			return;
		}
		if (isSpeechAnnouncement) {
			timer.textContent = 'DIPUTAR';
			progress.classList.add('is-speaking');
		} else {
			updateTimer();
			timerInterval = window.setInterval(updateTimer, 250);
		}
	};

	const playAnnouncement = async () => {
		if (activeAnnouncement?.message !== null && activeAnnouncement?.message !== undefined) {
			if (!window.speechSynthesis || !window.SpeechSynthesisUtterance) {
				throw new Error('Browser ini belum mendukung suara otomatis.');
			}

			const announcementKey = activeKey;
			const utterance = new SpeechSynthesisUtterance(activeAnnouncement.message);
			utterance.lang = 'id-ID';
			utterance.rate = 0.95;
			utterance.onend = () => {
				if (activeKey === announcementKey) {
					completedAnnouncements.add(announcementKey);
					hidePopup();
				}
			};
			utterance.onerror = () => {
				if (activeKey === announcementKey) {
					enableButton.hidden = false;
				}
			};
			window.speechSynthesis.cancel();
			window.speechSynthesis.speak(utterance);

			return;
		}

		await audio.play();
	};

	audio.addEventListener('ended', () => {
		if (activeId !== undefined) {
			completedAnnouncements.add(activeKey);
		}
		hidePopup();
	});

	enableButton.addEventListener('click', async () => {
		try {
			await playAnnouncement();
			enableButton.hidden = true;
		} catch {
			enableButton.textContent = 'Suara tidak didukung di browser ini';
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