# Filamic — Dokumentasi Sistem

Filamic adalah sistem manajemen sekolah multi-cabang untuk jaringan sekolah di Batam. Dibangun dengan Laravel 12 + Filament 5.

---

## Panel-Panel dalam Sistem

| Panel | URL | Untuk Siapa | Fungsi Utama |
|-------|-----|-------------|--------------|
| **Admin** | `/admin` | Super Admin | Pengaturan master data (sekolah, kelas, tahun ajaran, pengguna) |
| **Finance** | `/finance` | Staf Keuangan | Peserta didik, tagihan, dan pembayaran SPP/buku |
| **CMS** | `/cms` | Konten | Galeri foto dan event sekolah |
| **Supply Hub** | `/supply-hub` | Logistik | Pesanan, stok barang, dan supplier |

Finance panel menggunakan **multi-tenancy per cabang** — staf Finance hanya melihat data cabang mereka sendiri.

---

## Dokumen Referensi

- [Alur Peserta Didik](./student-lifecycle.md) — Dari pendaftaran hingga lulus
- [Tahun Ajaran & Semester](./academic-periods.md) — Bagaimana periode akademik bekerja
- [Tagihan & Pembayaran](./invoices.md) — SPP bulanan dan biaya buku

---

## Aturan Dasar Sistem

- Satu **Tahun Ajaran aktif** pada satu waktu — semua data Finance terikat ke tahun ajaran aktif.
- Peserta didik dianggap **Aktif** jika dan hanya jika mereka punya enrollment di tahun ajaran aktif.
- Tagihan tidak bisa dibuat sebelum **student_payment_accounts** diimplementasikan (saat ini diblokir sementara).
- Semua data finansial tersimpan permanen — tidak ada penghapusan histori.
