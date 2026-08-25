# Kullanıcı Yetkileri — Canlıya Güvenli Geçiş

Bu güncelleme mevcut kayıtları silmez ve mevcut operasyon tablolarını değiştirmez. Yalnızca kullanıcı yetkileri için üç yapı ekler:

- `permissions`: yetki kataloğu
- `user_permissions`: kullanıcıya verilen yetkiler
- `users.permissions_configured`: özel izin listesinin uygulanıp uygulanmadığı

Migration mevcut kullanıcıların erişimini koruyacak şekilde varsayılan izinleri otomatik atar. Mevcut şirket yöneticileri önceki yönetim yetkilerini, personel hesapları önceki kayıt ekleme yetkilerini korur. Araç ve tanker için ekleme, düzenleme ve silme yetkileri ayrı seçeneklerdir; eski toplu yetkiler geriye dönük çalışmaya devam eder.

## Canlıya almadan önce

1. cPanel yedeklerinden ve uygulamanın Sistem Yönetimi > Yedekler ekranından güncel veritabanı yedeği alın.
2. Uygulama dosyalarını yüklemeden önce mevcut `storage` klasörünü değiştirmeyin.
3. Aşağıdaki PHP dosyalarını ve migration dosyasını yükleyin; eski dosyaları silmeyin.
4. cPanel Terminal yoksa migration'ı geçici olarak yalnızca yetkili kurulum ekranı veya hosting destek ekibi üzerinden çalıştırın. `migrate:fresh` kesinlikle çalıştırılmamalıdır.
5. Migration tamamlandıktan sonra `/kullanicilar` ekranını sistem yöneticisi hesabıyla açın.

## cPanel/SSH erişimi varsa

```bash
cd /home/KULLANICI/360.natex.com.tr/santiye-kasa
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
```

Sonuç kontrolü:

```bash
php artisan migrate:status
php artisan route:list --path=kullanicilar
```

## İlk kullanım

1. Kullanıcı Yönetimi ekranını açın.
2. Kullanıcıyı düzenleyin.
3. “Kullanıcı yetkileri” bölümünden izinleri seçin.
4. `Tanker stoklarını görüntüleme` yalnızca görüntüleme sağlar.
5. `Tankere yakıt alımı ekleme` tankere yakıt alımı ekler.
6. `Tanker ekleyebilme`, `Tanker düzenleyebilme`, `Tanker silebilme` ayrı ayrı verilebilir.
7. `Araç ve makineleri görüntüleme` listeyi açar.
8. `Araç / makine ekleyebilme`, `Araç / makine düzenleyebilme`, `Araç / makine silebilme` ayrı ayrı verilebilir.
9. `Kasa hareket raporu PDF/Excel` ve `Yakıt raporu PDF/Excel` izinleri, rapor ekranını görüntüleme yetkisinden bağımsız olarak ayrı ayrı kontrol edilir.
10. Rapor ekranındaki filtreler dışa aktarma bağlantılarına aynen taşınır; personel filtresiyle kişiye özel kasa raporu alınabilir.

## Geri dönüş

Sorun görülürse önce kullanıcı izin listesini eski varsayılanlarına döndürün. Migration geri alınacaksa önce bu migration ile oluşturulan tabloların yedeğini alın; canlıda `migrate:rollback` işlemini onaysız çalıştırmayın. Operasyon kayıtları ayrı tablolarda kaldığı için yetki migration'ı tek başına kasa veya yakıt kayıtlarını silmez.
