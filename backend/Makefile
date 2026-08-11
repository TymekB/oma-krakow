DC ?= docker compose
APP = $(DC) exec -T app bash -lc

.PHONY: build up down restart logs install fixtures prices assets landing shell console db reset dev crons

build:
	$(DC) build

up:
	$(DC) up -d

down:
	$(DC) down

restart:
	$(DC) restart app

logs:
	$(DC) logs -f app

install:
	$(APP) "php bin/console sylius:install -n"
	@$(MAKE) assets
	@$(MAKE) fixtures

fixtures:
	$(APP) "php bin/console sylius:fixtures:load oma -n"
	@$(MAKE) prices
	$(APP) "php bin/console cache:clear"
	@$(MAKE) restart

prices:
	$(DC) exec -T mysql sh -c 'mysql -usylius -psylius sylius_oma' < docker/sql/demo-prices.sql

assets:
	$(APP) "yarn install && yarn build && php bin/console sylius:install:assets -n"

landing:
	cd .. && npx ng build --base-href=/
	find public -maxdepth 1 -type f \( -name 'main-*.js' -o -name 'chunk-*.js' -o -name 'styles-*.css' -o -name 'polyfills-*.js' -o -name 'index.html' \) -delete
	rm -rf public/assets/img
	cp -a ../dist/oma/browser/. public/

shell:
	$(DC) exec app bash

console:
	$(APP) "php bin/console $(filter-out $@,$(MAKECMDGOALS))"

db:
	$(DC) exec mysql mysql -usylius -psylius sylius_oma

crons:
	env $$(grep -E '^OMA_HC_' .env | xargs) ../deploy/healthchecks-bootstrap.sh

dev:
	OMA_ENV=dev OMA_DEBUG=1 OMA_FRANKENPHP_CONFIG= $(DC) up -d --force-recreate app

reset:
	$(DC) down -v
	@$(MAKE) up
	@$(MAKE) install
