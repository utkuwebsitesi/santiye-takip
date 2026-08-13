<?php

return [
    /*
     * Mobil oturumlar tarayıcı çerezinden bağımsızdır. Bu süre, cihaz kaybolsa
     * dahi kullanılmayan bir erişim anahtarının geçerliliğini sınırlar.
     */
    'token_idle_minutes' => (int) env('MOBILE_TOKEN_IDLE_MINUTES', 15),
    'token_max_days' => (int) env('MOBILE_TOKEN_MAX_DAYS', 30),
    'captcha_minutes' => (int) env('MOBILE_CAPTCHA_MINUTES', 10),
];
