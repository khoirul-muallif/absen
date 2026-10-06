# Instruksi Kerja — absensi-app (untuk DeepSeek Harness)

> File ini adalah PERINTAH operasional, bukan narasi. Jangan digabung dengan
> `docs/QUICK_CONTEXT.md`. Dokumentasi naratif tetap di `docs/` — file ini
> hanya mengatur PERILAKU agent.

## Sebelum bertindak
- Baca `docs/todo.md`. Kerjakan **HANYA** item yang aku sebut eksplisit.
- Baca `docs/QUICK_CONTEXT.md` bagian "Batasan & Larangan Eksplisit"
  sebelum menyentuh model, controller, atau Filament Resource.
- Kalau permintaanku ambigu, **TANYA dulu**. Jangan berasumsi.
- Kalau ragu antara dua pendekatan, sajikan keduanya + rekomendasi.

## Setelah mengedit
- Jalankan test yang relevan saja: `php artisan test --filter=<NamaTest>`.
  Jangan jalankan full suite kecuali aku minta.
- Kalau menyentuh `Shift`, `Absensi`, `Jadwal`, atau `Karyawan`:
  jalankan `vendor/bin/phpstan analyse` juga.
- **JANGAN commit.** Aku yang commit manual lewat editor.

## Larangan operasional (keras)
- **JANGAN** `php artisan migrate:fresh`, `db:wipe`, atau sejenisnya tanpa
  konfirmasi eksplisit dariku.
- **JANGAN** ubah file di `database/migrations/`.
- **JANGAN** ubah `database/seeders/KaryawanShiftSeeder.php` jadi open-ended.
- **JANGAN** "sekalian fix" bug yang tidak aku sebut. Kalau menemukan bug
  lain: **LAPORKAN di akhir sesi**, jangan perbaiki.
- **JANGAN** refactor file yang tidak berkaitan dengan task. Perubahan
  seminimal mungkin.
- **JANGAN** pakai `TimePicker::native(false)` — sudah jadi bug fase 25.
- **JANGAN** ubah `KuotaCuti::sisaUntuk()` jadi return `int` atau `?? 0`.
- **JANGAN** akses `$shift->jam_masuk` langsung — pakai `jamMasukString()`.

## Konvensi wajib
- Scaffolding baru: **selalu** `php artisan make:*`. Jangan tulis file manual.
- Bahasa respons: **Indonesia**.
- Setiap perubahan logis = 1 commit terpisah (aku yang commit).
- Sebelum mengubah file, **baca file aslinya dulu**. Jangan menempel blok
  berdasarkan tebakan — sudah dua kali fatal error karena ini.
- Kalau bikin migration baru: ingatkan aku update `docs/SCHEMA.md` di akhir.

## Referensi
- Konteks cepat: `docs/QUICK_CONTEXT.md`
- Detail teknis: `docs/PROJECT_CONTEXT.md`
- Skema DB: `docs/SCHEMA.md`
- Riwayat & alasan keputusan: `docs/CHANGELOG.md`
- Task aktif: `docs/todo.md`
