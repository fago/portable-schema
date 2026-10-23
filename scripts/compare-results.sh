#!/bin/sh
# Compares PHP and JS: the full results and loaded data (YAML included) of every fixture case, and the agent tool showcase's output.
set -e
cd "$(dirname "$0")/.."
out="${TMPDIR:-/tmp}"
php php/tests/results.php > "$out/portable-schema-php.raw.jsonl"
# Re-serialized by JS: the same numbers, written as JS writes them (PHP writes 1.0e+20).
node -e 'require("readline").createInterface({ input: process.stdin }).on("line", (l) => console.log(JSON.stringify(JSON.parse(l))))' < "$out/portable-schema-php.raw.jsonl" > "$out/portable-schema-php.jsonl"
npx tsx js/tests/results.ts > "$out/portable-schema-js.jsonl"
test -s "$out/portable-schema-php.jsonl"
diff "$out/portable-schema-php.jsonl" "$out/portable-schema-js.jsonl"
echo "PHP and JS give identical results for $(wc -l < "$out/portable-schema-php.jsonl") cases."
php fixtures/examples/drupal/agent-tool/repair-loop.php > "$out/portable-schema-repair-php.txt"
npx tsx fixtures/examples/drupal/agent-tool/repair-loop.mts > "$out/portable-schema-repair-js.txt"
diff "$out/portable-schema-repair-php.txt" "$out/portable-schema-repair-js.txt"
echo "PHP and JS print the same repair loop."
diff "$out/portable-schema-repair-php.txt" fixtures/examples/drupal/agent-tool/repair-loop.txt
echo "The repair loop matches fixtures/examples/drupal/agent-tool/repair-loop.txt."
