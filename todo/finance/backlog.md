- [x] buat tagihan spp bulanan untuk semua siswa
- [ ] buat tagihan spp satuan untuk siswa tertentu
    - [ ] buat test GenerateBulkMonthlyFeeInvoice
    - [ ] buat test MonthlyFeeInvoicesRelationManager
- [ ] buat tagihan uang buku untuk tahun ajaran depan
    - jika dia sudah berada di kelas 6 SD/3 SMP/3 SMA maka tidak bisa membuat tagihan uang buku.
    - [ ] buat test scope excludeFinalYears di Classroom
    - [ ] buat test scope notInFinalYears di Student
    - [ ] buat test scope hasProblems di Student
    - [ ] buat test scope hasNoProblems di Student
    - [ ] buat test untuk test liststudent tabs
    - [ ] buat test scope bookFeeForNextSchoolYear di Invoice
    - [ ] buat test GenerateBookFeeInvoice
- [x] bayar tagihan uang spp
- [ ] bayar tagihan uang buku
- [ ] import data pembayaran uang spp
- [ ] import data pembayaran uang buku
- [ ] export data pembayaran uang spp untuk di upload di Bank
- [ ] export data pembayaran uang buku untuk di upload di Bank
- [x] print bukti pembayaran uang spp
- [ ] print laporan uang spp
- [ ] print laporan uang buku
- [ ] filter yang punya tagihan saja
- [ ] kita udh pakai laravel action, di migrate data, pakai aja juga action itu klo diperlukan
- [ ] delete all student payment account and all related
- [ ] saat buat tagihan buku, update juga master nominal buku
- [ ] add widget di dasboard finance
- [ ] buat fitur ulangi pembayaran
- [ ] create test for invoice in finance

- [ ] kenaikan kelas
- [ ] cek apakah fungsi relasi belongstoclassroom bentrok / duplikat dengan currentClassroom di model student
- [ ] cek, sebenarnya kalau kita udah punya dan udah make sure fungsi active() di model student kita tidak perlu lagi melakukan pengecekan seperti ini

```php
->whereHas('currentEnrollment')
            ->whereHas('currentPaymentAccount', function ($query) {
                /** @var StudentPaymentAccount $query */
                // @phpstan-ignore-next-line
                $query->eligibleForBookFee();
            })
```

coba deh lihat di GenerateInvoice action pada cek itu, harus dipikirkan lagi itu

# BACKLOG

- [ ] ganti protected $casts di Invoice.php ganti jadi function
- [ ] buat global scope untuk lngsung filter berdasrkan branch deh, biar g ada kesalahan
- [ ] DATA BERMASALAH
      // Illuminate\Support\Collection {#8785 ▼ // routes/web.php:52
      // #items: array:6 [▼
      // "01km2epzrfb0rdxcqc41fhxw1g" => "Miracle J Letticia"
      // "01km2eq0qaqtq8eywmvz1yb57y" => "Frishila Maria Rajagukguk"
      // "01km2eq2trz2vktrr9hwwhkvcf" => "Brian Putra Raden Simbolon" -> bermasalah kelasnya saat migrasi -> old id = 668
      // "01km2eq2vdqyhkhtdy2rz4cgr2" => "Eudora Kameaprasita Hudaya"
      // "01km2eq2w8jgazvs792q5tkrv0" => "Smith Dyto Siagian"
      // "01km2eq2wz2b1mk1aykw29g6t1" => "Tri Jeniyanti Linggi Allo"
      // ]
      // #escapeWhenCastingToString: false
      // }
