# cPanel Kurulumu

MultiPHP Manager’dan PHP 8.3+ seçin. Uygulamayı `public_html` dışında bir dizine yükleyin ve domain/subdomain document root’unu uygulamanın `public/` klasörüne ayarlayın. MySQL Database Wizard ile veritabanı ve kullanıcı oluşturup tüm gerekli yetkileri verin.

Document root değiştirilemiyorsa yalnızca `public/` içeriğini `public_html` içine taşımak yerine hosting sağlayıcısından güvenli document-root yönlendirmesi isteyin. Proje kökünü web’e açmak `.env` sızıntısı riski doğurur.

`storage` ve `bootstrap/cache` yazılabilir olduktan sonra HTTPS üzerinden `/install` çalıştırın. `storage:link` gerekmez.
