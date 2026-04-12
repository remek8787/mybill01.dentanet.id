# MYBILL RT/RW NET

Fondasi aplikasi billing RT/RW Net berbasis **PHP + SQLite** tanpa phpMyAdmin.

## Fitur awal
- Login admin / staff
- Data paket internet
- Data pelanggan
- Generate invoice bulanan
- Pembayaran invoice + cetak invoice / kwitansi
- Setting API yang bisa diubah dari panel admin

## Login awal
- `admin / admin123`

## Database
- Menggunakan file `database.sqlite`
- Tabel dibuat otomatis saat aplikasi pertama kali dibuka

## Catatan
- App ini disiapkan supaya nanti gampang dihubungkan ke API provider / router / billing external.
- Endpoint, token, username, password, dan secret API bisa diatur dari menu **Pengaturan**.
