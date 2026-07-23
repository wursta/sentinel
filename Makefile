SHELL=/bin/bash
PHP74=docker compose -f .docker.loc/docker-compose.yaml run --rm --no-deps php74
PHP80=docker compose -f .docker.loc/docker-compose.yaml run --rm --no-deps php80
PHP81=docker compose -f .docker.loc/docker-compose.yaml run --rm --no-deps php81
PHP82=docker compose -f .docker.loc/docker-compose.yaml run --rm --no-deps php82
PHP83=docker compose -f .docker.loc/docker-compose.yaml run --rm --no-deps php83
PHP84=docker compose -f .docker.loc/docker-compose.yaml run --rm --no-deps php84

composer-install:
	$(PHP74) composer update

# Quality
quality:
	$(PHP74) composer update --prefer-dist --no-interaction
	$(PHP74) vendor/bin/php-cs-fixer check src
	$(PHP74) vendor/bin/phpstan analyse src --memory-limit=512M
fix:
	$(PHP74) vendor/bin/php-cs-fixer fix src

# Tests
test74:
	$(PHP74) composer update --prefer-dist --no-interaction
	$(PHP74) vendor/bin/phpunit tests --no-coverage
test80:
	$(PHP80) composer update --prefer-dist --no-interaction
	$(PHP80) vendor/bin/phpunit tests --no-coverage
test81:
	$(PHP81) composer update --prefer-dist --no-interaction
	$(PHP81) vendor/bin/phpunit tests --no-coverage
test82:
	$(PHP82) composer update --prefer-dist --no-interaction
	$(PHP82) vendor/bin/phpunit tests --no-coverage
test83:
	$(PHP83) composer update --prefer-dist --no-interaction
	$(PHP83) vendor/bin/phpunit tests --no-coverage
test84:
	$(PHP84) composer update --prefer-dist --no-interaction
	$(PHP84) vendor/bin/phpunit tests --no-coverage
test:
	$(MAKE) test74
	$(MAKE) test80
	$(MAKE) test81
	$(MAKE) test82
	$(MAKE) test83
	$(MAKE) test84
lowest:
	$(PHP74) composer update --prefer-lowest --prefer-dist --no-interaction
	$(PHP74) vendor/bin/phpunit
ci:
	$(MAKE) quality
	$(MAKE) test
	$(MAKE) lowest