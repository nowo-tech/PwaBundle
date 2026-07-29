.PHONY: help up-symfony8 down-symfony8 install-symfony8 shell-symfony8 test-symfony8 release-check demo-smoke update-bundle-symfony8

help:
	@echo "PWA Bundle — Demos"
	@echo ""
	@echo "Symfony 8.1 (port 8025):"
	@echo "  up-symfony8 down-symfony8 install-symfony8 shell-symfony8 test-symfony8"
	@echo ""
	@echo "  release-check          Verify demo health (HTTP 200 + manifest)"
	@echo "  demo-smoke             REQ-TEST-011: boot demo + HTTP 200 → down"
	@echo "  update-bundle-symfony8 Sync bundle autoload in demo"

up-symfony8:
	@$(MAKE) -C symfony8 up

down-symfony8:
	@$(MAKE) -C symfony8 down

install-symfony8:
	@$(MAKE) -C symfony8 install

shell-symfony8:
	@$(MAKE) -C symfony8 shell

test-symfony8:
	@$(MAKE) -C symfony8 test

update-bundle-symfony8:
	@$(MAKE) -C symfony8 update-bundle

release-check:
	@$(MAKE) up-symfony8
	@$(MAKE) update-bundle-symfony8
	@PORT=$$(grep "^PORT=" symfony8/.env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep "^PORT=" symfony8/.env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=8025; \
	curl -sf "http://localhost:$$PORT/" >/dev/null && \
	curl -sf "http://localhost:$$PORT/manifest.webmanifest" | grep -q '"name"' && \
	curl -sf "http://localhost:$$PORT/sw.js" | grep -q 'nowo-pwa' && \
	echo "Symfony 8 PWA demo healthcheck OK (port $$PORT)" || \
	(echo "Symfony 8 PWA demo healthcheck failed" && exit 1)

# REQ-TEST-011: boot primary demo and expect HTTP 200
demo-smoke:
	@PORT=$$(grep "^PORT=" symfony8/.env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=8025; \
	echo "Demo smoke: symfony8 (http://127.0.0.1:$$PORT)"; \
	$(MAKE) up-symfony8 || exit 1; \
	sleep 12; \
	code=$$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:$$PORT" 2>/dev/null || echo "000"); \
	$(MAKE) down-symfony8 2>/dev/null || true; \
	if [ "$$code" != "200" ]; then echo "symfony8 failed with HTTP $$code"; exit 1; fi; \
	echo "symfony8 OK (HTTP $$code)"

# REQ-MAKE-008: update-deps-all (REQ-MAKE-008)
DEMOS := symfony8
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/..)
# Optional: monorepo helper absent on standalone GitHub Actions checkout (REQ-MAKE-009).
-include $(BUNDLE_ROOT)/../.scripts/Makefile.demo-aggregate-update-deps.mk
