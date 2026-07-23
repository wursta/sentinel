SHELL=/bin/bash

check: cs-check \
	analyse \
	test
cs-check:
	docker compose -f .docker.loc/docker-compose.yaml run -T --rm --no-deps php php vendor/bin/php-cs-fixer check src
fix:
	docker compose -f .docker.loc/docker-compose.yaml run -T --rm --no-deps php php vendor/bin/php-cs-fixer fix src
analyse:
	docker compose -f .docker.loc/docker-compose.yaml run -T --rm --no-deps php php vendor/bin/phpstan analyse src --memory-limit=512M
test:
	docker compose -f .docker.loc/docker-compose.yaml run -T --rm --no-deps php php vendor/bin/phpunit tests --no-coverage
composer-install:
	docker compose -f .docker.loc/docker-compose.yaml run -T --rm --no-deps php composer install
