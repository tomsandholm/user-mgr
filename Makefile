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


ansible-shell:
	docker exec -it user-mgr-ansible-1 /bin/bash
