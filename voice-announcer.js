/**
 * SmartPark Voice Announcer & PA Audio System
 * Menggunakan Web Audio API untuk Nada Bel (Chime) & Web Speech API untuk Suara Bahasa Indonesia
 */

const VoiceAnnouncer = {
    audioEnabled: localStorage.getItem('smartpark_audio_muted') !== 'true',

    // Toggle mute/unmute
    toggleAudio() {
        this.audioEnabled = !this.audioEnabled;
        localStorage.setItem('smartpark_audio_muted', (!this.audioEnabled).toString());
        this.updateAudioButtonState();
        if (this.audioEnabled) {
            this.speak("Fitur pengumuman audio diaktifkan.");
        }
    },

    updateAudioButtonState() {
        const btn = document.getElementById('btnToggleAudioMute');
        const icon = document.getElementById('iconAudioMute');
        const text = document.getElementById('textAudioMute');
        if (btn) {
            if (this.audioEnabled) {
                btn.classList.remove('btn-outline-secondary');
                btn.classList.add('btn-outline-primary');
                if (icon) icon.className = 'bi bi-volume-up-fill text-primary';
                if (text) text.textContent = 'Audio Aktif';
            } else {
                btn.classList.remove('btn-outline-primary');
                btn.classList.add('btn-outline-secondary');
                if (icon) icon.className = 'bi bi-volume-mute-fill text-muted';
                if (text) text.textContent = 'Audio Senyap';
            }
        }
    },

    // Synthesize authentic 2-tone airport / mall PA chime using Web Audio API
    playChime(callback) {
        if (!this.audioEnabled) {
            if (typeof callback === 'function') callback();
            return;
        }

        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) {
                if (typeof callback === 'function') callback();
                return;
            }
            const ctx = new AudioContext();
            
            // Tone 1: F5 (698.46 Hz)
            const osc1 = ctx.createOscillator();
            const gain1 = ctx.createGain();
            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(698.46, ctx.currentTime);
            gain1.gain.setValueAtTime(0.001, ctx.currentTime);
            gain1.gain.exponentialRampToValueAtTime(0.3, ctx.currentTime + 0.05);
            gain1.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.45);
            osc1.connect(gain1);
            gain1.connect(ctx.destination);
            osc1.start(ctx.currentTime);
            osc1.stop(ctx.currentTime + 0.45);

            // Tone 2: A5 (880.00 Hz)
            const osc2 = ctx.createOscillator();
            const gain2 = ctx.createGain();
            osc2.type = 'sine';
            osc2.frequency.setValueAtTime(880.00, ctx.currentTime + 0.35);
            gain2.gain.setValueAtTime(0.001, ctx.currentTime + 0.35);
            gain2.gain.exponentialRampToValueAtTime(0.3, ctx.currentTime + 0.4);
            gain2.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 1.1);
            osc2.connect(gain2);
            gain2.connect(ctx.destination);
            osc2.start(ctx.currentTime + 0.35);
            osc2.stop(ctx.currentTime + 1.1);

            // Trigger callback when chime finishes
            setTimeout(() => {
                if (typeof callback === 'function') callback();
            }, 850);
        } catch (e) {
            console.warn('Audio Context Chime Error:', e);
            if (typeof callback === 'function') callback();
        }
    },

    // Announce text using Indonesian speech synthesis
    speak(text, onComplete) {
        if (!this.audioEnabled) return;
        if (!('speechSynthesis' in window)) {
            console.warn('Browser tidak mendukung Speech Synthesis.');
            return;
        }

        // Cancel previous speech if ongoing
        window.speechSynthesis.cancel();

        this.playChime(() => {
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = 'id-ID';
            utterance.rate = 0.95; // Sedikit lebih lambat agar artikulasi jelas
            utterance.pitch = 1.05; // Nada ramah dan berwibawa

            // Cari suara bahasa Indonesia jika tersedia di OS/browser
            const voices = window.speechSynthesis.getVoices();
            const idVoice = voices.find(v => v.lang.includes('id') || v.lang.includes('ID') || v.name.toLowerCase().includes('indonesia'));
            if (idVoice) {
                utterance.voice = idVoice;
            }

            // Tampilkan visual bar pengumuman
            this.showAnnouncementToast(text);

            utterance.onend = () => {
                this.hideAnnouncementToast();
                if (typeof onComplete === 'function') onComplete();
            };

            utterance.onerror = (e) => {
                this.hideAnnouncementToast();
                console.warn('Speech Error:', e);
            };

            window.speechSynthesis.speak(utterance);
        });
    },

    showAnnouncementToast(text) {
        let toastEl = document.getElementById('voiceAnnouncementToast');
        if (!toastEl) {
            toastEl = document.createElement('div');
            toastEl.id = 'voiceAnnouncementToast';
            toastEl.className = 'voice-announcement-toast shadow-lg';
            document.body.appendChild(toastEl);
        }
        toastEl.innerHTML = `
            <div class="d-flex align-items-center gap-3">
                <div class="voice-wave-anim">
                    <span></span><span></span><span></span><span></span>
                </div>
                <div class="overflow-hidden">
                    <small class="text-uppercase fw-bold text-primary" style="font-size: 0.68rem; letter-spacing: 0.08em;">
                        <i class="bi bi-broadcast me-1"></i> Pengumuman Suara
                    </small>
                    <div class="small fw-semibold text-dark text-truncate" style="max-width: 320px;">${text}</div>
                </div>
            </div>
        `;
        toastEl.classList.add('show');
    },

    hideAnnouncementToast() {
        const toastEl = document.getElementById('voiceAnnouncementToast');
        if (toastEl) {
            toastEl.classList.remove('show');
        }
    },

    // Quick Announcement Presets
    announceMasuk(platNomor, namaArea) {
        const text = `Selamat datang di SmartPark. Kendaraan dengan nomor plat ${platNomor}, silakan parkir di ${namaArea}. Harap simpan tiket Anda dengan aman.`;
        this.speak(text);
    },

    announceKeluar(platNomor, totalBiayaFmt) {
        const text = `Terima kasih. Pembayaran parkir untuk kendaraan ${platNomor} sebesar ${totalBiayaFmt} telah berhasil. Barrier gate terbuka. Hati-hati di jalan dan selamat sampai tujuan.`;
        this.speak(text);
    },

    announcePanggilan(platNomor, area, keperluan) {
        const text = `Perhatian. Panggilan kepada pemilik kendaraan dengan nomor plat ${platNomor} di ${area}. Dimohon segera menuju kendaraan Anda karena ${keperluan}. Terima kasih.`;
        this.speak(text);
    },

    announceKeamanan() {
        const text = `Perhatian kepada seluruh pengunjung parkir. Pastikan kendaraan Anda telah terkunci ganda dan tidak meninggalkan barang berharga di dalam kendaraan. Keamanan bersama adalah prioritas kita.`;
        this.speak(text);
    },

    announceTutup() {
        const text = `Pemberitahuan. Area parkir akan segera ditutup dalam waktu tiga puluh menit. Bagi pemilik kendaraan yang masih terparkir, dimohon segera menuju loket kasir keluar. Terima kasih.`;
        this.speak(text);
    }
};

// Inisialisasi suara saat DOM siap
document.addEventListener('DOMContentLoaded', function () {
    if ('speechSynthesis' in window) {
        window.speechSynthesis.onvoiceschanged = function () {
            window.speechSynthesis.getVoices();
        };
    }
    VoiceAnnouncer.updateAudioButtonState();
});

