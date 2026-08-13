# CWP Kurulumu

PHP sürümünü 8.3 veya üstüne alın ve gerekli eklentileri etkinleştirin. Domain vHost document root’unu proje kökündeki `public/` dizinine yöneltin. Nginx kullanılıyorsa mevcut dosya bulunmadığında istekleri `public/index.php` dosyasına aktarın:

```nginx
location / { try_files $uri $uri/ /index.php?$query_string; }
```

`storage` ve `bootstrap/cache` dizinlerini PHP-FPM kullanıcısının yazabileceği şekilde ayarlayın. HTTPS sonrası `/install` ile kurulumu tamamlayın. Proje kökü, `.env`, `vendor` ve özel belgeler doğrudan web erişimine açılmamalıdır.
