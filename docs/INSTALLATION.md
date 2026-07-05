# Installation

This guide covers installing **PWA Bundle** in a Symfony application.

## Requirements

- **PHP** >= 8.2, < 8.6
- **Symfony** ^7.4 || ^8.0
- **symfony/framework-bundle**, **symfony/twig-bundle**, **symfony/routing**, **symfony/yaml**
- **symfony/asset** (recommended) — used by the head partial for `asset('pwa.js', 'nowo_pwa')`

No database or extra PHP extensions are required.

## Install with Composer

```bash
composer require nowo-tech/pwa-bundle
```

## Register the bundle

### With Symfony Flex

When Flex is enabled, the recipe registers:

- `config/packages/nowo_pwa.yaml`
- `config/routes/nowo_pwa.yaml` (or equivalent route import)

It also publishes bundle public assets on `assets:install`.

### Manual registration

1. Register the bundle in `config/bundles.php`:

```php
<?php

return [
    // ...
    Nowo\PwaBundle\PwaBundle::class => ['all' => true],
];
```

2. Create `config/packages/nowo_pwa.yaml` (see [Configuration](CONFIGURATION.md)).

3. Import PWA routes in `config/routes.yaml`:

```yaml
nowo_pwa:
    resource: .
    type: nowo_pwa
```

4. Install public assets:

```bash
php bin/console assets:install
```

## Twig layout

Add the helpers to your base layout:

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

## Icons

Place PNG (or SVG where supported) icons under `public/icons/` and reference them in config:

```yaml
nowo_pwa:
    manifest:
        icons:
            - { src: '/icons/icon-192.png', sizes: '192x192', type: image/png }
            - { src: '/icons/icon-512.png', sizes: '512x512', type: image/png }
```

## HTTPS

Service workers and install prompts require a **secure context** (HTTPS or `localhost`). Plan TLS in production before enabling PWA features.

## Demo

To explore the bundle locally, clone the repository and run the FrankenPHP demo — see [demo/README.md](../demo/README.md) and [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md).

## Next steps

- [Configuration](CONFIGURATION.md)
- [Usage](USAGE.md)
