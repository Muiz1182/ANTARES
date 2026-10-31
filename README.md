# ANTARES - Sistem Absensi Sekolah Berbasis NFC (SMP)

**ANTARES** adalah aplikasi manajemen kehadiran harian siswa Sekolah Menengah Pertama (SMP) berbasis kartu pintar **NFC 13.56 MHz (MIFARE Classic 1K / S50)** menggunakan pembaca kartu **USB HID Reader (Keyboard Emulation)**.

Sistem ini dirancang dengan arsitektur monolitik modern untuk menggantikan pencatatan absensi kertas manual menjadi pencatatan digital waktu nyata (*real-time*), lengkap dengan kontrol akses berbasis peran (*Role-Based Access Control*), layar penuh **Pos Absensi (Scan Station)**, manajemen siklus hidup kartu NFC, koreksi kehadiran berjejak audit (*Audit Log*), dashboard analitik langsung, serta laporan bulanan siap cetak (`.xlsx` & `.pdf`).

---

## Teknologi Utama (Tech Stack)

- **Backend**: Laravel 12 (PHP 8.2+)
- **Frontend**: Inertia.js v2 + Vue 3 (`<script setup>` Composition API) + TypeScript
- **Styling**: Tailwind CSS
- **Otorisasi & Hak Akses**: Spatie Laravel Permission (5 Peran)
- **Ekspor & Impor Spreadsheet / Dokumen**: OpenSpout (`.xlsx` & `.csv` *streaming* hemat memori) + Generator PDF Resmi Sekolah (`.pdf` A4 *Landscape*)
- **Pengujian & Kualitas Kode**: Pest v3, Node.js Native Test Runner (`node --test`), Laravel Pint, `vue-tsc`
- **Zona Waktu & Bahasa**: `Asia/Jakarta` (WIB) & Bahasa Indonesia (`id`)

---

## Fitur Utama

### Fase 0: Fondasi, Standarisasi & Normalisasi UID NFC (Selesai)
- Konfigurasi zona waktu `Asia/Jakarta` dan lokalisasi Bahasa Indonesia (`lang/id/`).
- Dokumentasi sampel perangkat keras nyata ([docs/reader-sample.md](docs/reader-sample.md)) untuk **NFC Reader Hassel 13.56 MHz MF1 S50 USB**.
- **Normalisasi UID Kembar (PHP & TypeScript)**:
  - PHP: `App\Support\NfcUidNormalizer`
  - TypeScript: `resources/js/utils/nfcUid.ts`
  - Mengubah output mentah USB HID (10-digit desimal, hex 4-byte/7-byte dengan pemisah `:`, `-`, atau spasi) menjadi format kanonik huruf besar heksadesimal (`A-F0-9`) yang identik 100% di sisi server maupun klien.
- Pipeline *Quality Gate* CI terpadu (`composer run ci` & `npm run ci`).

### Fase 1: Autentikasi, Peran & Manajemen Data Master (Selesai)
- **5 Peran Pengguna (RBAC)**:
  1. `super_admin` - Akses penuh ke seluruh fitur, manajemen pengguna, pengaturan identitas sekolah, dan manajemen perangkat pos absensi.
  2. `admin_tu` - Pengelolaan data master (tahun ajaran, kelas, siswa, foto, impor Excel/CSV, kalender hari libur), registrasi kartu NFC, aturan jam absensi, koreksi kehadiran, laporan ekspor, dan akses Pos Absensi.
  3. `piket` - Petugas Pos Absensi harian, input Izin/Sakit/Dispensasi harian, koreksi kehadiran hari berjalan, dan pencarian direktori siswa.
  4. `wali_kelas` - Akses terbatas khusus kelas binaan (*homeroom*) untuk memantau kehadiran harian, mencatat Izin/Sakit/Dispensasi siswa kelasnya, serta mengunduh rekap laporan kelas binaan.
  5. `kepala_sekolah` - Akses baca saja (*read-only*) untuk pemantauan dashboard sekolah, rekap laporan seluruh kelas, dan unduhan laporan.
- **Manajemen Tahun Ajaran**: Menjamin secara transaksional hanya ada **1 tahun ajaran aktif** (`is_active = true`) pada satu waktu.
- **Manajemen Kelas**: Pengaturan tingkat (`7`, `8`, `9`), nama kelas unik per tahun ajaran, dan penugasan Wali Kelas.
- **Manajemen Siswa**: CRUD siswa (NIS, NISN, jenis kelamin, kelas, kontak orang tua/wali, unggah foto profil maks 2 MB, serta *soft delete*).
- **Impor Massal Siswa (Excel/CSV)**: Unduh templat `.xlsx`/`.csv`, pratinjau validasi per baris dengan laporan pesan kesalahan yang spesifik, serta penyimpanan atomik (mendukung 700+ baris tanpa *timeout*).
- **Manajemen Hari Libur**: Kalender libur nasional dan libur khusus sekolah.

### Fase 2: Kartu NFC, Composable Scanner, Pos Absensi & API Scan (Selesai)
- **Registrasi & Manajemen Kartu NFC (`/cards` & `/cards/bulk`)**:
  - Registrasi kartu tunggal dengan penangkapan tap reader langsung atau input manual.
  - Validasi keunikan: satu UID tidak dapat dipakai dua pemilik aktif, dan satu siswa hanya memiliki satu kartu aktif pada satu waktu.
  - Alur **Ganti Kartu (Hilang / Rusak)**: menonaktifkan kartu lama (`lost` atau `inactive`), mencatat waktu penonaktifan (`deactivated_at`) serta referensi kartu pengganti (`replaced_by_card_id`), dan mempertahankan seluruh riwayat kartu lama.
  - **Registrasi Massal per Kelas (Mode Scan Beruntun)**: menyorot siswa satu per satu dalam satu kelas sehingga operator cukup menempelkan tumpukan kartu secara berurutan, dilengkapi tombol **Lewati Siswa (Skip)** dan **Batalkan Kartu Terakhir (Undo)**.
- **Manajemen Perangkat Pos (`/devices`)**:
  - Pendaftaran terminal pos absensi dengan pembuatan token Bearer acak yang di-*hash* menggunakan SHA-256 di database dan hanya ditampilkan **satu kali** saat dibuat atau dibuat ulang.
  - Pencatatan waktu terakhir perangkat terhubung (`last_seen_at`) serta pencabutan akses perangkat (*revoke*).
- **Composable `useNfcScanner` (`resources/js/composables/useNfcScanner.ts`)**:
  - Menangkap semburan ketikan cepat (*fast keystroke burst*, jeda antar karakter <= 50 ms) yang diakhiri tombol `Enter` dari USB HID Reader.
  - Mengabaikan ketikan tangan manual yang lambat sehingga aktivitas papan ketik biasa di komputer pos tidak memicu scan palsu.
- **Halaman Layar Penuh Pos Absensi (`/scan-station`)**:
  - Penangkap fokus otomatis, indikator status koneksi server, dan jam digital waktu nyata (`Asia/Jakarta`).
  - Menampilkan foto siswa (atau avatar inisial), nama lengkap, NIS, kelas, jam tap, dan status kehadiran dengan warna kontras tinggi (WCAG AA) serta bunyi *beep* Web Audio API yang berbeda untuk **Sukses**, **Tap Duplikat**, **Peringatan (Kartu Hilang / Izin)**, dan **Ditolak / Kartu Tidak Dikenal**.
  - Auto-reset tampilan utama setelah **3 detik** dan daftar **10 pemindaian terakhir** di sisi layar.
- **Endpoint `POST /api/scan` & Mesin Keputusan Absensi (PRD 4.3)**:
  - Dilindungi middleware token perangkat (`Authorization: Bearer <device_token>`) dan *rate limiter* (`throttle:scan-api`).
  - Mendukung `idempotency_key` dan mencatat setiap tap mentah ke tabel `attendance_logs`.
  - Mengimplementasikan seluruh 12 hasil keputusan scan: `success_in`, `success_out`, `duplicate`, `already_checked_in`, `already_checked_out`, `no_checkin`, `blocked_excused`, `unknown_card`, `inactive_card`, `lost_card`, `student_inactive`, dan `no_school_day`.

### Fase 3: Aturan Jam Absensi, Penjadwal Alfa Otomatis, Izin/Sakit, Koreksi & Log Audit (Selesai)
- **Pengaturan Aturan Jam Absensi Harian (`/attendance-rules`)**:
  - Konfigurasi per hari (`Senin` sampai `Minggu`) untuk status hari sekolah (`is_school_day`), jam buka masuk (`check_in_start`), batas tepat waktu (`late_after`), batas akhir masuk (`check_in_end`), jam buka pulang (`check_out_start`), dan batas akhir pulang (`check_out_end`).
  - Validasi urutan kronologis jam (`check_in_start < late_after <= check_in_end < check_out_start < check_out_end`) dan pencatatan perubahan di `audit_logs`.
- **Penjadwal Otomatis Status Alpa (`php artisan attendance:mark-absent`)**:
  - Dijadwalkan berjalan setiap 5 menit melalui Laravel Scheduler (`routes/console.php`).
  - Menandai siswa aktif yang belum memiliki catatan kehadiran setelah melewati `check_in_end` pada hari sekolah aktif (bukan hari libur) sebagai `alpa` (`source = auto_absent`) secara idempoten.
  - Mendukung *override* transparan apabila kartu dipindai susulan dengan pencatatan ke `audit_logs`.
- **Kehadiran Harian per Kelas, Pencatatan Izin/Sakit/Dispensasi & Koreksi (`/attendances/today`)**:
  - Tampilan daftar seluruh siswa per kelas pada tanggal terpilih lengkap dengan ringkasan cepat (`Hadir`, `Terlambat`, `Izin`, `Sakit`, `Dispensasi`, `Alpa`, dan `Belum Absen`).
  - Form pencatatan manual **Izin**, **Sakit**, dan **Dispensasi** dengan dukungan unggah lampiran bukti surat (`.jpg`, `.png`, `.pdf`, maks 2 MB) dan pembatasan cakupan kelas otomatis untuk `wali_kelas`.
  - Alur **Koreksi Kehadiran Manual** dengan kewajiban mengisi alasan koreksi (`notes` minimal 5 karakter) yang menyimpan perbandingan nilai sebelum (`old_values`) dan sesudah (`new_values`) di `audit_logs`.
- **Log Audit Sistem (`/audit-logs`)**:
  - Pencatatan terpusat untuk aksi sensitif (perubahan aturan jam absensi, input izin/sakit/dispensasi, koreksi kehadiran, dan *override* status alpa otomatis) lengkap dengan filter tanggal, jenis aksi, dan pelaku.

### Fase 4: Dashboard Analitik Real-Time, Laporan Matriks Bulanan, Ekspor Excel/PDF & Pengaturan Sekolah (Selesai)
- **Dashboard Analitik Kehadiran Real-Time (`/dashboard` & `/dashboard/live-state`)**:
  - Kartu indikator utama hari ini: Total Siswa Aktif, Hadir Tepat Waktu (`Hadir` + persentase), Terlambat (`Terlambat` + persentase), Izin/Sakit/Dispensasi, serta Alpa/Belum Tap.
  - Grafik progres kehadiran hari ini per kelas yang dapat diklik langsung menuju daftar kehadiran kelas terkait.
  - Umpan langsung (*Live Feed*) **20 aktivitas tap terakhir** dan peringatan kelas yang masih memiliki siswa belum tap mendekati batas akhir masuk, disinkronkan secara ringan setiap **2 detik**.
- **Laporan Matriks Bulanan & Analitik Siswa (`/reports`)**:
  - **Matriks Kehadiran Bulanan per Kelas (Tanggal 1 s/d 31)**: menampilkan kode ringkas `H` (Hadir), `T` (Terlambat), `I` (Izin), `S` (Sakit), `A` (Alpa), `D` (Dispensasi), dan `L` (Libur/Akhir Pekan) beserta total rekap per siswa dan persentase kehadiran.
  - **Riwayat Kehadiran Individu Siswa**: rincian jam masuk, jam pulang, durasi keterlambatan, sumber pencatatan, dan catatan per tanggal.
  - **Peringkat Keterlambatan & Ketidakhadiran**: daftar siswa dengan frekuensi terlambat dan alpa tertinggi pada periode terpilih.
- **Ekspor Excel (`.xlsx`) & PDF Resmi Sekolah (`.pdf`) (`/reports/export/excel` & `/reports/export/pdf`)**:
  - Ekspor `.xlsx` menggunakan OpenSpout dengan 2 lembar kerja (*Sheet Ringkasan & Matriks Tanggal 1-31* serta *Sheet Log Rincian Kehadiran*).
  - Ekspor `.pdf` ukuran A4 *Landscape* siap cetak lengkap dengan kop identitas sekolah, tabel matriks kehadiran bulanan, tanggal cetak lokal Indonesia, dan blok tanda tangan Kepala Sekolah serta Wali Kelas.
  - Dilengkapi `GenerateAttendanceExportJob` untuk pemrosesan ekspor latar belakang (*queued export*) pada rentang data skala besar.
- **Pengaturan Identitas & Logo Sekolah (`/settings`)**:
  - Pengelolaan nama sekolah, NPSN, alamat lengkap, nomor telepon, email, nama & NIP Kepala Sekolah, serta unggah logo sekolah yang langsung digunakan pada bilah navigasi aplikasi dan kop laporan PDF.

### Peta Jalan Pengembangan Selanjutnya (Roadmap)
- **Fase 5**: Antrian Offline (*IndexedDB* + `POST /api/scan/batch`), penguatan keamanan produksi, dan panduan deployment *on-premise*.

---

## Arsitektur Kode

Proyek ini menerapkan aturan arsitektur ketat yang terdokumentasi di [AGENTS.md](AGENTS.md):
1. **Thin Controllers**: Controller hanya menangani otorisasi (`Policy`), validasi (`FormRequest`), memanggil kelas `Action`/`Service`, dan mengembalikan respons Inertia, JSON Resource, atau *redirect*.
2. **Actions & Services**: Logika bisnis dan mutasi multi-langkah ditempatkan di `app/Actions/` dan `app/Services/` di dalam transaksi database (`DB::transaction`).
3. **Form Requests**: Seluruh validasi request berada di `app/Http/Requests/`.
4. **Eloquent Resources**: Model Eloquent tidak pernah dikirim mentah ke halaman Inertia maupun API, melainkan ditransformasi melalui `JsonResource` di `app/Http/Resources/`.
5. **UID Normalization**: Seluruh pembacaan UID kartu NFC wajib melewati helper tunggal `NfcUidNormalizer` (PHP) dan `nfcUid.ts` (TypeScript).

---

## Persyaratan Sistem

- **PHP** >= 8.2 (dengan ekstensi `pdo`, `mbstring`, `zip`, `gd`, `xml`, `fileinfo`)
- **Composer** >= 2.x
- **Node.js** >= 20.x (direkomendasikan Node.js 22/24 LTS) & **NPM**
- **Database**: SQLite (bawaan pengembangan lokal), PostgreSQL 15+, atau MySQL 8.0+ / MariaDB 10.6+

---

## Panduan Instalasi Lokal

1. **Clone repositori ini:**
   ```bash
   git clone https://github.com/Muiz1182/ANTARES.git
   cd ANTARES
   ```

2. **Pasang dependensi PHP dan JavaScript:**
   ```bash
   composer install
   npm install
   ```

3. **Salin file konfigurasi environment dan buat kunci aplikasi:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Siapkan database dan jalankan migrasi beserta *seeder*:**
   - Jika menggunakan SQLite (default di `.env.example`), buat file database jika belum ada:
     ```bash
     touch database/database.sqlite
     ```
   - Jalankan migrasi dan *seeder* data awal:
     ```bash
     php artisan migrate --seed
     php artisan storage:link
     ```

5. **Jalankan server pengembangan:**
   ```bash
   composer run dev
   ```
   Atau jalankan secara terpisah di dua terminal:
   ```bash
   php artisan serve
   npm run dev
   ```

6. Buka aplikasi di peramban pada alamat `http://127.0.0.1:8000`.

---

## Akun Demo & Data Seeder Bawaan (`php artisan db:seed`)

Seluruh akun demo di bawah ini menggunakan kata sandi: **`password`**

| Peran | Email | Hak Akses Utama |
| :--- | :--- | :--- |
| **Super Admin** | `superadmin@sekolah.sch.id` | Seluruh fitur sistem, pengaturan sekolah, log audit, manajemen pengguna & perangkat pos |
| **Admin Sekolah / TU** | `tu@sekolah.sch.id` | Data master, kartu NFC, aturan jam absensi, kehadiran harian, koreksi, laporan ekspor & Pos Absensi |
| **Petugas Piket** | `piket@sekolah.sch.id` | Operasikan Pos Absensi (`/scan-station`), kehadiran harian, input izin/sakit & koreksi hari ini |
| **Wali Kelas 7A** | `wali7a@sekolah.sch.id` | Dashboard, kehadiran harian, input izin/sakit & laporan ekspor khusus kelas binaan 7A |
| **Wali Kelas 8A** | `wali8a@sekolah.sch.id` | Dashboard, kehadiran harian, input izin/sakit & laporan ekspor khusus kelas binaan 8A |
| **Kepala Sekolah** | `kepsek@sekolah.sch.id` | Akses baca saja (*Read-Only*) dashboard sekolah, laporan bulanan, ekspor Excel/PDF & data master |

**Perangkat & Kartu NFC Demo (Seeder Pengembangan):**
- **Perangkat Pos Default**: `Pos Gerbang Utama`
- **Token Bearer Default (Pengembangan Lokal)**: `dev-station-token-gerbang-utama-2026`
- **Sampel UID Kartu Terdaftar**: `04A1B2C3` (desimal: `0077705923`), `C3B2A104` (desimal: `3283263748`), `0044DA59` (desimal: `0004512345`), dan `04A1B2C3D4E5F6`.

---

## Pengujian, Linting, Type-Check & Script CI

Jalankan perintah berikut untuk memverifikasi kualitas kode sebelum melakukan *commit* atau *pull request*:

- **Menjalankan Seluruh Test Backend (Pest - 88 Tests / 555 Assertions):**
  ```bash
  php artisan test
  ```
- **Menjalankan Unit Test TypeScript (NFC UID Normalizer & `useNfcScanner` - 6 Tests):**
  ```bash
  npm run test:unit
  ```
- **Pemeriksaan Gaya Kode PHP (Laravel Pint):**
  ```bash
  ./vendor/bin/pint --test
  ```
- **Pemeriksaan Tipe TypeScript & Vue (`vue-tsc`):**
  ```bash
  npm run type-check
  ```
- **Build Aset Produksi (Vite):**
  ```bash
  npm run build
  ```
- **Menjalankan Seluruh Quality Gate CI Sekaligus:**
  ```bash
  composer run ci
  npm run ci
  ```

---

## Dokumentasi Proyek

- [AGENTS.md](AGENTS.md) - Aturan arsitektur, konvensi kode, dan standar kualitas proyek.
- [docs/BRIEF.md](docs/BRIEF.md) - Ringkasan bisnis, analisis perangkat keras NFC, dan rancangan sistem.
- [docs/PRD.md](docs/PRD.md) - Spesifikasi kebutuhan produk (*Product Requirements Document*) dan pembagian fase pengembangan.
- [docs/reader-sample.md](docs/reader-sample.md) - Dokumentasi sampel output mentah NFC Reader Hassel MF1 S50 dan aturan normalisasi UID.
