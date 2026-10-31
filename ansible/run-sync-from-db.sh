#!/bin/bash
# Run from the web container via sudo (see deploy-sudoers). Applies groups then users from MySQL.
set -euo pipefail

export ANSIBLE_CONFIG="${ANSIBLE_CONFIG:-/ansible/ansible.cfg}"
export DB_HOST="${DB_HOST:-db}"
export DB_PORT="${DB_PORT:-3306}"
export DB_NAME="${DB_NAME:-users}"
export DB_USER="${DB_USER:-root}"
export DB_PASS="${DB_PASS:-theresa1}"

exec ansible-playbook /ansible/playbooks/sync-accounts-from-db.yml "$@"
