#!/usr/bin/env bash
# Prepare a fresh Ubuntu 22.04/24.04 VM as the PoC cloud database server (Table 1-2 "Cloud
# Database Server"). Run ON THE SERVER as a sudo-capable user:
#
#   sudo bash setup_vps_mysql.sh <client_public_ip> [dump.sql.gz]
#
# What it does:
#   1. installs MySQL 8.0 from the Ubuntu archive;
#   2. lets MySQL listen on the network, and opens port 3306 ONLY for <client_public_ip>;
#   3. creates database master_icd_x and user 'fkse'@'<client_public_ip>' (password is asked
#      interactively and never written to disk or to the shell history);
#   4. imports the dump if one is given (made on the client with deploy/export_dump.sh);
#   5. writes server_spec.txt with the values needed for Table 1-2 and Table 6.
# It changes nothing else on the machine. Run it again to change the client IP.
set -euo pipefail

CLIENT_IP="${1:-}"
DUMP="${2:-}"
DB=master_icd_x
DB_USER=fkse

if [[ $EUID -ne 0 ]]; then echo "Run with sudo." >&2; exit 1; fi
if [[ ! "$CLIENT_IP" =~ ^[0-9]{1,3}(\.[0-9]{1,3}){3}$ ]]; then
  echo "Usage: sudo bash $0 <client_public_ipv4> [dump.sql.gz]" >&2
  echo "Find the client IP on the Windows client with: curl.exe -4 ifconfig.me" >&2
  exit 1
fi
if [[ -n "$DUMP" && ! -f "$DUMP" ]]; then echo "Dump not found: $DUMP" >&2; exit 1; fi

echo "== 1/5 Installing MySQL 8.0"
export DEBIAN_FRONTEND=noninteractive
apt-get update -q
apt-get install -y -q mysql-server
restart_mysql() {
  if [[ -d /run/systemd/system ]]; then systemctl enable mysql >/dev/null 2>&1; systemctl restart mysql
  else service mysql restart; fi
}
restart_mysql

echo "== 2/5 Network access (port 3306 only from $CLIENT_IP)"
CONF=/etc/mysql/mysql.conf.d/zz-fkse.cnf
cat > "$CONF" <<EOF
# Written by setup_vps_mysql.sh (FKSE PoC benchmark server)
[mysqld]
bind-address = 0.0.0.0
mysqlx       = OFF
EOF
restart_mysql
if command -v ufw >/dev/null; then
  ufw allow OpenSSH >/dev/null
  # remove rules for an older client IP, then allow the current one
  while ufw status numbered | grep -q '3306/tcp.*ALLOW IN'; do
    n=$(ufw status numbered | grep '3306/tcp.*ALLOW IN' | head -1 | sed 's/^\[ *\([0-9]*\)\].*/\1/')
    ufw --force delete "$n" >/dev/null
  done
  ufw allow from "$CLIENT_IP" to any port 3306 proto tcp >/dev/null
  ufw --force enable >/dev/null
  ufw status | grep -E '3306|OpenSSH'
fi
echo "NOTE: the cloud provider's own firewall (AWS Security Group / OCI Security List) must also"
echo "      allow TCP 3306 from $CLIENT_IP/32 only. Oracle images also have iptables rules: see"
echo "      03_Catatan/Panduan_VPS_Benchmark.md."

echo "== 3/5 Database and user"
read -r -s -p "New password for MySQL user '$DB_USER' (input hidden): " PW1; echo
read -r -s -p "Repeat: " PW2; echo
if [[ "$PW1" != "$PW2" || ${#PW1} -lt 12 ]]; then echo "Passwords differ or shorter than 12." >&2; exit 1; fi
PW_SQL=${PW1//\'/\'\'}
mysql --protocol=socket -uroot <<SQL
CREATE DATABASE IF NOT EXISTS $DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
DROP USER IF EXISTS '$DB_USER'@'%';
CREATE USER IF NOT EXISTS '$DB_USER'@'$CLIENT_IP' IDENTIFIED BY '$PW_SQL';
ALTER USER '$DB_USER'@'$CLIENT_IP' IDENTIFIED BY '$PW_SQL';
GRANT ALL PRIVILEGES ON $DB.* TO '$DB_USER'@'$CLIENT_IP';
FLUSH PRIVILEGES;
SQL
unset PW1 PW2 PW_SQL

if [[ -n "$DUMP" ]]; then
  echo "== 4/5 Importing $DUMP"
  if [[ "$DUMP" == *.gz ]]; then gunzip -c "$DUMP" | mysql -uroot "$DB"; else mysql -uroot "$DB" < "$DUMP"; fi
else
  echo "== 4/5 No dump given: seed from the client with php scripts/seed_database.php"
fi

echo "== 5/5 Writing server_spec.txt (for Table 1-2 and Table 6)"
{
  echo "date:        $(date -Is)"
  echo "os:          $(. /etc/os-release && echo "$PRETTY_NAME")"
  echo "kernel:      $(uname -r) ($(uname -m))"
  echo "mysql:       $(mysql -uroot -N -e 'SELECT VERSION()') / $(mysqld --version | sed 's/^.*Ver //')"
  echo "cpu model:   $(lscpu | sed -n 's/^Model name: *//p' | head -1)"
  echo "vcpus:       $(nproc)"
  echo "hypervisor:  $(lscpu | sed -n 's/^Hypervisor vendor: *//p' | head -1)"
  echo "memory:      $(free -h | awk '/^Mem:/{print $2}')"
  echo "disk /:      $(df -h / | awk 'NR==2{print $2}')"
  echo "innodb_buffer_pool_size: $(mysql -uroot -N -e 'SELECT @@innodb_buffer_pool_size')"
  echo "row counts (Table 6):"
  mysql -uroot -N "$DB" -e "SELECT 'icds_encrypted',COUNT(*) FROM icds_encrypted UNION ALL
    SELECT 'exact_index',COUNT(*) FROM exact_index UNION ALL
    SELECT 'ngram_index',COUNT(*) FROM ngram_index UNION ALL
    SELECT 'lsh_index',COUNT(*) FROM lsh_index" 2>/dev/null || echo "  (tables not created yet)"
} | tee server_spec.txt
echo "Done. Copy server_spec.txt back to the client (it contains no secrets)."
