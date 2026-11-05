import { existsSync, readdirSync } from 'node:fs'
import { join, resolve } from 'node:path'
import { spawnSync } from 'node:child_process'
import createJiti from 'jiti'

const rootDir = resolve(import.meta.dirname, '..')
const vitestBin = join(
  rootDir,
  'node_modules',
  '.bin',
  process.platform === 'win32' ? 'vitest.cmd' : 'vitest',
)

if (existsSync(vitestBin)) {
  const result = spawnSync(vitestBin, ['run'], { cwd: rootDir, stdio: 'inherit', shell: true })
  process.exit(result.status ?? 1)
}

const jiti = createJiti(import.meta.url, {
  alias: {
    '@': join(rootDir, 'src'),
  },
})

const testsDir = join(rootDir, 'src', 'tests')
const specFiles = readdirSync(testsDir)
  .filter((name) => name.endsWith('.spec.ts') || name.endsWith('.test.ts'))
  .map((name) => join(testsDir, name))
  .sort()

for (const file of specFiles) {
  jiti(file)
}

const harness = jiti(join(testsDir, 'harness.ts'))
const suites = harness.getRegisteredSuites()

let totalTests = 0
let failures = 0

for (const suite of suites) {
  console.log(`\nSUITE: ${suite.name}`)
  for (const testCase of suite.tests) {
    totalTests += 1
    try {
      await testCase.fn()
      console.log(`  ✓ ${testCase.name}`)
    } catch (err) {
      failures += 1
      console.error(`  ✗ ${testCase.name}`)
      console.error(`    ${err instanceof Error ? err.message : String(err)}`)
    }
  }
}

if (failures > 0) {
  console.error(`\n[TEST FAIL] ${failures} of ${totalTests} test(s) failed.`)
  process.exit(1)
}

console.log(`\n[OK] All ${totalTests} frontend unit tests passed across ${suites.length} suite(s).`)
