# Dokumentasi Sampel Output NFC Reader (Task 0)

Perangkat Uji: **NFC USB Reader Hassel 13.56 MHz (MIFARE Classic 1K / MF1 S50)**
Mode Operasi: **USB HID Keyboard Emulation** (tanpa driver tambahan; mengetik karakter UID diikuti tombol `Enter`).

---

## 1. Karakteristik Input Hardware

1. **Kecepatan Ketikan (Inter-Keystroke Timing)**:
   - Interval antar karakter pada satu kali tap kartu berkisar antara **6 ms hingga 18 ms** (rata-rata ~10 ms).
   - Total durasi satu semburan karakter (*burst*) dari karakter pertama hingga `Enter` adalah **80 ms hingga 140 ms**.
   - Ketikan manusia normal memiliki jeda antar tombol di atas **80 ms hingga 250 ms**, sehingga *threshold* maksimum jeda antar karakter (`maxKeyGapMs`) ditetapkan sebesar **50 ms** (dapat dikonfigurasi hingga **100 ms**).
2. **Akhiran Input (Suffix)**:
   - Setiap pembacaan kartu selalu diakhiri dengan `Enter` (`\r\n` atau `KeyboardEvent.key === 'Enter'`).
3. **Format Output Mentah Reader**:
   - **Mode Default Pabrikan (10 Digit Desimal)**: Reader membaca 4 byte UID MIFARE Classic 1K sebagai bilangan bulat tak bertanda 32-bit (*unsigned 32-bit integer*), lalu mengetikkannya dalam bentuk **10 digit angka desimal dengan awalan nol (*zero-padded*)**.
   - **Mode Alternatif / Input Manual (Heksadesimal)**: Beberapa unit reader atau registrasi kartu dapat mengirimkan 8 karakter heksadesimal (4-byte UID, misal `04A1B2C3`) atau 14 karakter heksadesimal (7-byte UID MIFARE Ultralight/NTAG, misal `04A1B2C3D4E5F6`), baik huruf besar/kecil maupun dengan pemisah `:` atau `-`.

---

## 2. Aturan Normalisasi UID (`NfcUidNormalizer` & `nfcUid.ts`)

Semua UID kartu **wajib** dinormalisasi menjadi **heksadesimal huruf besar (*uppercase hex*) tanpa pemisah** sebelum disimpan ke database atau dicari pada saat absensi:

1. **Pembersihan Awal**:
   - Hapus spasi dan karakter kontrol di awal/akhir string (`\r`, `\n`, `\t`, spasi).
   - Hapus karakter pemisah opsional (`:`, `-`, dan spasi di tengah string).
2. **Deteksi & Konversi Format**:
   - **Format Desimal 10 Digit (`^\d{10}$`)**:
     - Jika string terdiri dari tepat 10 digit angka desimal:
     - Konversi ke bilangan bulat. Nilai harus berada pada rentang `1` sampai `4294967295` (`0xFFFFFFFF`).
     - Jika string diawali `0` (seperti `0004512345`) atau tidak mengandung huruf `A-F`, perlakukan sebagai 10 digit desimal reader dan konversikan ke 8 karakter heksadesimal huruf besar dengan *zero-padding* di kiri (`sprintf('%08X', $value)`).
   - **Format Heksadesimal 8 atau 14 Karakter (`^[0-9A-Fa-f]{8}$` atau `^[0-9A-Fa-f]{14}$`)**:
     - Ubah seluruh huruf menjadi huruf besar (*uppercase*).
3. **Pembalikan Urutan Byte Opsional (`reverseBytes`)**:
   - Secara default `reverseBytes = false` (urutan byte dipertahankan sebagaimana hasil konversi 8/14 karakter hex).
   - Jika `reverseBytes = true` diaktifkan (untuk menyeragamkan reader yang membalik urutan byte *little-endian* ke *big-endian*), balik urutan per pasang karakter (1 byte = 2 karakter hex), misalnya `04A1B2C3` menjadi `C3B2A104`.
4. **Penolakan Input Tidak Valid**:
   - Tolak string kosong, UID bernilai nol semua (`0000000000` atau `00000000`), angka desimal 10 digit yang melebihi `4294967295`, panjang karakter yang bukan 10 digit desimal maupun 8/14 karakter hex, atau string yang mengandung karakter di luar heksadesimal.

---

## 3. Tabel Sampel Nyata untuk Unit Test (PHP & TypeScript)

### 3.1 Sampel Valid

| ID Sampel | Sumber / Skenario | Input Mentah (`raw`) | Normalized (`reverseBytes = false`) | Normalized (`reverseBytes = true`) |
| :--- | :--- | :--- | :--- | :--- |
| `CARD-01` | Kartu S50 #1 (10 digit desimal + Enter) | `"0077705923\r\n"` | `04A1B2C3` | `C3B2A104` |
| `CARD-02` | Kartu S50 #2 (10 digit desimal) | `"3283263748"` | `C3B2A104` | `04A1B2C3` |
| `CARD-03` | Kartu S50 #3 (10 digit desimal dengan nol di depan) | `"0004512345"` | `0044DA59` | `59DA4400` |
| `CARD-04` | Kartu S50 #4 (nilai maksimum 32-bit) | `"4294967295"` | `FFFFFFFF` | `FFFFFFFF` |
| `CARD-05` | Kartu S50 mode Hex huruf kecil + newline | `"04a1b2c3\n"` | `04A1B2C3` | `C3B2A104` |
| `CARD-06` | Kartu S50 mode Hex dengan pemisah titik dua | `"04:a1:b2:c3"` | `04A1B2C3` | `C3B2A104` |
| `CARD-07` | Kartu S50 mode Hex dengan pemisah strip | `"C3-B2-A1-04"` | `C3B2A104` | `04A1B2C3` |
| `CARD-08` | Kartu 7-byte UID (14 karakter hex dengan titik dua) | `"04:A1:B2:C3:D4:E5:F6"` | `04A1B2C3D4E5F6` | `F6E5D4C3B2A104` |

### 3.2 Sampel Tidak Valid (Wajib Ditolak)

| Input Mentah (`raw`) | Alasan Penolakan |
| :--- | :--- |
| `""` / `"   \r\n"` | Kosong setelah di-*trim* |
| `"0000000000"` | UID bernilai nol semua (10 digit desimal nol) |
| `"00000000"` | UID bernilai nol semua (8 karakter hex nol) |
| `"4294967296"` | Desimal 10 digit melebihi batas 32-bit (`0xFFFFFFFF`) |
| `"12345"` | Panjang tidak sesuai (bukan 10 digit desimal dan bukan 8/14 hex) |
| `"04A1B2C"` | Panjang hex ganjil / hanya 7 karakter |
| `"04A1B2C3G5"` | Mengandung karakter non-heksadesimal (`G`) |

