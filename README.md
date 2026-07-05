# PWA Bundle

[![CI](https://github.com/nowo-tech/PwaBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/PwaBundle/actions/workflows/ci.yml) [![codecov](https://codecov.io/gh/nowo-tech/PwaBundle/graph/badge.svg)](https://codecov.io/gh/nowo-tech/PwaBundle) [![Packagist Version](https://img.shields.io/packagist/v/nowo-tech/pwa-bundle.svg?style=flat)](https://packagist.org/packages/nowo-tech/pwa-bundle) [![Packagist Downloads](https://img.shields.io/packagist/dt/nowo-tech/pwa-bundle.svg)](https://packagist.org/packages/nowo-tech/pwa-bundle) [![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE) [![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php)](https://php.net) [![Symfony](https://img.shields.io/badge/Symfony-7.4%2B%20%7C%208.0%2B-000000?logo=symfony)](https://symfony.com) [![GitHub stars](https://img.shields.io/github/stars/nowo-tech/PwaBundle.svg?style=social&label=Star)](https://github.com/nowo-tech/PwaBundle)

> ⭐ **Found this useful?** Give it a **star** on [GitHub](https://github.com/nowo-tech/PwaBundle) so more developers can find it.

Turn any Symfony application into a **Progressive Web App** with a fully configurable manifest, service worker, offline page, and install prompt — no vendor lock-in, no hardcoded assets.

## Features

- **Web App Manifest** generated from `nowo_pwa.yaml` (name, icons, shortcuts, theme, display mode, scope, start URL)
- **Service worker** with configurable cache strategy (`network-first`, `cache-first`, `stale-while-revalidate`), precache URLs, and offline fallback
- **Twig helpers** `nowo_pwa_head()`, `nowo_pwa_install_prompt()`, and `nowo_pwa_install_links()` with route targeting (`all` / `only` / `except`)
- **Override-friendly** Twig templates and translations (**en**, **es**, **fr**, **it**, **pt**, **de**, **nl**; REQ-TWIG-001 / REQ-I18N-001)
- **TypeScript client** for SW registration and install banner (Vite build → `pwa.js`)
- Works with Symfony **7.4+** and **8.x**; no database required

## Installation

```bash
composer require nowo-tech/pwa-bundle
```

```yaml
# config/packages/nowo_pwa.yaml
nowo_pwa:
    manifest:
        name: 'My Application'
        short_name: MyApp
        theme_color: '#0f172a'
        icons:
            - { src: '/icons/icon-192.png', sizes: '192x192', type: image/png }
            - { src: '/icons/icon-512.png', sizes: '512x512', type: image/png }
    service_worker:
        precache_urls: ['/', '/offline']
        offline_url: '/offline'
```

In your base layout:

```twig
<head>
    {{ nowo_pwa_head() }}
</head>
<body>
    {% block body %}{% endblock %}
    {{ nowo_pwa_install_prompt() }}
    {{ nowo_pwa_install_links() }}
</body>
```

See [Installation](docs/INSTALLATION.md) and [Configuration](docs/CONFIGURATION.md).

## Documentation

- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [Usage](docs/USAGE.md)
- [Contributing](docs/CONTRIBUTING.md)
- [Changelog](docs/CHANGELOG.md)
- [Upgrading](docs/UPGRADING.md)
- [Release process](docs/RELEASE.md)
- [Security](docs/SECURITY.md)
- [Engram](docs/ENGRAM.md)
- [Spec-driven development](docs/SPEC-DRIVEN-DEVELOPMENT.md)

### Additional documentation

- [Demo with FrankenPHP](docs/DEMO-FRANKENPHP.md)

## Tests and coverage

```bash
make test
make test-ts
make test-coverage
make release-check   # includes demo healthcheck on port 8025
```

## License

MIT — see [LICENSE](LICENSE).
