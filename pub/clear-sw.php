<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Clear-Site-Data: "cache", "storage", "executionContexts"');

?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Limpeza de cache AWA</title>
    <style>
        body{font-family:Arial,sans-serif;margin:0;background:#f7f7f7;color:#222}
        main{max-width:680px;margin:48px auto;padding:24px;background:#fff;border-radius:12px;box-shadow:0 8px 28px rgb(0 0 0 / 8%)}
        code{background:#f1f1f1;padding:2px 6px;border-radius:4px}
        a{display:inline-block;margin-top:16px;padding:12px 18px;border-radius:8px;background:#b73337;color:#fff;text-decoration:none;font-weight:700}
        pre{white-space:pre-wrap;background:#f6f6f6;padding:12px;border-radius:8px}
    </style>
</head>
<body>
<main>
    <h1>Cache do navegador limpo</h1>
    <p>Esta resposta enviou <code>Clear-Site-Data</code> para remover cache, storage e service workers antigos deste domínio.</p>
    <pre id="status">Verificando service workers e caches...</pre>
    <a href="/checkout/cart/?verify_clear_site_data=1">Abrir carrinho novamente</a>
</main>
<script>
(function () {
    var status = document.getElementById('status');
    Promise.all([
        ('serviceWorker' in navigator)
            ? navigator.serviceWorker.getRegistrations().then(function (registrations) {
                return Promise.all(registrations.map(function (registration) {
                    return registration.unregister();
                })).then(function () {
                    return registrations.length;
                });
            })
            : Promise.resolve(0),
        ('caches' in window)
            ? caches.keys().then(function (names) {
                return Promise.all(names.map(function (name) {
                    return caches.delete(name);
                })).then(function () {
                    return names.length;
                });
            })
            : Promise.resolve(0)
    ]).then(function (result) {
        status.textContent = 'Service workers removidos: ' + result[0] + '\nCaches removidos: ' + result[1] + '\nAgora abra o carrinho pelo botão abaixo.';
    }).catch(function (error) {
        status.textContent = 'A limpeza por header foi enviada. Verificação JS falhou: ' + error.message;
    });
}());
</script>
</body>
</html>
