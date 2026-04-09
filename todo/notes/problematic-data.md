# Problematic Data — Migration Issues

Preserved from old finance/backlog.md. Students with data issues from migration:

```
"01km2epzrfb0rdxcqc41fhxw1g" => "Miracle J Letticia"
"01km2eq0qaqtq8eywmvz1yb57y" => "Frishila Maria Rajagukguk"
"01km2eq2trz2vktrr9hwwhkvcf" => "Brian Putra Raden Simbolon" -> bermasalah kelasnya saat migrasi -> old id = 668
"01km2eq2vdqyhkhtdy2rz4cgr2" => "Eudora Kameaprasita Hudaya"
"01km2eq2w8jgazvs792q5tkrv0" => "Smith Dyto Siagian"
"01km2eq2wz2b1mk1aykw29g6t1" => "Tri Jeniyanti Linggi Allo"
```

## Notes from interview (2026-04-01)

- Invoice student_name (denormalized) used for print reporting
- Invoice student.name (live) used for table grouping so name changes are reflected
- Old unpaid invoices keep their original amount (e.g., kelas 1 = 500, kelas 2 = 600)
- Virtual account only for active fee, not for old unpaid invoices — those pay via EDC
