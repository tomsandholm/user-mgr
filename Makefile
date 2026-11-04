build:
	docker compose up -d --build

down:
	docker compose down

up:
	docker compose up -d
	
status:
	docker ps | grep user-mgr

# MariaDB in docker-compose (published on DB_HOST:DB_PORT)
DB_HOST ?= 127.0.0.1
DB_PORT ?= 3306
DB_NAME ?= users
DB_USER ?= root
DB_PASS ?= theresa1
MYSQL := mysql -h $(DB_HOST) -P $(DB_PORT) -u $(DB_USER) -p$(DB_PASS) $(DB_NAME)

show-users:
	@$(MYSQL) -BN -e "SELECT name,uid,gid,email,shell,home FROM users;" | tr '\t' ':'

show-groups:
	@$(MYSQL) -BN -e "SELECT groupname,gid,members FROM hosts;" | tr '\t' ':'

web-shell:
	docker exec -it user-mgr-web-1 /bin/bash

db-shell:
	docker exec -it user-mgr-db-1 /bin/bash


DEV25_HOST ?= dev25
DEV25_DIR  ?= ~/prog/user-mgr

.PHONY: ansible-sync-groups ansible-sync-users ansible-sync-all ansible-sync-controller sync-to-dev25 sync-from-dev25 sync-keys-from-dev25

# Laptop ↔ dev25: rsync edits to dev25; laptop updates from GitHub only (after dev25 push). Keys: sync-keys-from-dev25.
sync-to-dev25:
	rsync -avz --exclude '.cursor/' --exclude 'ansible-keys/' --exclude '.git/' ./ $(DEV25_HOST):$(DEV25_DIR)/

sync-from-dev25:
	git pull --ff-only origin main

sync-keys-from-dev25:
	rsync -avz $(DEV25_HOST):$(DEV25_DIR)/ansible-keys/ ./ansible-keys/

ansible-sync-groups:
	ANSIBLE_CONFIG=ansible/ansible.cfg ansible-playbook ansible/playbooks/update-group-block.yml

ansible-sync-users:
	ANSIBLE_CONFIG=ansible/ansible.cfg ansible-playbook ansible/playbooks/update-passwd-block.yml

ansible-sync-all:
	ANSIBLE_CONFIG=ansible/ansible.cfg ansible-playbook ansible/playbooks/sync-accounts-from-db.yml

ansible-sync-controller:
	ANSIBLE_CONFIG=ansible/ansible.cfg ansible-playbook ansible/playbooks/update-controller-users.yml
