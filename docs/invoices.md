# Tagihan & Pembayaran (Invoices & Payments)

> ⚠️ **Status saat ini:** Pembuatan tagihan baru diblokir sementara karena tabel `student_payment_accounts` belum diimplementasikan. Tagihan yang sudah ada dari migrasi data lama tetap bisa dilihat dan dibayar.

---

## Jenis Tagihan

### 1. SPP Bulanan (Monthly Fee)
- Dibuat **per bulan** untuk setiap peserta didik aktif
- Peserta didik bisa punya tunggakan beberapa bulan sekaligus
- Pembayaran bisa mencakup **beberapa bulan sekaligus** dalam satu transaksi

### 2. Biaya Buku (Book Fee)
- Dibuat **per tahun ajaran**
- **Tidak** dibuatkan untuk peserta didik di kelas akhir (TK B, 6 SD, 3 SMP, 3 SMA) karena mereka akan lulus
- Bisa diperbarui nominalnya tanpa membuat tagihan baru

---

## Status Tagihan

| Status | Arti |
|--------|------|
| `UNPAID` | Belum dibayar |
| `PAID` | Sudah dibayar |
| `VOID` | Dibatalkan / tidak berlaku |

---

## Alur Pembayaran (saat ini)

**Di mana:** Finance panel → kartu peserta didik → tombol **"Bayar"**

### Bayar SPP Bulanan
1. Klik **Bayar → Uang Sekolah**
2. Centang bulan-bulan yang ingin dibayar
3. Sistem menghitung total otomatis
4. Isi denda (otomatis dari konfigurasi, bisa diubah) dan diskon jika ada
5. Pilih metode pembayaran
6. Tambah keterangan *(opsional)*
7. Submit → semua invoice yang dicentang berubah ke `PAID`

### Bayar Biaya Buku
1. Klik **Bayar → Uang Buku**
2. Pilih tagihan buku yang ingin dibayar
3. Pilih metode pembayaran
4. Submit

---

## Mencetak Kuitansi SPP

**Di mana:** Finance panel → kartu peserta didik → tombol **"Cetak → Uang Sekolah"**

1. Pilih tahun ajaran
2. Centang bulan-bulan yang ingin dicetak *(hanya yang sudah PAID)*
3. Submit → PDF di-generate dan bisa didownload

---

## Virtual Account (VA)

> ⚠️ **Belum aktif** — akan diimplementasikan bersama `student_payment_accounts`.

Rencananya setiap peserta didik akan punya VA per cabang (bukan per jenis tagihan). Satu pembayaran VA bisa mencakup beberapa tagihan sekaligus.

---

## Denda (Fine)

Denda dihitung otomatis berdasarkan:
- **Hari melewati tanggal jatuh tempo** × nominal denda harian
- Konfigurasi denda per jenis tagihan (akan disimpan di `fee_types` table)

Saat ini denda dikonfigurasi di `config('setting.fine')`.

---

## Fingerprint (Duplikasi Prevention)

Setiap invoice punya **fingerprint** unik berdasarkan kombinasi:
- Jenis tagihan
- ID peserta didik
- Tahun ajaran
- Bulan *(khusus SPP)*

Jika sistem mencoba membuat invoice yang sudah ada (fingerprint sama), sistem memunculkan peringatan — bukan error — sehingga admin tidak bingung.

---

## Data Tagihan Tidak Bisa Dihapus

Semua tagihan dan pembayaran disimpan permanen. Ini penting untuk:
- Audit keuangan
- Laporan historis per tahun ajaran
- Riwayat pembayaran peserta didik yang sudah lulus

Untuk membatalkan tagihan, gunakan status `VOID`.
