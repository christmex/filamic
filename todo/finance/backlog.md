- [x] buat tagihan spp bulanan untuk semua siswa
- [ ] buat tagihan spp satuan untuk siswa tertentu
    - [ ] buat test GenerateBulkMonthlyFeeInvoice
    - [ ] buat test MonthlyFeeInvoicesRelationManager
- [ ] buat tagihan uang buku untuk tahun ajaran depan
    - jika dia sudah berada di kelas 6 SD/3 SMP/3 SMA maka tidak bisa membuat tagihan uang buku.
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

- [ ] kenaikan kelas

# BACKLOG

- [ ] ganti protected $casts di Invoice.php ganti jadi function
- [ ] buat global scope untuk lngsung filter berdasrkan branch deh, biar g ada kesalahan
- [ ] DATA BERMASALAH
      // Illuminate\Support\Collection {#8785 ▼ // routes/web.php:52
      // #items: array:6 [▼
      // "01km2epzrfb0rdxcqc41fhxw1g" => "Miracle J Letticia"
      // "01km2eq0qaqtq8eywmvz1yb57y" => "Frishila Maria Rajagukguk"
      // "01km2eq2trz2vktrr9hwwhkvcf" => "Brian Putra Raden Simbolon"
      // "01km2eq2vdqyhkhtdy2rz4cgr2" => "Eudora Kameaprasita Hudaya"
      // "01km2eq2w8jgazvs792q5tkrv0" => "Smith Dyto Siagian"
      // "01km2eq2wz2b1mk1aykw29g6t1" => "Tri Jeniyanti Linggi Allo"
      // ]
      // #escapeWhenCastingToString: false
      // }
