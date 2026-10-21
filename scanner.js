/**
 * Advanced Scanner Module (Webcam & Hardware Barcode Scanner)
 * Smart Parking Management System
 */

// Sound effect synthesizer using Web Audio API
function playScanBeep(success = true) {
    try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        const ctx = new AudioContext();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();

        osc.connect(gain);
        gain.connect(ctx.destination);

        if (success) {
            osc.frequency.setValueAtTime(880, ctx.currentTime); // High pitch A5
            gain.gain.setValueAtTime(0.15, ctx.currentTime);
            osc.start();
            osc.stop(ctx.currentTime + 0.12);
        } else {
            osc.frequency.setValueAtTime(300, ctx.currentTime); // Low pitch buzz
            gain.gain.setValueAtTime(0.2, ctx.currentTime);
            osc.start();
            osc.stop(ctx.currentTime + 0.25);
        }
    } catch (e) {
        console.warn('Audio Context error:', e);
    }
}

let html5QrCodeInstance = null;
let isScannerRunning = false;

/**
 * Inisialisasi Kamera Pemindai (QR & Barcode)
 */
function initCameraScanner(elementId, onScanSuccessCallback) {
    const readerEl = document.getElementById(elementId);
    if (!readerEl) return;

    if (html5QrCodeInstance && isScannerRunning) {
        html5QrCodeInstance.stop().then(() => {
            isScannerRunning = false;
        }).catch(err => console.error(err));
        return;
    }

    const html5QrCode = new Html5Qrcode(elementId);
    html5QrCodeInstance = html5QrCode;

    const config = {
        fps: 15,
        qrbox: { width: 260, height: 260 },
        aspectRatio: 1.0
    };

    html5QrCode.start(
        { facingMode: "environment" },
        config,
        (decodedText, decodedResult) => {
            playScanBeep(true);
            if (typeof onScanSuccessCallback === 'function') {
                onScanSuccessCallback(decodedText, decodedResult);
            }
        },
        (errorMessage) => {
            // Ignore frame decode errors
        }
    ).then(() => {
        isScannerRunning = true;
        const toggleBtn = document.getElementById('btnToggleCamera');
        if (toggleBtn) {
            toggleBtn.innerHTML = '<i class="bi bi-camera-video-off me-1"></i> Matikan Kamera';
            toggleBtn.classList.replace('btn-primary', 'btn-danger');
        }
    }).catch(err => {
        console.error("Gagal memulai kamera:", err);
        Swal.fire({
            icon: 'error',
            title: 'Kamera Tidak Tersedia',
            text: 'Izin kamera ditolak atau perangkat tidak memiliki webcam/kamera aktif. Anda tetap dapat menggunakan input manual atau scanner USB.',
            confirmButtonColor: '#4361ee'
        });
    });
}

/**
 * Matikan Kamera Pemindai
 */
function stopCameraScanner() {
    if (html5QrCodeInstance && isScannerRunning) {
        html5QrCodeInstance.stop().then(() => {
            isScannerRunning = false;
            const toggleBtn = document.getElementById('btnToggleCamera');
            if (toggleBtn) {
                toggleBtn.innerHTML = '<i class="bi bi-camera-video me-1"></i> Buka Kamera Scanner';
                toggleBtn.classList.replace('btn-danger', 'btn-primary');
            }
        }).catch(err => console.error(err));
    }
}

/**
 * Listener untuk Scanner Barcode Fisik (USB / Bluetooth Handheld Gun)
 * Scanner fisik bertindak seperti keyboard super cepat yang diakhiri Enter.
 */
function listenHardwareScanner(onBarcodeCaptured) {
    let barcodeBuffer = '';
    let lastKeyTime = Date.now();

    window.addEventListener('keydown', function (e) {
        // Jangan tangkap jika pengguna sedang fokus mengetik di input teks biasa (kecuali scanner-input)
        const activeTag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
        const isInputField = (activeTag === 'input' || activeTag === 'textarea' || activeTag === 'select');
        const isTargetScannerInput = document.activeElement && document.activeElement.classList.contains('scanner-input');

        if (isInputField && !isTargetScannerInput) {
            return;
        }

        const currentTime = Date.now();
        const diff = currentTime - lastKeyTime;
        lastKeyTime = currentTime;

        if (e.key === 'Enter') {
            if (barcodeBuffer.trim().length >= 3) {
                e.preventDefault();
                playScanBeep(true);
                const scannedCode = barcodeBuffer.trim();
                barcodeBuffer = '';
                if (typeof onBarcodeCaptured === 'function') {
                    onBarcodeCaptured(scannedCode);
                }
            }
            barcodeBuffer = '';
        } else if (e.key.length === 1) {
            // Jika jeda antar karakter terlalu lama (> 60ms), reset buffer (berarti manusia mengetik perlahan)
            if (diff > 80 && !isTargetScannerInput) {
                barcodeBuffer = '';
            }
            barcodeBuffer += e.key;
        }
    });
}

