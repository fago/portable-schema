/**
 * Prints the full result of every fixture case as JSON lines, for comparing with php/tests/results.php.
 */
import { Formatter } from "../src/index.js";
import * as Fixtures from "./fixtures.js";

for (const file of Fixtures.files()) {
  const [schemaFile, tests] = Fixtures.load(file);
  for (const testCase of tests) {
    const result = await Fixtures.run(file, schemaFile, testCase);
    const messages = Formatter.create().messages(result);
    process.stdout.write(JSON.stringify([file, testCase.description, result, messages]) + "\n");
  }
}
