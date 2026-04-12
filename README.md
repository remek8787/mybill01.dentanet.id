# MYBILL RT/RW NET

Fondasi aplikasi billing RT/RW Net berbasis **PHP + SQLite** tanpa phpMyAdmin, sekarang sudah disiapkan untuk workflow **billing pelanggan MikroTik**.

## Fitur inti saat ini
- Login admin / staff
- Data paket internet + mapping profile MikroTik
- Data pelanggan lengkap: nama, alamat, no HP, area, paket, username PPPoE / secret, profile
- Koneksi **MikroTik RouterOS API** dengan host/port yang bisa diubah dari panel admin
- Baca daftar PPP secret dan profile dari MikroTik
- Enable / disable secret dan pindah profile dari panel web
- Sinkron status isolir pelanggan dari MikroTik
- Generate invoice bulanan
- Filter jumlah pelanggan belum bayar per bulan
- Cetak invoice + barcode nomor invoice
- Tutorial ringkas di dashboard dan halaman MikroTik

## Login awal
- `admin / admin123`

## Database
- Menggunakan file `database.sqlite`
- Tabel dibuat otomatis saat aplikasi pertama kali dibuka
- Kolom tambahan untuk MikroTik akan dimigrasikan otomatis saat app dijalankan

## Alur cepat admin
1. Isi koneksi MikroTik di **Pengaturan**
2. Buka **MikroTik API** lalu tes koneksi
3. Tambah **Paket** dan mapping profile jika ada
4. Tambah **Pelanggan** lalu pilih secret PPPoE / profile
5. Buka **Generate Billing** untuk buat invoice bulanan
6. Kelola tagihan dari menu **Invoice** dan print invoice barcode

## Catatan
- Status isolir saat ini mengikuti status **disabled** pada PPP secret MikroTik.
- Struktur ini masih fondasi awal dan siap di-improve ke auto isolir / reminder / integrasi lain berikutnya.
