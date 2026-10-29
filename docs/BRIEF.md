# Brief Sistem Absensi Sekolah Berbasis NFC

Stack: Laravel + Inertia.js + Vue 3 · Hardware: NFC USB Reader Hassel 13.56 MHz (MF1 S50) · Dev tool: Antigravity

## 1. Ringkasan Proyek

Sistem absensi sekolah berbasis web. Siswa SMP (sekitar 700 siswa) melakukan absensi harian dengan menempelkan kartu NFC (MIFARE Classic 1K / S50) ke reader USB yang terpasang di komputer pos absensi (gerbang atau ruang piket). Sistem mencatat kehadiran secara real-time, menghitung keterlambatan, dan menyediakan dashboard serta laporan untuk admin, wali kelas, dan kepala sekolah.

**Tujuan utama**

- Menggantikan absensi manual dengan pencatatan otomatis dan akurat.
- Absensi cepat: satu tap kurang dari 1 detik dari tap sampai feedback di layar.
- Laporan harian, mingguan, dan bulanan yang siap cetak atau ekspor.
- Opsional: notifikasi ke orang tua (WhatsApp) saat siswa datang atau pulang.

**Di luar cakupan (fase 1)**

- Aplikasi mobile native, pembacaan NFC lewat HP.
- Face recognition atau biometrik.
- Integrasi langsung ke Dapodik (cukup ekspor data).

## 2. Pengguna dan Peran

| Peran | Akses |
| --- | --- |
| Super Admin | Semua fitur, manajemen user, pengaturan sistem, perangkat |
| Admin Sekolah / Tata Usaha | Master data (siswa, guru, kelas, tahun ajaran), registrasi kartu, koreksi absensi, laporan |
| Petugas Piket | Halaman Scan Station, input izin/sakit manual |
| Wali Kelas / Guru | Lihat dan rekap absensi kelas binaan, input izin/sakit |
| Kepala Sekolah | Dashboard dan laporan (read-only) |
| Siswa / Orang Tua (opsional, fase 2) | Lihat riwayat kehadiran sendiri |

Implementasi role dengan `spatie/laravel-permission`.

## 3. Analisis Hardware: NFC Reader Hassel 13.56 MHz MF1 S50

**Asumsi kunci (wajib diverifikasi di awal):** reader USB jenis ini umumnya bekerja sebagai **HID keyboard emulation**. Saat kartu ditempel, reader "mengetik" UID kartu (biasanya angka desimal 8-10 digit atau hex) diikuti tombol **Enter**. Artinya:

- Tidak perlu driver atau library khusus, tidak perlu WebUSB atau Web Serial.
- Browser hanya menerima input keyboard cepat yang berakhir Enter.
- Format output UID (desimal/hex, urutan byte normal/terbalik, panjang) bisa berbeda antar unit dan **harus dinormalisasi**.

**Langkah verifikasi hardware (Task 0)**

1. Colokkan reader, buka Notepad atau input teks, tempel 3 kartu berbeda, catat output mentahnya.
2. Tentukan: format (desimal/hex), panjang, ada tidaknya Enter/suffix, urutan byte.
3. Tempel kartu yang sama 5 kali, pastikan UID konsisten.
4. Jika reader ternyata mode CDC/serial atau butuh konfigurasi, sesuaikan strategi (lihat bagian 6, alternatif).

**Catatan MIFARE S50**

- UID 4 byte (kadang 7 byte pada klon). Cukup untuk identifikasi.
- **Jangan mengandalkan UID sebagai rahasia.** UID Classic mudah diklon. Untuk sekolah, risikonya dapat diterima, tetapi tetap perlu mitigasi (lihat bagian 9).
- Fase 1 hanya membaca UID, tidak membaca atau menulis sektor data kartu.

## 4. Arsitektur Sistem

```
[Kartu NFC] -> [USB Reader (HID keyboard)] -> [Browser di PC Pos: halaman Scan Station]
                                                    |  (tangkap input, kirim via Inertia/axios)
                                                    v
                                          [Laravel: ScanController]
                                                    |
                           +------------------------+------------------------+
                           v                        v                        v
                      [Database]            [Broadcast (Reverb)]        [Queue: notifikasi WA]
                                                    |
                                                    v
                                       [Dashboard real-time admin]
```

**Stack**

- Laravel 11/12, PHP 8.2+, MySQL/MariaDB (atau PostgreSQL)
- Inertia.js v2 + Vue 3 (Composition API, `<script setup>`), TypeScript disarankan
- Tailwind CSS, komponen UI (shadcn-vue atau PrimeVue)
- Laravel Reverb (WebSocket) untuk update real-time, fallback polling
- Queue (database/Redis) untuk notifikasi dan ekspor laporan
- `spatie/laravel-permission`, `maatwebsite/excel`, `barryvdh/laravel-dompdf`
- Pest/PHPUnit untuk testing

## 5. Fitur

### 5.1 Scan Station (inti sistem)

Halaman khusus layar penuh untuk PC pos absensi.

- Input tersembunyi yang **selalu fokus** menangkap input reader. Re-focus otomatis saat blur atau klik di mana pun.
- Deteksi tap: kumpulkan karakter yang masuk cepat (interval antar karakter kurang dari 50 ms) hingga Enter. Abaikan ketikan manual yang lambat.
- Setelah scan: tampilkan **foto, nama, kelas, jam, status** (Hadir / Terlambat / Pulang) dengan warna jelas dan bunyi beep berbeda untuk sukses, duplikat, dan kartu tidak dikenal.
- Reset layar otomatis setelah 3 detik, siap scan berikutnya.
- Daftar 10 scan terakhir di sisi layar.
- Mode **Masuk**, **Pulang**, atau **Otomatis** (berdasarkan jam).
- Indikator status koneksi ke server. Jika offline, simpan scan di antrian lokal (IndexedDB) dan sinkronkan saat online (lihat bagian 8).
- Debounce: tap ulang kartu yang sama dalam N detik (default 30 detik, bisa diatur) dianggap duplikat dan tidak dicatat.

### 5.2 Registrasi dan Manajemen Kartu

- Halaman "Daftarkan Kartu": pilih siswa/guru, tempel kartu, UID tersimpan.
- Validasi: satu UID hanya boleh terhubung ke satu orang aktif.
- Ganti kartu (hilang/rusak): nonaktifkan kartu lama, daftarkan baru, simpan riwayat.
- Registrasi massal: mode "scan beruntun" untuk mengurutkan siswa satu kelas, kartu ditempel satu per satu.
- Status kartu: aktif, nonaktif, hilang (kartu hilang yang di-tap menghasilkan peringatan ke petugas).

### 5.3 Master Data

- Tahun ajaran dan semester
- Kelas dan rombel, wali kelas
- Siswa (NIS, NISN, nama, jenis kelamin, kelas, foto, kontak orang tua)
- Guru dan wali kelas (NIP, nama): hanya sebagai pengguna sistem, tidak absen dengan NFC
- Impor Excel/CSV untuk siswa dan guru, dengan validasi dan laporan error per baris
- Hari libur dan kalender akademik

### 5.4 Aturan Absensi

- Jam masuk, batas terlambat, jam pulang minimal, dikonfigurasi per hari (misal Jumat berbeda) dan per jenjang/kelas bila perlu.
- Status: **Hadir, Terlambat, Izin, Sakit, Alpa, Dispensasi**.
- Alpa otomatis lewat scheduled job: siswa tanpa catatan hingga batas waktu (misal 10:00) dan bukan hari libur diberi status Alpa.
- Input manual izin/sakit oleh wali kelas atau piket, dengan lampiran surat (opsional).
- Koreksi absensi oleh admin dengan alasan wajib dan tercatat di audit log.

### 5.5 Dashboard dan Laporan

- Dashboard real-time: jumlah hadir, terlambat, belum hadir hari ini, per kelas; daftar siswa belum hadir; grafik tren.
- Rekap harian, mingguan, bulanan, semester per siswa, per kelas, dan per sekolah.
- Ekspor Excel dan PDF (cetak rekap kelas, surat peringatan).
- Laporan khusus: siswa sering terlambat, siswa dengan alpa di atas ambang batas.

### 5.6 Notifikasi (opsional / fase 2)

- Pesan WhatsApp ke orang tua saat siswa tap masuk/pulang, lewat queue dan penyedia gateway pilihan (konfigurasi via driver agar mudah diganti).
- Ringkasan harian untuk wali kelas.

### 5.7 Pengaturan dan Audit

- Pengaturan sekolah (nama, logo, zona waktu `Asia/Jakarta`).
- Manajemen perangkat/pos scan: nama pos, token perangkat, status terakhir online.
- Audit log: siapa mengubah apa dan kapan.

## 6. Strategi Integrasi Reader di Frontend

Komponen Vue `useNfcScanner` (composable):

- Listener `keydown` global saat halaman Scan Station aktif.
- Buffer karakter, timestamp tiap karakter, reset buffer jika jeda lebih dari ambang (default 100 ms).
- Saat Enter diterima dan buffer valid (panjang dan karakter sesuai pola), emit event `scan` dengan UID ternormalisasi.
- Normalisasi UID di satu tempat (frontend dan backend sepakat): uppercase hex tanpa pemisah. Sediakan konfigurasi konversi desimal ke hex dan pembalikan urutan byte sesuai hasil Task 0.
- Cegah input masuk ke field lain saat Scan Station aktif.

**Alternatif jika reader bukan HID keyboard:** gunakan Web Serial API (Chrome/Edge) atau layanan agen lokal kecil (Node/Python) yang membaca serial dan meneruskan ke server lewat WebSocket/HTTP lokal.

## 7. Desain Database (draf)

| Tabel | Kolom utama |
| --- | --- |
| `users` | id, name, email, password, role (via Spatie) |
| `academic_years` | id, name, start\_date, end\_date, is\_active |
| `classrooms` | id, academic\_year\_id, name, level, homeroom\_teacher\_id |
| `students` | id, nis, nisn, name, gender, classroom\_id, photo, parent\_name, parent\_phone, is\_active |
| `teachers` | id, nip, name, photo, user\_id, is\_active |
| `nfc_cards` | id, uid (unique, ternormalisasi), cardable\_type, cardable\_id, status (active/inactive/lost), issued\_at, deactivated\_at, notes |
| `attendance_rules` | id, day\_of\_week, check\_in\_start, late\_after, check\_out\_start, scope (global/level/classroom) |
| `attendances` | id, attendable\_type, attendable\_id, date, check\_in\_at, check\_out\_at, status, source (nfc/manual), device\_id, note, created\_by |
| `attendance_logs` | id, nfc\_card\_id, uid\_raw, scanned\_at, device\_id, result (success/duplicate/unknown/inactive) |
| `devices` | id, name, token\_hash, last\_seen\_at |
| `holidays` | id, date, name |
| `audit_logs` | id, user\_id, action, subject, before, after, created\_at |

Catatan: simpan **semua** tap mentah di `attendance_logs` (termasuk gagal/duplikat) untuk forensik, sedangkan `attendances` adalah rekap satu baris per orang per hari (unique `attendable_type, attendable_id, date`). Indeks pada `nfc_cards.uid`, `attendances.date`.

## 8. Endpoint dan Alur Utama

**Alur scan**

1. Frontend kirim `POST /scan` dengan `uid`, `device_id`, `scanned_at`.
2. Backend: normalisasi UID, cari kartu aktif. Jika tidak ada, catat log `unknown` dan balas error.
3. Terapkan debounce dan aturan: tentukan masuk atau pulang, hitung status terlambat.
4. Upsert `attendances` dalam transaksi, tulis `attendance_logs`.
5. Balas data siswa untuk ditampilkan; broadcast event ke dashboard; dispatch job notifikasi.

**Offline mode:** frontend menyimpan scan (uid + timestamp klien) di IndexedDB, sinkron batch via `POST /scan/batch`. Backend memakai timestamp klien dengan validasi toleransi dan idempotency key per scan.

**Contoh rute**

- `POST /api/scan`, `POST /api/scan/batch` (auth token perangkat)
- Resource: students, teachers, classrooms, cards, attendances, reports
- `GET /reports/attendance/export`

## 9. Keamanan dan Integritas Data

- Auth Laravel (Breeze/Fortify) dan otorisasi berbasis Policy/Gate.
- Endpoint scan dilindungi token perangkat (hash di DB), rate limiting.
- UID bukan rahasia: mitigasi klon kartu dengan foto siswa tampil besar di layar agar petugas memverifikasi visual, deteksi anomali (tap dua pos berjauhan dalam waktu singkat), dan opsi fase 2 membaca data terenkripsi di sektor kartu.
- Validasi input ketat, perlindungan CSRF untuk sesi web, audit log untuk semua koreksi.
- Data pribadi anak: batasi akses menurut peran, enkripsi data kontak sensitif bila perlu, backup harian otomatis.
- Semua waktu disimpan UTC atau `Asia/Jakarta` secara konsisten (tentukan satu dan patuhi).

## 10. Kebutuhan Non-Fungsional

- Respons scan p95 di bawah 500 ms di jaringan lokal sekolah.
- Target 700 siswa SMP dan 1 pos scan pada fase 1, dengan desain yang tetap bisa menambah pos; lonjakan jam masuk sekitar 700 tap dalam 15 menit.
- UI responsif; Scan Station dioptimalkan untuk layar penuh desktop, dashboard juga nyaman di tablet/HP.
- Bahasa antarmuka Indonesia (siapkan i18n).
- Deployment: server lokal sekolah (on-premise) di jaringan LAN; HTTPS dengan sertifikat internal; backup terjadwal ke media terpisah; dukungan jaringan yang tidak stabil (offline queue).

## 11. Rencana Pengembangan (Milestone)

| Fase | Isi | Hasil |
| --- | --- | --- |
| 0 | Verifikasi hardware, setup repo, Laravel + Inertia + Vue + Tailwind, CI dasar | Format UID terdokumentasi, skeleton jalan |
| 1 | Auth, role, master data, impor Excel | Data sekolah bisa dikelola |
| 2 | Registrasi kartu + composable scanner + Scan Station + API scan | Tap kartu tercatat dan tampil di layar |
| 3 | Aturan jam, status terlambat, scheduler Alpa, izin/sakit, koreksi | Logika absensi lengkap |
| 4 | Dashboard real-time, laporan, ekspor Excel/PDF | Siap dipakai wali kelas dan kepala sekolah |
| 5 | Offline mode, audit log, hardening, uji beban, dokumentasi | Siap produksi |
| 6 (opsional) | Notifikasi WhatsApp, portal orang tua | Fitur tambahan |

## 12. Kriteria Penerimaan

- Tap kartu terdaftar menampilkan identitas dan status dalam kurang dari 1 detik dan tercatat tepat satu kali.
- Tap ganda dalam jendela debounce tidak membuat data ganda.
- Kartu tak dikenal atau nonaktif ditolak dengan feedback jelas dan tercatat.
- Alpa otomatis berjalan benar dan melewati hari libur.
- Laporan bulanan per kelas cocok dengan data harian dan dapat diekspor.
- Scan saat offline tersinkron tanpa duplikat setelah koneksi pulih.
- Seluruh test otomatis untuk logika scan, aturan jam, dan otorisasi lulus.

## 13. Panduan Kerja dengan Antigravity

Agar agent menghasilkan kode konsisten, gunakan brief ini sebagai dokumen acuan dan berikan konteks bertahap.

**Aturan proyek (taruh di file rules/`AGENTS.md` di root repo)**

- Laravel 11/12, PHP 8.2+, Inertia v2, Vue 3 `<script setup>` + TypeScript, Tailwind.
- Logika bisnis di Service/Action class, controller tipis, validasi lewat Form Request.
- Gunakan Eloquent resource/DTO untuk data ke Inertia, jangan kirim model mentah.
- Setiap fitur disertai test (Pest). Jalankan `php artisan test` dan lint sebelum selesai.
- Semua teks UI berbahasa Indonesia; kode, nama variabel, dan komit berbahasa Inggris.
- Zona waktu `Asia/Jakarta`; UID selalu dinormalisasi lewat satu helper.

**Cara menyuapi agent:** satu fase per sesi/task, mulai dari Fase 0. Berikan bagian brief yang relevan (misal bagian 5.1, 6, dan 8 untuk Fase 2), minta rencana dulu (implementation plan) sebelum eksekusi, lalu review diff per fase. Simpan contoh output mentah reader dari Task 0 ke dalam repo (`docs/reader-sample.md`) agar agent tidak menebak formatnya.

## 14. Keputusan Awal

| Topik | Keputusan | Dampak ke desain |
| --- | --- | --- |
| Skala | \~700 siswa SMP, 1 pos scan selama pengembangan | Beban ringan; tabel `devices` tetap disiapkan agar pos tambahan mudah |
| Hosting | Server sekolah (on-premise) | Akses lewat LAN, HTTPS sertifikat internal, queue dan Reverb berjalan lokal, backup terjadwal |
| Peserta | Siswa saja | Guru/staf tidak absen NFC; relasi kartu polimorfik dipertahankan agar bisa diperluas |
| Cakupan | Absensi harian | Tanpa absensi per mata pelajaran |
| Notifikasi WhatsApp | Fase 2 | Tidak dikerjakan di fase 1 |
| Kartu NFC | Belum ada | Gunakan beberapa kartu uji S50 saat pengembangan; pengadaan dan pembagian kartu jadi pekerjaan terpisah; registrasi massal (5.2) jadi prioritas |
| Format laporan khusus | Tidak ada | Laporan standar di bagian 5.5 cukup |
