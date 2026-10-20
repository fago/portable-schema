/**
 * Prints the agent tools as MCP tool definitions and a repair loop: calls with invalid arguments, the errors the
 * tool returns, and the corrected calls. repair-loop.php prints the same in PHP.
 *
 *   npx tsx repair-loop.mts
 */
import { fileURLToPath } from "node:url";
import { CheckResult, Exporter, Formatter, Registry, Validator, type CheckRequest, type Result } from "../../../../js/src/index.js";

const TOOLS: Record<string, { calls: string[]; result: string }> = {
  find_images: {
    calls: [
      '{"query": "skiing photos", "limit": 100, "sort": "date"}',
      '{"query": "ski", "limit": 5}',
    ],
    result: '{"images": [{"id": 7, "title": "Ski tour", "url": "https://dri.es/photos/ski-tour.jpg", "alt": "Two skiers on a ridge above the clouds"}]}',
  },
  update_image_alt: {
    calls: [
      '{"image_id": "7", "alt": ""}',
      '{"image_id": 70, "alt": "Two skiers on a ridge above the clouds"}',
      '{"image_id": 7, "alt": "Two skiers on a ridge above the clouds"}',
    ],
    result: '{"id": 7, "url": "https://dri.es/photos/ski-tour.jpg", "alt": "Two skiers on a ridge above the clouds"}',
  },
};

/** Files that exist on the site. */
const FILES = [7, 8];

const registry = Registry.withTypes();
for (const directory of ["../schemas", "../catalogs", "schemas"]) {
  registry.addDirectory(fileURLToPath(new URL(directory, import.meta.url)));
}
const validator = (await Validator.create(registry)).withCheck("drupal:ValidReference", (requests: CheckRequest[]) =>
  requests.map((request) => FILES.includes(request.value as number)
    ? new CheckResult()
    : new CheckResult([{ code: "check.drupal:ValidReference", params: { id: request.value }, message: `The referenced file (${String(request.value)}) does not exist.` }])));
const exporter = new Exporter(registry);
const formatter = Formatter.create();

const print = (value: unknown): void => {
  process.stdout.write(JSON.stringify(value, null, 4) + "\n");
};

process.stdout.write("# MCP tool definitions\n\n");
print(Object.keys(TOOLS).map((name) => {
  const input = registry.get(`drupal:tool.${name}.input`) as Record<string, unknown>;
  return {
    name,
    title: input["title"],
    description: input["description"],
    inputSchema: exporter.inline(`drupal:tool.${name}.input`),
    outputSchema: exporter.inline(`drupal:tool.${name}.output`),
  };
}));

/** The tool result for invalid arguments: messages as text, the errors as structured content. */
const toolError = (result: Result) => {
  const messages = formatter.messages(result);
  return {
    isError: true,
    content: [{ type: "text", text: messages.join("\n") }],
    structuredContent: {
      errors: result.errors.map((error, i) => ({
        instanceLocation: error.instanceLocation,
        code: error.code,
        params: error.params,
        message: messages[i],
      })),
    },
  };
};

for (const [name, tool] of Object.entries(TOOLS)) {
  process.stdout.write(`\n# Repair loop: ${name}\n`);
  for (const call of tool.calls) {
    process.stdout.write(`\n→ ${name} ${call}\n`);
    const result = validator.validate(JSON.parse(call), `drupal:tool.${name}.input`);
    if (!result.valid) {
      process.stdout.write("← ");
      print(toolError(result));
      continue;
    }
    const output: unknown = JSON.parse(tool.result);
    const checked = validator.validate(output, `drupal:tool.${name}.output`);
    process.stdout.write(checked.valid ? "← valid output: " : "← invalid output: ");
    print({ structuredContent: output });
  }
}
