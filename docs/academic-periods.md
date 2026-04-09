# Tahun Ajaran & Semester (Academic Periods)

---

## Tahun Ajaran (SchoolYear)

Tahun ajaran adalah periode akademik satu tahun, misalnya **2024/2025**.

### Aturan Penting

- **Hanya satu tahun ajaran yang boleh aktif** pada satu waktu
- Seluruh Finance panel berjalan berdasarkan tahun ajaran yang sedang aktif
- Jika tidak ada tahun ajaran aktif, Finance panel menampilkan peringatan dan beberapa fitur diblokir

### Status Tahun Ajaran

| `is_active` | Arti |
|-------------|------|
| `true` | Tahun ajaran sedang berjalan — enrollment baru dan tagihan mengacu ke ini |
| `false` | Arsip — data tetap ada dan bisa dilihat, tapi tidak bisa jadi acuan enrollment baru |

### Cara Mengelola

**Di mana:** Admin panel → Tahun Ajaran

- Tambah tahun ajaran baru → otomatis nonaktifkan yang lama
- Satu tahun ajaran bisa punya beberapa **Semester**

### Dampak ke Peserta Didik

Ketika tahun ajaran berganti (diaktifkan/dinonaktifkan), `syncActiveStatus()` dipanggil untuk semua peserta didik — status aktif/tidak aktif dihitung ulang berdasarkan enrollment di tahun ajaran yang baru aktif.

---

## Semester (SchoolTerm)

Semester adalah sub-periode dalam tahun ajaran. Semester 1 dan Semester 2.

### Aturan

- Satu semester aktif dalam satu waktu
- Semester menentukan **bulan mana yang bisa digunakan** untuk tagihan SPP bulanan
  - Contoh: Semester 1 = Juli–Desember, Semester 2 = Januari–Juni

### Cara Mengelola

**Di mana:** Admin panel → Semester

---

## Contoh Skenario: Ganti Tahun Ajaran

**Situasi:** Tahun ajaran 2024/2025 selesai, mulai 2025/2026.

1. **Admin panel** → Tahun Ajaran → Tambah `2025/2026`
2. Set `2025/2026` sebagai aktif → `2024/2025` otomatis nonaktif
3. Semua peserta didik yang hanya punya enrollment di 2024/2025 → `is_active = false`
4. Staf Finance perlu menjalankan proses kenaikan kelas (bulk promote) → enrollment baru di 2025/2026 dibuat
5. Setelah enrollment baru tersimpan → `is_active = true` kembali

> Proses kenaikan kelas massal akan tersedia sebagai fitur wizard di versi berikutnya.

---

## Contoh Skenario: Peserta Didik Baru di Tengah Tahun

**Situasi:** Peserta didik pindahan masuk di bulan Oktober (tengah Semester 1 tahun 2025/2026).

1. Buat peserta didik baru di Finance panel
2. Tambah enrollment: pilih tahun ajaran 2025/2026, sekolah, kelas
3. Peserta didik langsung Aktif
4. Tagihan SPP dibuat hanya untuk bulan Oktober ke depan *(bukan Juli–September)*

> Penanganan tagihan prorata di tengah tahun akan bergantung pada implementasi `student_payment_accounts`.
