APLIKASI KONSERVASI ENERGI (PHP + MySQL/MariaDB)

Cara menjalankan (XAMPP):
1. Salin folder ini ke htdocs, misalnya C:\xampp\htdocs\konservasi_energi_ardan
2. Jalankan Apache dan MySQL di XAMPP.
3. Buka phpMyAdmin, buat database bernama konservasi_energi_ardan,
   lalu Import file database/konservasi_energi_ardan.sql
4. Kalau user/password MySQL berbeda dari bawaan XAMPP, ubah di config.php
5. Buka http://localhost/konservasi_energi_ardan/ dan masuk dengan akun di tabel users.

Catatan:
- Password lama yang masih teks biasa otomatis diubah menjadi hash saat akun itu pertama kali login.
- Akun siswa yang username-nya sama dengan NIS otomatis mengisi checklist atas namanya sendiri.
- Level admin: kelola siswa, perangkat, pengguna, checklist, laporan.
  Level guru: lihat siswa dan perangkat, kelola checklist, lihat laporan.
  Level siswa: lihat dan isi checklist.
