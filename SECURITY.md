# Güvenlik

- Üretimde `APP_DEBUG=false`, HTTPS ve güvenli oturum çerezleri zorunludur.
- İlk yönetici parolası en az 12 karakter, büyük/küçük harf ve rakam içermelidir; `.env`, log veya destek mesajlarında paylaşılmamalıdır.
- Kullanıcı parolaları Laravel’in `hashed` cast’i ile saklanır. Audit JSON’undan parola, hash, token ve session alanları çıkarılır.
- Belgeler public dizinde değildir; izin verilen türler JPG/JPEG/PNG/WEBP/PDF ve sınır 5 MB’tır. Rastgele UUID dosya adı kullanılır.
- Günlük veritabanı ve özel belge yedeği alın. Yedekler şifreli, erişim kontrollü ve geri yükleme testi yapılmış olmalıdır.
- `storage/app/installed.lock` üretimde korunmalıdır.
- Giriş formunda 10 dakika geçerli, tek kullanımlık CAPTCHA ve IP tabanlı hız sınırlaması birlikte uygulanır.
- Admin değişiklikleri gerekçe ve eski/yeni değerlerle denetim kaydına alınır; audit kayıtlarının uygulama üzerinden güncelleme/silme yolu yoktur.

## Üretime geçiş kontrolü

Composer bağımlılıkları kilit dosyasından kurulmuş, migration yedeği alınmış, testler geçmiş, debug kapalı, HTTPS açık, document root `public/`, dosya izinleri daraltılmış, varsayılan hesap parolası değiştirilmiş ve `/install` kilitlenmiş olmalıdır.
