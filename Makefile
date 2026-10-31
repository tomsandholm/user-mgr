build:
	docker compose up -d --build

down:
	docker compose down

up:
	docker compose up -d
	
status:
	docker ps | grep user-mgr

show-users:
	@mysql -BN -h 127.0.0.1 -e "SELECT name,uid,gid,email,shell,home from users.users;" | tr '\t' ':'

show-groups:
	@mysql -BN -h 127.0.0.1 -e "SELECT groupname,gid,members from users.hosts;" | tr '\t' ':'

web-shell:
	docker exec -it user-mgr-web-1 /bin/bash

db-shell:
	docker exec -it user-mgr-db-1 /bin/bash


DEV25_HOST ?= dev25
DEV25_DIR  ?= ~/prog/user-mgr

.PHONY: ansible-sync-groups ansible-sync-users ansible-sync-all ansible-sync-controller sync-to-dev25 sync-from-dev25

# Laptop ↔ dev25 (sv-quantum-lx25): push git from dev25 only; pull changes to laptop with sync-from-dev25.
sync-to-dev25:
	rsync -avz --exclude '.cursor/' ./ $(DEV25_HOST):$(DEV25_DIR)/

sync-from-dev25:
	git fetch origin
	git pull --ff-only origin main
	rsync -avz $(DEV25_HOST):$(DEV25_DIR)/ ./ --exclude '.git/' --exclude '.cursor/'

ansible-sync-groups:
	ANSIBLE_CONFIG=ansible/ansible.cfg ansible-playbook ansible/playbooks/update-group-block.yml

ansible-sync-users:
	ANSIBLE_CONFIG=ansible/ansible.cfg ansible-playbook ansible/playbooks/update-passwd-block.yml

ansible-sync-all:
	ANSIBLE_CONFIG=ansible/ansible.cfg ansible-playbook ansible/playbooks/sync-accounts-from-db.yml

ansible-sync-controller:
	ANSIBLE_CONFIG=ansible/ansible.cfg ansible-playbook ansible/playbooks/update-controller-users.yml
