#!/bin/bash
# Run from the web container via sudo (see deploy-sudoers). Applies groups then users from MySQL.
set -euo pipefail

export ANSIBLE_CONFIG="${ANSIBLE_CONFIG:-/ansible/ansible.cfg}"
export DB_HOST="${DB_HOST:-db}"
export DB_PORT="${DB_PORT:-3306}"
export DB_NAME="${DB_NAME:-users}"
export DB_USER="${DB_USER:-root}"
export DB_PASS="${DB_PASS:-theresa1}"

# Web deploy: show task details, diffs, and per-host results (override with ANSIBLE_VERBOSITY=1..4).
verbosity="${ANSIBLE_VERBOSITY:-3}"
verbose_flags=()
for ((v = 0; v < verbosity; v++)); do
  verbose_flags+=(-v)
done

export ANSIBLE_STDOUT_CALLBACK="${ANSIBLE_STDOUT_CALLBACK:-yaml}"
export ANSIBLE_SHOW_TASK_PATH="${ANSIBLE_SHOW_TASK_PATH:-1}"
export ANSIBLE_DISPLAY_OK_HOSTS="${ANSIBLE_DISPLAY_OK_HOSTS:-true}"
export ANSIBLE_DISPLAY_SKIPPED_HOSTS="${ANSIBLE_DISPLAY_SKIPPED_HOSTS:-true}"

exec ansible-playbook "${verbose_flags[@]}" --diff \
  /ansible/playbooks/sync-accounts-from-db.yml "$@"
