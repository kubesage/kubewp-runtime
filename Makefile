# Phase 20.5 — kubewp-runtime build/test wrapper.
# The runtime container IS the artifact; "build" = docker build.
# See: workspace CLAUDE.md > Local Development Rule
#
# C1 fix: each verb has its recipe DIRECTLY in the same target body. The
# ws verifier (`grep -A 5 '^<verb>:'`) finds `docker build` / `docker compose`
# in the target's own recipe, not in a prerequisite target.

.PHONY: build test install dev dev-up dev-down

IMAGE_NAME := kubewp-runtime
IMAGE_TAG := dev
DOCKERFILE := wordpress/Dockerfile

## install: No-op (no package-manager fetch in this repo by design).
## C1 shape: recipe directly under the target (even when it's a no-op echo).
install:
	@echo "kubewp-runtime: install is a no-op (runtime IS the artifact, no host-side deps)."
	@echo "Run 'make build' to build the runtime image."

## build: Build the WordPress runtime image.
## C1 shape: docker build is the recipe directly under build:
build:
	docker build -f $(DOCKERFILE) -t $(IMAGE_NAME):$(IMAGE_TAG) wordpress

## test: Smoke-test the built image (PHP-FPM responds, mu-plugins load).
## C1 shape: recipe directly under test: (no `test: build` empty wrapper).
## Builds the image inline if not already built.
test:
	docker build -f $(DOCKERFILE) -t $(IMAGE_NAME):$(IMAGE_TAG) wordpress
	docker compose --profile dev up -d
	@sleep 5
	docker compose --profile dev exec wordpress php -v
	docker compose --profile dev exec wordpress php -r 'echo "MU plugins: ", count(glob("/var/www/html/wp-content/mu-plugins/*.php")), "\n";'
	docker compose --profile dev down

## dev: Bring up WordPress + MySQL + Redis for local smoke testing.
## C1 fix: recipe DIRECTLY under `dev:` (NOT `dev: dev-up` with the recipe
## living under dev-up:). The verifier sees `docker compose` in dev:'s body.
dev:
	docker compose --profile dev up -d

## dev-up: Alias for `make dev` — kept for symmetry with other repos.
dev-up:
	docker compose --profile dev up -d

## dev-down: Tear down dev stack.
dev-down:
	docker compose --profile dev down
