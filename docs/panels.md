# Panel-Panel dalam Sistem

Filamic terdiri dari 4 panel terpisah, masing-masing untuk peran yang berbeda.

---

## Admin Panel (`/admin`)

**Untuk:** Super Admin / IT

Panel pengaturan master data. Semua konfigurasi dasar sistem dikelola di sini.

### Fitur

| Modul | Fungsi |
|-------|--------|
| **Branches** | Kelola cabang (Batam Center, Batu Aji, dst.) |
| **Schools** | Kelola unit sekolah per cabang (TK, SD, SMP, SMA) |
| **Classrooms** | Kelola kelas per sekolah (VII-A, VIII-B, dst.) |
| **School Years** | Tahun ajaran — hanya 1 yang aktif |
| **School Terms** | Semester 1 dan 2 dalam tahun ajaran |
| **Students** | Lihat/edit data peserta didik (read-heavy, CRUD ada di Finance) |
| **Users** | Kelola akun pengguna dan akses cabang |
| **Positions** | Jabatan staf |
| **Subjects** | Mata pelajaran |
| **Subject Categories** | Kategori mata pelajaran (hierarkis) |
| **Curricula** | Kurikulum |
| **Learning Groups** | Kelompok belajar lintas kelas |
| **Product Categories** | Kategori barang (untuk Supply Hub) |

### Penting
- Admin panel **tidak** digunakan untuk operasional harian keuangan
- Perubahan di Admin (seperti mengaktifkan tahun ajaran baru) berdampak langsung ke Finance panel

---

## Finance Panel (`/finance`)

**Untuk:** Staf Keuangan per Cabang

Panel utama untuk operasional keuangan sehari-hari.

### Multi-Tenancy
Finance panel menggunakan **Branch sebagai tenant** — setiap staf hanya melihat data cabang mereka. Staf yang punya akses ke beberapa cabang bisa switch tenant dari panel.

### Fitur

| Modul | Fungsi |
|-------|--------|
| **Peserta Didik** | Daftar peserta didik dengan kartu ringkasan tagihan. Tab: Semua / Aktif / Tidak Aktif |
| **Tagihan (Invoices)** | Daftar semua tagihan cabang. Filter per bulan, status, kelas. |

### Pada kartu Peserta Didik
Setiap kartu menampilkan:
- Nama dan kelas saat ini
- Ringkasan tagihan yang belum dibayar (SPP + Buku)
- Daftar tagihan detail (bisa di-expand)
- Tombol **Bayar** (SPP / Buku)
- Tombol **Cetak** kuitansi

### Syarat Operasional
Finance panel memerlukan **tahun ajaran aktif** untuk berjalan normal. Jika tidak ada, muncul peringatan.

---

## CMS Panel (`/cms`)

**Untuk:** Staf Konten / Humas

Panel untuk mengelola konten yang ditampilkan ke publik.

### Fitur

| Modul | Fungsi |
|-------|--------|
| **Galeri** | Upload dan kelola foto kegiatan |
| **Kategori Galeri** | Organisasi foto (Kegiatan, Prestasi, dst.) |
| **Event Sekolah** | Publikasikan pengumuman dan jadwal kegiatan |

---

## Supply Hub Panel (`/supply-hub`)

**Untuk:** Staf Logistik / Tata Usaha

Panel pengelolaan barang dan pembelian.

### Multi-Tenancy
Sama seperti Finance — scoped per cabang.

### Fitur

| Modul | Fungsi |
|-------|--------|
| **Orders** | Buat dan kelola pesanan pembelian |
| **Product Items** | Daftar barang dengan stok |
| **Stock Movements** | Catat penerimaan / pengeluaran barang |
| **Suppliers** | Kelola data supplier |

---

## Hubungan Antar Panel

```
Admin Panel
  ├── Membuat: Branch, School, Classroom, SchoolYear, User
  └── Konfigurasi ini digunakan oleh semua panel di bawah

Finance Panel (per Branch)
  ├── Membaca: Branch, School, Classroom, SchoolYear dari Admin
  └── Mengelola: Student, Enrollment, Invoice, Payment

Supply Hub (per Branch)
  ├── Membaca: Branch dari Admin
  └── Mengelola: Orders, Products, Stock, Suppliers

CMS Panel
  └── Mandiri: Galleries, Events
```
