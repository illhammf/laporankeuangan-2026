### ⚠️ Noted:
Readme ini dibuat secara otomatis menggunakan skrip start.sh dengan template yang sudah disiapkan. 
Anda bisa mengeditnya sesuai kebutuhan setelah proyek dibuat

<div align="center">

# 🚀 laporankeuangan

### Laravel Boilerplate Project 2026

Built with ❤️ using Laravel, Docker, WSL & Filament

![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-2496ED?style=for-the-badge&logo=docker&logoColor=white)
![MariaDB](https://img.shields.io/badge/MariaDB-003545?style=for-the-badge&logo=mariadb&logoColor=white)
![Filament](https://img.shields.io/badge/Filament-v3-F59E0B?style=for-the-badge)

[![GitHub](https://img.shields.io/badge/Open-GitHub-black?style=for-the-badge&logo=github)](https://github.com/illhammf/laporankeuangan-2026)

</div>

---

## 📖 Tentang Project

**laporankeuangan** merupakan aplikasi berbasis Laravel yang dibuat menggunakan **Ilham Boilerplate 2026**.

Project ini sudah dikonfigurasi dengan berbagai kebutuhan modern untuk pengembangan aplikasi web.

---

## ✨ Fitur Bawaan

- ✅ Laravel
- ✅ Docker Environment
- ✅ Nginx Web Server
- ✅ PHP 8.3
- ✅ MariaDB
- ✅ Filament Admin Panel
- ✅ GitHub Integration
- ✅ WSL Ubuntu Support
- ✅ HTTPS Local Domain
- ✅ Seeder & Migration Ready

---

## 🚀 Quick Start

### Menjalankan Docker

```bash
docker compose up -d
```

atau

```bash
dcu
```

### Masuk Container PHP

```bash
docker compose exec php bash
```

### Menjalankan Migration

```bash
php artisan migrate
```

### Menjalankan Seeder

```bash
php artisan db:seed
```

### Mematikan Docker
```bash
docker compose down
```

atau

```bash
dcd
```

---

## 🌐 URL Aplikasi

### Website Proyek

```text
https://laporankeuangan.test
```

### Admin Panel

```text
https://laporankeuangan.test/admin
```

---

## 🐳 Docker Services

| Service | Port |
|----------|----------|
| Nginx | 80 / 443 |
| PHP-FPM | Internal |
| MariaDB | 13306 |

---

## 📂 Struktur Project

```text
app/
bootstrap/
config/
database/
public/
resources/
routes/
storage/
tests/
```

---

## 🛠️ Tech Stack

| Teknologi | Digunakan Untuk |
|------------|------------|
| Laravel | Backend Framework |
| PHP | Server Side Language |
| MariaDB | Database |
| Docker | Containerization |
| Nginx | Web Server |
| Filament | Admin Panel |
| WSL Ubuntu | Development Environment |
| GitHub | Version Control |

---

## 📋 Checklist Pengembangan

- [ ] Setup Database
- [ ] Setup Authentication
- [ ] Membuat Migration
- [ ] Membuat Seeder
- [ ] Membuat Admin Panel
- [ ] Deploy Production
- [ ] Tambahkan Fitur Lainnya

---

## Command Line Tools
| Command | Deskripsi |
|---------|-----------|
| `dcu` | Jalankan Docker Compose |
| `dcd` | Matikan Docker Compose |
| `dca` | Masuk ke dalam kontainer PHP dan jalankan perintah Artisan |
| `dcp "pesan"` | Git add, commit, pull --rebase, dan push dengan pesan commit |
| `dcm ModelName` | | Buat model Laravel lengkap dengan migration, seeder, controller, policy, dan resource Filament |
| `dcr ModelName` | Hapus file model, migration, seeder, controller, policy, dan resource Filament untuk model yang diberikan |
| `code .` | Buka proyek di Visual Studio Code |

---

## 👨‍💻 Developer

**illhammf**

Generated automatically using:

```text
Ilham Boilerplate 2026
```

---

## 🎯 Quote of The Project

> "Konsistensi Mengalahkan Bakat Saat Bakat Tidak Muncul."

🔥 Happy Coding!!!
