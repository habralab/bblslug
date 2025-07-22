# Makefile for bblslug

.PHONY: install lint test

install:
	composer install --no-interaction --optimize-autoloader

lint:
	composer run lint

test:
	composer run phpunit
