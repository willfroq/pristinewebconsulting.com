#!/bin/bash

# Run this script before committing!!!

bin/console cache:clear --env=dev
bin/console cache:clear --env=test
bin/console cache:clear --env=prod

vendor/bin/phpstan clear-result-cache

vendor/bin/php-cs-fixer fix src
vendor/bin/php-cs-fixer fix tests
vendor/bin/twig-cs-fixer lint --fix
vendor/bin/phpstan analyse src --level=8
vendor/bin/phpstan analyse tests --level=8

vendor/bin/phpunit --stop-on-failure
