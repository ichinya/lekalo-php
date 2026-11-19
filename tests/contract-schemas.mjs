import assert from "node:assert/strict";
import { readdirSync, readFileSync } from "node:fs";
import { createRequire } from "node:module";

const require = createRequire(import.meta.url);
const Ajv = require("ajv/dist/2020").default;
assert.equal(require("ajv/package.json").version, "8.20.0");
const ajv = new Ajv({ strict: true });
const contracts = new URL("../contracts/", import.meta.url);
const filenames = ["operations", "routes", "types"].flatMap(family =>
  ["evidence", "input", "map"].map(kind => `php-${family}-${kind}.schema.v0.4.0.json`)
);
assert.deepEqual(
  readdirSync(contracts).filter(name => /^php-(operations|routes|types)-.*\.v0\.4\.0\.json$/.test(name)).sort(),
  filenames
);
for (const name of filenames) {
  const schema = JSON.parse(readFileSync(new URL(name, contracts), "utf8"));
  assert.equal(schema.$id, `https://dev.lekalo/${name}`);
  assert.equal(schema.properties.schemaVersion.const, `lekalo/${name.split(".schema.")[0]}/v0.4.0`);
  ajv.compile(schema);
}
console.log(JSON.stringify({ ok: true, phpSchemas: filenames.length }));
