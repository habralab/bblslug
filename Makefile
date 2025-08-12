# Makefile for bblslug

.PHONY: install lint test

install:
	composer install --no-interaction --optimize-autoloader

test: test-phplint test-phpunit test-phpstan

lint: test-phplint

test-phplint:
	composer run test:phplint

test-phpunit:
	composer run test:phpunit

test-phpstan:
	composer run test:phpstan
