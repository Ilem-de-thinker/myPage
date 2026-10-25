# MyPage.ng PWA

Both platform and merchant origins expose their own app shell:

- Platform: `/manifest.webmanifest`, `/service-worker.js`, `/offline`.
- Merchant: the same paths on the merchant subdomain or custom domain after the
  existing custom-domain forwarding middleware resolves the storefront.

The merchant manifest is generated from `StorePage` settings and
`ThemeTokens`: name, description, brand theme color, paper background, scope,
portrait orientation, and 192/512 maskable PNG icons. Merchant-uploaded logos
are rendered into the icon canvas; stores without a logo receive a branded
monogram fallback.

The worker is origin- and root-scoped. It never caches POST, authenticated,
dashboard, admin, API, login, or register requests. Public HTML is
network-first with the branded offline page as fallback; CSS, JS, fonts and
images use stale-while-revalidate. Cache names are versioned and old versions
are deleted on activation. Updates show a refresh toast.

The shared platform and storefront layouts register the worker, expose manifest
and Apple PWA metadata, and provide a delayed install prompt. Android uses
`beforeinstallprompt`; iOS receives the Share → Add to Home Screen guidance.
Dismissal is remembered in local storage and standalone mode suppresses the
prompt.

Test locally with `npm run build`, open the platform and a published merchant
origin over HTTPS (or localhost), then inspect Application → Manifest and
Service Workers in DevTools. Custom domains must serve the forwarded merchant
origin consistently so `/service-worker.js` keeps root scope on that domain.
