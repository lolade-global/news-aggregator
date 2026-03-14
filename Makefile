.PHONY: setup up down test lint fix fetch-news db-fresh shell

setup:
	cp -n .env.example .env || true
	composer install
	./vendor/bin/sail build
	./vendor/bin/sail up -d
	./vendor/bin/sail artisan key:generate
	./vendor/bin/sail artisan migrate
	@echo "Setup complete. Run 'make fetch-news' to populate articles."

up:
	./vendor/bin/sail up -d

down:
	./vendor/bin/sail down

test:
	./vendor/bin/pest

lint:
	./vendor/bin/pint --test
	./vendor/bin/phpstan analyse --memory-limit=512M

fix:
	./vendor/bin/pint
	./vendor/bin/rector process

fetch-news:
	./vendor/bin/sail artisan news:fetch

db-fresh:
	./vendor/bin/sail artisan migrate:fresh

shell:
	./vendor/bin/sail shell
