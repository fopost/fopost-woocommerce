# ────────────────────────────────────────────────────────────
# FoPost for WooCommerce Makefile
# ────────────────────────────────────────────────────────────

PLUGIN_SLUG  := fopost-woocommerce
VERSION      ?= $(shell grep -i 'Version:' fopost-woocommerce.php | head -1 | sed 's/.*Version:[[:space:]]*//' | tr -d '[:space:]')
DIST_DIR     := dist
BUILD_DIR    := $(DIST_DIR)/$(PLUGIN_SLUG)
ZIP_FILE     := $(DIST_DIR)/$(PLUGIN_SLUG)-$(VERSION).zip

# SVN settings
SVN_URL      := https://plugins.svn.wordpress.org/$(PLUGIN_SLUG)
SVN_DIR      := .svn-wp
SVN_USER     ?= $(shell echo $${WP_ORG_SVN_USERNAME})

.DEFAULT_GOAL := help

# ── Development ──────────────────────────────────────────────

.PHONY: install
install: ## Install all Composer dependencies (dev + prod)
	composer install --prefer-dist

.PHONY: lint
lint: ## Run PHPCS (project standard from phpcs.xml.dist)
	php -d xdebug.mode=off vendor/bin/phpcs

.PHONY: lint-fix
lint-fix: ## Auto-fix PHPCS violations where possible
	php -d xdebug.mode=off vendor/bin/phpcbf

.PHONY: test
test: ## Run PHPUnit tests
	vendor/bin/phpunit

.PHONY: version-check
version-check: ## Verify the plugin header and readme.txt agree on the version
	@header=$$(grep -i '^ \* Version:' fopost-woocommerce.php | head -1 | sed 's/.*Version:[[:space:]]*//' | tr -d '[:space:]'); \
	stable=$$(grep -i '^Stable tag:' readme.txt | head -1 | sed 's/.*Stable tag:[[:space:]]*//' | tr -d '[:space:]'); \
	if [ "$$header" != "$$stable" ]; then \
		echo "Version mismatch: header $$header, readme.txt $$stable"; exit 1; \
	fi; \
	echo "Version $$header"

.PHONY: version-bump
version-bump: ## Set the version everywhere: make version-bump V=x.y.z
	@test -n "$(V)" || (echo "Usage: make version-bump V=x.y.z"; exit 1)
	sed -i.bak -E 's/^( \* Version:[[:space:]]*).*/\1$(V)/' fopost-woocommerce.php && rm fopost-woocommerce.php.bak
	sed -i.bak -E "s/^(define\\('FOPOST_WC_VERSION', ').*(''\\)?.*)/\\1$(V)');/" fopost-woocommerce.php && rm fopost-woocommerce.php.bak
	sed -i.bak -E 's/^(Stable tag:[[:space:]]*).*/\1$(V)/' readme.txt && rm readme.txt.bak
	@$(MAKE) version-check

# ── Build / Release ─────────────────────────────────────────

.PHONY: build
build: clean ## Build distribution zip for WordPress.org
	@echo "==> Building $(PLUGIN_SLUG) v$(VERSION)"

	@# Install production-only dependencies.
	composer install --no-dev --prefer-dist --optimize-autoloader --quiet

	@# Create build directory and sync files.
	@mkdir -p $(BUILD_DIR)
	rsync -rc --exclude-from=".distignore" ./ $(BUILD_DIR)/

	@# Remove dev vendor packages (safety net).
	rm -rf $(BUILD_DIR)/vendor/phpunit \
	       $(BUILD_DIR)/vendor/phpcsstandards \
	       $(BUILD_DIR)/vendor/squizlabs \
	       $(BUILD_DIR)/vendor/wp-coding-standards \
	       $(BUILD_DIR)/vendor/dealerdirect \
	       $(BUILD_DIR)/vendor/staabm \
	       $(BUILD_DIR)/vendor/phar-io \
	       $(BUILD_DIR)/vendor/sebastian \
	       $(BUILD_DIR)/vendor/theseer \
	       $(BUILD_DIR)/vendor/nikic \
	       $(BUILD_DIR)/vendor/myclabs \
	       $(BUILD_DIR)/vendor/bin

	@# Create the zip.
	@echo "==> Creating zip…"
	cd $(DIST_DIR) && zip -rq ../$(ZIP_FILE) $(PLUGIN_SLUG)/

	@# Clean up build directory.
	rm -rf $(BUILD_DIR)

	@# Restore dev dependencies.
	@echo "==> Restoring dev dependencies…"
	composer install --quiet

	@echo "==> Done! $(ZIP_FILE)"
	@echo "    Size: $$(du -h $(ZIP_FILE) | cut -f1)"

.PHONY: clean
clean: ## Remove previous build artifacts
	rm -rf $(DIST_DIR)

.PHONY: release
release: build ## Publish the built zip to the WordPress.org SVN repository (manual)
	@echo "==> WordPress.org publish is manual until the plugin listing is approved."
	@echo "    See CLAUDE.md, section Releasing."

# ── Help ─────────────────────────────────────────────────────

.PHONY: help
help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'
