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


.PHONY: ansible-sync-groups ansible-sync-users ansible-sync-all ansible-sync-controller

ansible-sync-groups:
	ANSIBLE_CONFIG=ansible/ansible.cfg ansible-playbook ansible/playbooks/update-group-block.yml

ansible-sync-users:
	ANSIBLE_CONFIG=ansible/ansible.cfg ansible-playbook ansible/playbooks/update-passwd-block.yml

ansible-sync-all:
	ANSIBLE_CONFIG=ansible/ansible.cfg ansible-playbook ansible/playbooks/sync-accounts-from-db.yml

ansible-sync-controller:
	ANSIBLE_CONFIG=ansible/ansible.cfg ansible-playbook ansible/playbooks/update-controller-users.yml
