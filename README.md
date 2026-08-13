# Şantiye Takip

Tek şantiye için TL bazlı gelir–gider, özel belge, araç/makine yakıtı, bakım/onarım, sayaç ve denetim takip uygulamasıdır. Laravel 12 ve PHP 8.3+ kullanır; Blade/CSS arayüzü Node veya Vite gerektirmez.

## Yetkiler

- Personel bütün aktif kayıtları görür ve yeni kayıt ekler; kaydettiği hareketi değiştiremez veya silemez.
- Şirket yöneticisi gerekçe girerek düzeltme/silme yapar, personeli yönetir ve denetim geçmişini görür.
- Sistem yöneticisi şirket yöneticisinden izole bir hesapla yazılım/şirket adını, kategorileri ve sol menü bölümlerini yönetir.
- Pasif hesapların yeni ve mevcut oturumları engellenir. Son aktif yönetici korunur.
- Belgeler `storage/app/private/documents` altında rastgele adlarla saklanır ve yalnızca giriş gerektiren controller üzerinden indirilir.
- Bakım/onarım kayıtları araç veya makineye bağlanır; maliyet isteğe bağlı olarak kasaya gider yazılır ve sonraki bakım tarihi ya da km/saat değeri izlenir.
- Her araç ve makinede kilometre ile çalışma saati birlikte kaydedilir; iki sayaç için kronolojik kontrol ve bakım eşiği ayrı uygulanır.
- Araç ve makine yakıtları plaka bazlı operasyonel maliyet olarak izlenir; yakıt tutarı ana kasa bakiyesinden düşülmez.
- İlk kurulumda yalnızca Tanker 1 oluşturulur; yönetici ihtiyaç duydukça Tanker Yönetimi ekranından yeni tanker ekler. Her aktif tanker stok, yakıt ve raporlarda ayrı izlenir. Tankere yapılan satın alma kasadan düşer; araç/makine ikmali kasayı tekrar etkilemeden tanker stokunu azaltır ve tankerin son alış fiyatı araca maliyet olarak işlenir.

## Kurulum

Web kurulumu için dosyaları sunucuya yükleyin, document root’u `public/` yapın ve `/install` adresini açın. Ayrıntılar [INSTALL.md](INSTALL.md), [CWP.md](CWP.md) ve [CPANEL.md](CPANEL.md) dosyalarındadır.

Manuel geliştirme kurulumu:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Yerel/test seed parolası `Local-Admin-2026!` değeridir. Üretimde bu davranış kapalıdır; güçlü `ADMIN_PASSWORD` zorunludur.
İsteğe bağlı ayrı sistem hesabı `SYSTEM_ADMIN_NAME`, `SYSTEM_ADMIN_USERNAME` ve güçlü `SYSTEM_ADMIN_PASSWORD` değerleriyle oluşturulur. Web installer’ın ilk hesabı sistem yöneticisidir.

## Doğrulama

```bash
composer validate
php artisan test
php artisan route:list
php artisan view:cache
```

Üretim güvenliği ve yedekleme için [SECURITY.md](SECURITY.md) dosyasını okuyun.

## Üretim paketi

Windows/PowerShell ortamında geliştirme ve yerel verileri dışarıda bırakan üretim ZIP'ini oluşturmak için:

```powershell
.\scripts\build-production-zip.ps1
```

Paket `dist/` altında oluşturulur. Canlıya almadan önce [LIVE_INSTALL_CHECKLIST.md](LIVE_INSTALL_CHECKLIST.md) listesini uygulayın.
