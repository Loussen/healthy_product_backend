# Shared hosting: strip `/public` from public URLs

Behind Cloudflare / php-fpm, root `.htaccess` alone often still lets Laravel
answer `/public/...` with `200`. App middleware is the reliable fix.

## Wired

`StripPublicUrlPrefix` is prepended in `bootstrap/app.php`. It 301s:

`https://example.com/public/...` → `https://example.com/...`

using `APP_URL` + path (not `redirect()->to()`, which would re-add `/public`).

```bash
php artisan optimize:clear
curl -sI 'https://YOUR_DOMAIN/public/?n=1' | egrep -i 'HTTP/|location'
# expect: 301 + location without /public
```

Optional root rewrite: `htaccess.root.example` (next to `public/`, not inside it).

Never create route-named folders under `public/` that clash with routes.
