#!/bin/sh
# Compares the full results of every fixture case between PHP and JS.
set -e
cd "$(dirname "$0")/.."
out="${TMPDIR:-/tmp}"
php php/tests/results.php > "$out/portable-schema-php.jsonl"
npx tsx js/tests/results.ts > "$out/portable-schema-js.jsonl"
diff "$out/portable-schema-php.jsonl" "$out/portable-schema-js.jsonl"
echo "PHP and JS give identical results for $(wc -l < "$out/portable-schema-php.jsonl") cases."
