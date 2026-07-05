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

When Flex is enabled **and** the recipe is available from the Symfony recipe index, Flex registers:

- `config/packages/nowo_pwa.yaml`
- `config/routes/nowo_pwa.yaml` (or equivalent route import)

It also publishes bundle public assets on `assets:install`.

#### Recipe availability

The bundle ships a Flex recipe under [`.symfony/recipes/nowo-tech/pwa-bundle/1.0.0/`](../.symfony/recipes/nowo-tech/pwa-bundle/1.0.0/) in this repository. Until it is published to [symfony/recipes-contrib](https://github.com/symfony/recipes-contrib), automatic Flex installation may not run after `composer require`.

**Options:**

1. **Manual registration** — follow [Manual registration](#manual-registration) below (copy `config/packages/nowo_pwa.yaml` from the recipe as a starting point).
2. **Custom Flex endpoint** — point your project's `extra.symfony.endpoint` to a fork or private index that hosts the recipe (advanced).
3. **Contrib submission** — track [symfony/recipes-contrib](https://github.com/symfony/recipes-contrib) for `nowo-tech/pwa-bundle` recipe availability.

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

### AssetMapper

If your app uses [Symfony AssetMapper](https://symfony.com/doc/current/frontend/asset_mapper.html), the bundle still registers the `nowo_pwa` asset package. Run `assets:install` once so `pwa.js` is published to `public/bundles/pwa/pwa.js`, or copy the built file into your own asset pipeline. The head partial loads it via `asset('pwa.js', 'nowo_pwa')`.

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

Configure [trusted proxies](https://symfony.com/doc/current/deployment/proxies.html) when the app runs behind a reverse proxy and `manifest.absolute_start_url` is `true` (default) — see [Security — Trusted proxies](SECURITY.md#trusted-proxies).

## Demo

To explore the bundle locally, clone the repository and run the FrankenPHP demo — see [demo/README.md](../demo/README.md) and [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md).

## Next steps

- [Configuration](CONFIGURATION.md)
- [Usage](USAGE.md)
