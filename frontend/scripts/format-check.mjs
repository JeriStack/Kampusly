import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs'
import { join, relative, resolve } from 'node:path'
import { spawnSync } from 'node:child_process'

const rootDir = resolve(import.meta.dirname, '..')
const prettierBin = join(
  rootDir,
  'node_modules',
  '.bin',
  process.platform === 'win32' ? 'prettier.cmd' : 'prettier',
)

if (existsSync(prettierBin)) {
  const result = spawnSync(prettierBin, ['--check', 'src/**/*.{ts,vue,css,json}'], {
    cwd: rootDir,
    stdio: 'inherit',
    shell: true,
  })
  process.exit(result.status ?? 1)
}

function collectFiles(dir, extensions, acc = []) {
  for (const entry of readdirSync(dir)) {
    const fullPath = join(dir, entry)
    const stat = statSync(fullPath)
    if (stat.isDirectory()) {
      collectFiles(fullPath, extensions, acc)
    } else if (extensions.some((ext) => fullPath.endsWith(ext))) {
      acc.push(fullPath)
    }
  }
  return acc
}

const files = collectFiles(join(rootDir, 'src'), ['.ts', '.vue', '.css']).sort()
const errors = []

for (const filePath of files) {
  const rel = relative(rootDir, filePath).replace(/\\/g, '/')
  const content = readFileSync(filePath, 'utf8')
  const lines = content.split(/\r?\n/)
  lines.forEach((line, idx) => {
    if (/\t/.test(line)) {
      errors.push(`${rel}:${idx + 1}: Tab character found (use 2 spaces).`)
    }
  })
}

if (errors.length > 0) {
  for (const err of errors) {
    console.error(`[FORMAT FAIL] ${err}`)
  }
  process.exit(1)
}

console.log(`[OK] Frontend format check passed (${files.length} files verified).`)

