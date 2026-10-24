#!/usr/bin/env bash
# Export the already-seeded PoC database (icds_encrypted + the three index tables) to a gzip dump,
# so the VPS can import it instead of re-seeding about 1.5 M rows over the internet.
#
#   bash deploy/export_dump.sh [out.sql.gz]
#       uses DB_HOST/DB_PORT/DB_USER/DB_NAME/DB_PASS from .env and a local `mysqldump`
#   DOCKER_CONTAINER=mysql_db bash deploy/export_dump.sh [out.sql.gz]
#       runs mysqldump inside that MySQL container instead (no client tools needed on the host)
#
# The password never appears on a command line or in `ps`: it goes through a mode-600 option
# file (native) or through the MYSQL_PWD variable passed by name (docker). The index tables hold
# only HMAC trapdoors and AES ciphertexts; no plaintext keyword leaves the client.
set -euo pipefail
cd "$(dirname "$0")/.."

get() { grep -E "^$1=" .env 2>/dev/null | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
HOST=$(get DB_HOST); PORT=$(get DB_PORT); USER_=$(get DB_USER); NAME=$(get DB_NAME)
HOST=${HOST:-127.0.0.1}; PORT=${PORT:-3306}; USER_=${USER_:-root}; NAME=${NAME:-master_icd_x}
OUT=${1:-deploy/master_icd_x_dump.sql.gz}
TABLES="icds_encrypted exact_index ngram_index lsh_index"
DUMP_OPTS="--single-transaction --no-tablespaces --set-gtid-purged=OFF"

if [[ -n "${DOCKER_CONTAINER:-}" ]]; then
  MYSQL_PWD=$(get DB_PASS) docker exec -e MYSQL_PWD "$DOCKER_CONTAINER" \
    mysqldump -u"$USER_" $DUMP_OPTS "$NAME" $TABLES | gzip -9 > "$OUT"
else
  OPT=$(mktemp); chmod 600 "$OPT"; trap 'rm -f "$OPT"' EXIT
  printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n' "$HOST" "$PORT" "$USER_" "$(get DB_PASS)" > "$OPT"
  mysqldump --defaults-extra-file="$OPT" $DUMP_OPTS "$NAME" $TABLES | gzip -9 > "$OUT"
fi
echo "written $OUT ($(du -h "$OUT" | cut -f1))"
echo "copy it to the server:  scp $OUT <user>@<server_ip>:~/"
