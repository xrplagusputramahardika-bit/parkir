# 🚗 SmartPark System - Sistem Manajemen Parkir Cerdas & Modern

Sistem website parkir cerdas berbasis **PHP Native** murni dengan antarmuka modern **Bootstrap 5.3**, dilengkapi fitur pemindaian kamera **QR Code / Barcode scanner**, integrasi scanner fisik USB, pencetakan struk termal (thermal receipt), pemantauan okupansi slot area parkir secara real-time, audit log aktivitas, serta rekapitulasi laporan pendapatan.

Sistem ini dirancang 100% presisi sesuai skema database diagram yang disediakan.

---

## 📌 Skema Database Relasional

Sistem menggunakan database MariaDB/MySQL dengan 6 tabel berelasi:

1. **`tb_user`**: Pengelolaan pengguna multi-role (`admin`, `petugas`, `owner`), shift pagi/malam petugas, dan status keaktifan (`status_aktif`).
2. **`tb_tarif`**: Pengaturan tarif parkir per jam untuk setiap jenis kendaraan (`motor`, `mobil`, `lainnya`).
3. **`tb_area_parkir`**: Manajemen zona area parkir, kapasitas maksimal, dan jumlah slot terisi (`terisi`) yang tersinkronisasi otomatis.
4. **`tb_kendaraan`**: Data master kendaraan terdaftar (`plat_nomor`, `jenis_kendaraan`, `warna`, `pemilik`, `id_user`).
5. **`tb_transaksi`**: Pencatatan siklus parkir (`waktu_masuk`, `waktu_keluar`, `durasi_jam`, `biaya_total`, `status` [masuk/keluar], petugas masuk `id_user`, kasir keluar `id_user_keluar`, `id_area`, `slot_number`, `id_tarif`).
6. **`tb_log_aktivitas`**: Audit trail pencatatan setiap aksi sistem (login, logout, keluar-masuk kendaraan, perubahan tarif, dsb).

---

## 🚀 Fitur-Fitur Unggulan

### 1. 📷 Pemindai Kamera (QR Code & Barcode Scanner)
- **Webcam / Kamera HP Scanner**: Menggunakan engine `Html5-QRCode` untuk memindai tiket parkir tanpa alat tambahan.
- **Dukungan Barcode Scanner Fisik (USB / Bluetooth)**: Dilengkapi deteksi otomatis barcode gun kasir tanpa perlu klik mouse pada input.
- **Audio Feedback**: Dilengkapi suara bip (*beep sound*) bawaan Web Audio API saat barcode berhasil dipindai.

### 2. 🖨️ Cetak Struk / Tiket Termal (Thermal Printer 58mm & 80mm)
- **Tiket Masuk**: Otomatis menghasilkan kode batang 1D (Code128) dan kode 2D (QR Code), nomor tiket unik, nomor plat, waktu masuk, dan estimasi tarif.
- **Struk Keluar**: Menampilkan rincian durasi (jam & menit aktual), pembulatan durasi resmi, total biaya, nominal uang bayar, kembalian, serta kasir bertugas.
- **Auto-Print Trigger**: Halaman cetak otomatis membuka dialog cetak printer bawaan.

### 3. 📊 Visualisasi Slot Parkir Real-Time
- Visualisasi dinamis okupansi setiap area: **Tersedia (Hijau)**, **Hampir Penuh (Kuning)**, dan **Penuh (Merah)**.
- Otomatis mengunci dan menolak kendaraan baru jika kapasitas suatu area telah mencapai batas maksimal (100%).

### 4. 💰 Kasir & Kalkulator Pembayaran Otomatis
- Pencarian instan (AJAX) nomor plat atau scan tiket.
- Kalkulasi otomatis durasi parkir dan biaya berdasarkan tarif di tabel `tb_tarif`.
- Kalkulator kembalian instan dengan tombol preset nominal uang pas, Rp 5.000, 10.000, 20.000, 50.000, 100.000.

### 5. 👥 Hak Akses Multi-Role
- **Admin**: Akses penuh ke seluruh fitur sistem, data master (area, tarif, kendaraan, pengguna), transaksi, laporan, dan log aktivitas.
- **Petugas Shift Pagi dan Shift Malam**: Masing-masing memakai akun sendiri untuk gerbang masuk/keluar. Dashboard petugas menampilkan pendapatan transaksi yang diselesaikan oleh akunnya sendiri; admin/owner dapat membandingkan jumlah transaksi dan pendapatan per petugas.
- **Petugas masuk dan kasir keluar dicatat terpisah** pada tiap transaksi, sehingga pendapatan masuk ke petugas yang benar-benar menerima pembayaran.
- **Owner**: Akses eksekutif untuk memantau performa bisnis, dashboard analitik grafik, laporan pendapatan, ekspor data, dan log audit.

### 6. 📈 Laporan & Ekspor Data
- Filter fleksibel berdasarkan rentang tanggal, jenis kendaraan, dan area parkir.
- **Ekspor ke Excel** (`.csv` dengan format UTF-8 BOM).
- **Cetak Laporan Formal (PDF/A4)** lengkap dengan kop instansi dan kolom tanda tangan pengesahan pimpinan.

### 7. 💾 Backup & Restore Database
- Admin dapat mengunduh backup database dalam format JSON melalui menu **Backup & Restore**.
- Backup berisi data pengguna, area parkir, tarif, kendaraan, transaksi, dan log aktivitas.
- Restore hanya menerima file backup aplikasi yang strukturnya cocok; seluruh data aplikasi yang ada akan diganti.
- Admin juga dapat mereset data operasional (transaksi, kendaraan, dan log aktivitas) tanpa menghapus akun pengguna, area, atau tarif; aksi ini memerlukan konfirmasi dan dicatat dalam log audit.
- Simpan file backup di media atau lokasi terpisah dari server. Restore memerlukan struktur tabel aplikasi yang sama dan tidak mencadangkan kode program atau konfigurasi server.

Untuk database lama, jalankan `database_upgrade_slot_parkir.sql` satu kali sebelum menggunakan pemilihan slot. Migrasi memberi nomor pada slot kendaraan yang sedang aktif berdasarkan nomor tiket.

Setiap area memiliki nomor slot mulai dari 1 sesuai kapasitasnya. Slot aktif ditandai terisi sampai transaksi kendaraan diselesaikan; petugas memilih area dan slot kosong saat menerbitkan tiket.

---

## 🔑 Akun Demo Bawaan (Quick Login)

Sistem sudah dilengkapi fitur *1-Click Demo Fill* pada halaman login:

| Role | Username | Password | Deskripsi |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin` | `admin123` | Administrator Utama Sistem |
| **Petugas Shift Pagi** | `petugas` | `petugas123` | Petugas Loket Gerbang Shift Pagi |
| **Petugas Shift Malam** | `petugas2` | `petugas2123` | Petugas Loket Gerbang Shift Malam |
| **Owner** | `owner` | `owner123` | Pemilik / Manajemen Bisnis |

---

## ⚙️ Cara Menjalankan Aplikasi

1. **Jalankan Apache & MySQL** pada XAMPP / Laragon Anda.
2. Pastikan database `parkiran` telah dibuat dan tabel-tabelnya terpasang (file skema dan seeder telah disertakan di `database.sql` dan `seed.php`). Untuk database lama, jalankan `database_upgrade_petugas_shift.sql` satu kali terlebih dahulu.
3. Buka browser dan akses URL:
   ```
   http://localhost/tempat_parkir/
   ```
4. Masuk dengan akun `admin` / `admin123`.

Pembayaran yang tercatat sebelum fitur kasir per akun diaktifkan tidak memiliki atribusi petugas kasir, sehingga tidak dimasukkan ke penghasilan pribadi petugas. Penghasilan pribadi dihitung dari transaksi yang selesai dibayar oleh akun petugas tersebut pada hari berjalan. Akun dengan transaksi kasir tidak dapat dihapus; nonaktifkan akun agar catatan penghasilannya tetap tersimpan.

---

## 📁 Struktur Direktori

```
tempat_parkir/
├── assets/
│   ├── css/
│   │   └── style.css            # Desain kustom modern, tema Bootstrap, styling tiket termal
│   └── js/
│       ├── main.js              # Jam digital real-time, interaksi sidebar, konfirmasi dialog
│       └── scanner.js           # Engine pemindai kamera QR & hardware scanner listener
├── config/
│   ├── database.php             # Koneksi PDO MySQL
│   └── helper.php               # Utilitas sistem, autentikasi, format mata uang, kalkulator tarif
├── includes/
│   ├── header.php               # Header HTML, CSS & library JS (Bootstrap 5, Icons, Chart.js, QR)
│   ├── sidebar.php              # Navigasi sidebar responsif berdasarkan role
│   ├── topbar.php               # Navigasi atas, jam live, tombol aksi cepat, profil
│   └── footer.php               # Footer aplikasi
├── modules/
│   ├── auth/                    # Halaman login modern & logout
│   ├── backup/                  # Backup dan restore database aplikasi
│   ├── dashboard/               # Dashboard analitik, visual slot area & ringkasan KPI
│   ├── transaksi/               # Gerbang masuk, keluar/scan, cetak struk, riwayat
│   ├── area/                    # Kelola area parkir & kapasitas
│   ├── tarif/                   # Kelola tarif per jam kendaraan
│   ├── kendaraan/               # Master data kendaraan terdaftar
│   ├── user/                    # Manajemen pengguna & hak akses
│   ├── laporan/                 # Rekap laporan, cetak PDF, ekspor Excel
│   └── log/                     # Audit trail riwayat aktivitas
├── database.sql                 # Skema DDL & seed data database
├── database_upgrade_slot_parkir.sql # Migrasi nomor slot untuk database lama
├── seed.php                     # Script inisialisasi / seeder otomatis
├── index.php                    # Redirector utama
└── README.md                    # Dokumentasi lengkap proyek
```
