# Şantiye Takip Canlı Sistem Dayanıklılığı

## Günlük veritabanı yedeği

Uygulama her gün 02:30 için `santiye:backup` görevini planlar. cPanel > Cron Jobs ekranında doğrudan günlük yedek komutu olarak şunu ekleyin:

```text
30 2 * * * cd /home/natexcom/360.natex.com.tr/santiye-kasa && /usr/local/bin/php artisan santiye:backup >> /dev/null 2>&1
```

Hosting PHP yolu farklıysa cPanel'de gösterilen PHP CLI yolunu kullanın. Yedekler `storage/app/private/backups` altında tutulur; web üzerinden erişilemez. Yedek, MySQL `--single-transaction` ile alınır, önce `.part` yazılır, başarılı olunca kesin adına taşınır ve SHA-256 dosyası oluşturulur. Varsayılan saklama süresi 14 gündür.

İlk kurulumdan sonra Sistem Yönetimi > Veritabanı Yedekleri bölümündeki **Şimdi Yedek Al** düğmesiyle mekanizmayı sınayın ve oluşan dosyayı indirip ayrı bir yerde saklayın.

Canlı `.env` içinde aşağıdaki servislerin dosya tabanlı olduğundan emin olun; bu ayar kısa MySQL kesintilerinde giriş ekranının doğrudan 500 hatasına düşmesini engeller:

```text
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

Sunucu kaybına karşı cPanel/JetBackup üzerinden ayrıca günlük hesap yedeği ve mümkünse farklı sunucuya kopya etkinleştirilmelidir. Aynı sunucudaki uygulama yedeği tek başına felaket kurtarma değildir.

## Sağlık kontrolü

`https://360.natex.com.tr/health` adresi veritabanı ve yazılabilir çalışma klasörlerini sınar. HTTP 200 sağlıklı, HTTP 503 müdahale gerekiyor anlamına gelir. Harici izleme servisi bu adresi 5 dakikada bir kontrol etmelidir.

## Canlı güncelleme kuralı

- Güncellemeden önce veritabanı ve dosya yedeği doğrulanır.
- Paket hiçbir zaman `.env`, `storage/app/private`, log veya canlı veritabanı içermez.
- Migration varsa önce yedek alınır; `migrate:fresh`, tablo silme ve geri dönüşü olmayan komutlar canlıda kullanılmaz.
- ZIP doğrudan uygulama klasörü üzerine açılır; bakım penceresi dışında büyük sürüm geçişi yapılmaz.
- Güncellemeden sonra `/health`, `/giris` ve temel kayıt ekranları kontrol edilir.

## Geri yükleme

Geri yükleme yalnızca doğrulanmış `.sql.gz` ve aynı tarihe ait belge yedeğiyle yapılmalıdır. Canlı veritabanının üzerine aktarmadan önce mevcut durum ayrıca yedeklenmeli ve işlem mümkünse yeni/boş bir veritabanında prova edilmelidir.
