# Alur Peserta Didik (Student Lifecycle)

Dokumen ini menjelaskan perjalanan lengkap seorang peserta didik di dalam sistem — dari pertama kali didaftarkan hingga lulus atau pindah.

---

## Ringkasan Status

```
[Dibuat] → [Tidak Aktif] → [Aktif] → [Lulus / Pindah / Tidak Aktif]
              ↑ tanpa enrollment    ↑ punya enrollment ENROLLED di tahun ajaran aktif
```

Sistem tidak punya "draft" untuk peserta didik. Status hanya ditentukan oleh satu hal: **apakah peserta didik punya enrollment aktif di tahun ajaran yang sedang berjalan?**

---

## 1. Membuat Peserta Didik Baru

**Di mana:** Finance panel → Peserta Didik → tombol "+ Tambah"

**Data yang diisi saat membuat:**
- Nama Lengkap
- Jenis Kelamin
- Pendidikan Sebelumnya *(opsional)*
- Masuk di Kelas *(opsional, keterangan teks bebas)*
- Catatan Tambahan *(opsional)*

**Apa yang terjadi setelah disimpan:**
- Peserta didik tersimpan dengan `is_active = false`
- Muncul di tab **"Tidak Aktif"**
- Belum bisa menerima tagihan

> **Catatan:** Tab "Data Kelas" (untuk menambah enrollment) hanya muncul saat **Edit**, bukan saat Create. Jadi enrollment harus ditambahkan di langkah berikutnya.

---

## 2. Mendaftarkan Peserta Didik ke Kelas (Enrollment)

**Di mana:** Finance → Edit Peserta Didik → tab **"Data Kelas"** → tombol "Tambah"

**Data yang diisi:**
| Field | Keterangan |
|-------|-----------|
| Tahun Ajaran | Otomatis ke tahun ajaran aktif. Bisa diubah untuk data historis. |
| Unit Sekolah | Sekolah dalam cabang ini. |
| Kelas | Pilihan kelas berdasarkan sekolah yang dipilih. |

**Apa yang terjadi setelah disimpan:**
1. `StudentEnrollment` dibuat dengan status **`ENROLLED`**
2. `syncActiveStatus()` dipanggil otomatis
3. `is_active` berubah menjadi `true`
4. `branch_id`, `school_id`, `classroom_id` di tabel students diperbarui
5. Peserta didik pindah ke tab **"Aktif"**
6. Tombol "Tambah" enrollment tersembunyi (tidak bisa double enrollment selama masih aktif)

> **Penting:** Peserta didik hanya bisa punya **satu enrollment aktif** sekaligus. Selama mereka Aktif, tombol tambah enrollment tidak muncul.

---

## 3. Status Enrollment

`StudentEnrollmentStatusEnum` memiliki status berikut:

| Status | Arti | Efek ke `is_active` |
|--------|------|---------------------|
| `ENROLLED` | Terdaftar aktif | ✅ Student = Aktif |
| `DRAFT` | Belum dikonfirmasi | ❌ Student = Tidak Aktif |
| `PROMOTED` | Naik kelas (tahun lalu) | ❌ Student = Tidak Aktif |
| `STAYED` | Tinggal kelas (tahun lalu) | ❌ Student = Tidak Aktif |
| `INACTIVE` | Tidak aktif | ❌ Student = Tidak Aktif |
| `GRADUATED` | Lulus | ❌ Student = Tidak Aktif |

Hanya enrollment dengan status `ENROLLED` di **tahun ajaran aktif** yang membuat peserta didik Aktif.

---

## 4. Tagihan (Invoices)

> ⚠️ **Saat ini diblokir sementara** — pembuatan tagihan memerlukan tabel `student_payment_accounts` yang belum diimplementasikan.

Setelah diimplementasikan, alurnya:
- **SPP Bulanan:** Dibuat per bulan untuk peserta didik aktif dengan VA dan nominal SPP
- **Biaya Buku:** Dibuat di awal tahun ajaran baru untuk peserta didik yang tidak di kelas akhir (TK B, 6 SD, 3 SMP, 3 SMA tidak dibuatkan tagihan buku)
- **Pembayaran:** Bisa satu pembayaran untuk beberapa bulan sekaligus

Tagihan dan pembayaran tersimpan permanen — tidak bisa dihapus.

---

## 5. Akhir Tahun Ajaran

*(Fitur ini belum diimplementasikan — akan berupa Wizard step-by-step)*

Alur yang direncanakan:
1. Validasi semua peserta didik sudah punya data kelas
2. Admin review daftar kenaikan kelas (naik/tinggal/lulus per peserta didik)
3. Bulk promote: enrollment lama diubah ke `PROMOTED`/`STAYED`/`GRADUATED`
4. Enrollment baru di tahun ajaran berikutnya dibuat otomatis
5. Tahun ajaran lama dinonaktifkan, tahun ajaran baru diaktifkan

Setelah tahun ajaran berganti:
- Peserta didik kelas akhir yang lulus → `is_active = false`
- Peserta didik yang naik/tinggal → tetap `is_active = true` dengan enrollment baru

---

## 6. Pindah Sekolah / Cabang

*(Fitur TransferStudent belum diimplementasikan)*

Rencana:
- Peserta didik tidak boleh punya tagihan yang belum lunas sebelum dipindah
- Enrollment lama ditandai `TRANSFERRED_OUT`
- Enrollment baru dibuat di cabang/sekolah tujuan
- VA lama dinonaktifkan, VA baru dibuat di cabang baru

---

## Diagram Singkat

```
Finance: Tambah Peserta Didik
        │
        ▼
[Student dibuat, is_active = false]
        │
        ▼
Finance: Edit → tab "Data Kelas" → Tambah Enrollment
        │
        ▼ (StudentEnrollment saved → syncActiveStatus)
[is_active = true, muncul di tab Aktif]
        │
        ├─→ [Tagihan SPP dibuat per bulan]        ← belum aktif
        ├─→ [Tagihan Buku dibuat awal tahun]       ← belum aktif
        │
        ▼ (akhir tahun ajaran)
[Enrollment: PROMOTED / STAYED / GRADUATED]
[is_active = false]
        │
        ▼ (jika naik/tinggal: enrollment baru di tahun berikutnya)
[is_active = true kembali]
```
