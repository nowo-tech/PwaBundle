# Makefile for Symfony 8.1 Demo (PWA Bundle)

# Prefer Compose V2; absolute docker path avoids shadowing by local docker/ when PATH has "." (REQ-MAKE-010).
DOCKER_BIN := $(shell command -v docker 2>/dev/null)
ifeq ($(DOCKER_BIN),)
COMPOSE ?= docker-compose
else
COMPOSE ?= $(shell $(DOCKER_BIN) compose version >/dev/null 2>&1 && echo "$(DOCKER_BIN) compose" || echo "docker-compose")
endif
SERVICE_PHP = php

.PHONY: help up down build install shell test update-bundle ensure-up ensure-env test-e2e demo-screenshots

PLAYWRIGHT_IMAGE ?= mcr.microsoft.com/playwright:v1.63.0-jammy

help:
	@echo "Symfony 8.1 Demo - PWA Bundle"
	@echo ""
	@echo "  make up             Start containers"
	@echo "  make down           Stop containers"
	@echo "  make install        Install dependencies"
	@echo "  make shell          Open shell in PHP container"
	@echo "  make test           Run demo tests"
	@echo "  make update-bundle  Sync bundle autoload and clear cache"
	@echo "  make test-e2e       Playwright e2e against the running demo (REQ-DEMO-013)"
	@echo "  make demo-screenshots  Re-capture docs/images/demo/*.webp (REQ-DEMO-013)"

up: ensure-env
	@echo "Installing dependencies (one-off container: the worker-mode server needs vendor/ before it starts)..."
	@$(COMPOSE) run --rm -T --no-deps --entrypoint composer -e COMPOSER_MEMORY_LIMIT=-1 $(SERVICE_PHP) install --no-interaction --no-scripts
	@$(COMPOSE) up -d
	@echo "Waiting for container..."
	@sleep 5
	@$(COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 $(SERVICE_PHP) composer install --no-interaction
	@$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console assets:install public --symlink || true
	@PORT=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep "^PORT=" .env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	echo "Demo started at: http://localhost:$$PORT"

down:
	@$(COMPOSE) down

build:
	@$(COMPOSE) build --no-cache

ensure-env:
	@test -f .env || (cp .env.example .env && echo "Created .env from .env.example")

ensure-up:
	@if ! $(COMPOSE) exec -T $(SERVICE_PHP) true 2>/dev/null; then \
		$(MAKE) up; \
	fi

install: ensure-up
	@$(COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 $(SERVICE_PHP) composer install --no-interaction

shell: ensure-up
	@$(COMPOSE) exec $(SERVICE_PHP) sh

test: ensure-up update-bundle
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer test || echo "No demo tests configured"

update-bundle: ensure-up
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer dump-autoload --optimize
	@$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console assets:install public --symlink
	@$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console cache:clear --env=dev --no-warmup || true

BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/../..)
# Optional: monorepo helper absent on standalone GitHub Actions checkout (REQ-MAKE-009).
-include $(BUNDLE_ROOT)/../.scripts/Makefile.demo-update-deps.mk

test-e2e: ensure-up
	@mkdir -p e2e/node_modules
	@PORT=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep "^PORT=" .env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	docker run --rm --network host \
		-v "$(CURDIR)/e2e:/e2e" -w /e2e \
		-e DEMO_BASE_URL=http://127.0.0.1:$$PORT \
		$(PLAYWRIGHT_IMAGE) \
		bash -lc 'npm install --no-fund --no-audit && npx playwright test --project=chromium'

demo-screenshots: ensure-up
	@mkdir -p ../../docs/images/demo e2e/node_modules
	@PORT=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep "^PORT=" .env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	docker run --rm --network host \
		-v "$(CURDIR)/e2e:/e2e" \
		-v "$(CURDIR)/../../docs/images/demo:/screenshots" \
		-w /e2e \
		-e DEMO_BASE_URL=http://127.0.0.1:$$PORT \
		-e SCREENSHOT_DIR=/screenshots \
		$(PLAYWRIGHT_IMAGE) \
		bash -lc 'npm install --no-fund --no-audit && npx playwright test --project=screenshots && node scripts/to-webp.mjs /screenshots'
	@echo "Screenshots written under docs/images/demo/ (WebP)"
