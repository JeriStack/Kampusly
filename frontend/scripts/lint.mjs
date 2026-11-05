import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs'
import { join, relative, resolve } from 'node:path'
import { spawnSync } from 'node:child_process'
import { parse as parseSfc } from '@vue/compiler-sfc'
import ts from 'typescript'

const rootDir = resolve(import.meta.dirname, '..')
const eslintBin = join(
  rootDir,
  'node_modules',
  '.bin',
  process.platform === 'win32' ? 'eslint.cmd' : 'eslint',
)

if (existsSync(eslintBin)) {
  const result = spawnSync(eslintBin, ['.'], { cwd: rootDir, stdio: 'inherit', shell: true })
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

const files = collectFiles(join(rootDir, 'src'), ['.ts', '.vue']).sort()
const errors = []

for (const filePath of files) {
  const rel = relative(rootDir, filePath).replace(/\\/g, '/')
  const content = readFileSync(filePath, 'utf8')

  if (/\bdebugger\b/.test(content)) {
    errors.push(`${rel}: Unexpected 'debugger' statement (no-debugger).`)
  }

  if (filePath.endsWith('.vue')) {
    const parsed = parseSfc(content, { filename: filePath })
    if (parsed.errors.length > 0) {
      for (const err of parsed.errors) {
        errors.push(`${rel}: Vue SFC parse error — ${err.message}`)
      }
    }
    const scriptContent =
      parsed.descriptor.scriptSetup?.content || parsed.descriptor.script?.content || ''
    if (scriptContent) {
      const result = ts.transpileModule(scriptContent, {
        fileName: filePath + '.ts',
        reportDiagnostics: true,
        compilerOptions: { target: ts.ScriptTarget.ES2022, module: ts.ModuleKind.ESNext },
      })
      for (const diag of result.diagnostics ?? []) {
        if (diag.category === ts.DiagnosticCategory.Error) {
          errors.push(
            `${rel}: TS syntax error — ${ts.flattenDiagnosticMessageText(diag.messageText, '\n')}`,
          )
        }
      }
    }
  } else if (filePath.endsWith('.ts') && !filePath.endsWith('.d.ts')) {
    const result = ts.transpileModule(content, {
      fileName: filePath,
      reportDiagnostics: true,
      compilerOptions: { target: ts.ScriptTarget.ES2022, module: ts.ModuleKind.ESNext },
    })
    for (const diag of result.diagnostics ?? []) {
      if (diag.category === ts.DiagnosticCategory.Error) {
        errors.push(
          `${rel}: TS syntax error — ${ts.flattenDiagnosticMessageText(diag.messageText, '\n')}`,
        )
      }
    }
  }
}

if (errors.length > 0) {
  for (const err of errors) {
    console.error(`[LINT FAIL] ${err}`)
  }
  process.exit(1)
}

console.log(`[OK] Frontend lint passed (${files.length} files checked).`)

