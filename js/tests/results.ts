/**
 * Prints the full result (validation or subschemaAt) and the loaded data of every fixture case as JSON lines, for comparing
 * with php/tests/results.php.
 */
import { Formatter, Result } from "../src/index.js";
import * as Fixtures from "./fixtures.js";

for (const file of Fixtures.files()) {
  const [schemaFile, tests] = Fixtures.load(file);
  for (const testCase of tests) {
    const result = await Fixtures.run(file, schemaFile, testCase);
    const messages = result instanceof Result ? Formatter.create().messages(result) : null;
    process.stdout.write(JSON.stringify([file, testCase.description, result, messages, testCase.data]) + "\n");
  }
}
