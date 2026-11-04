export type TestFn = () => void | Promise<void>

export interface RegisteredSuite {
  name: string
  tests: Array<{ name: string; fn: TestFn }>
}

const suites: RegisteredSuite[] = []
let currentSuite: RegisteredSuite | null = null

export function describe(name: string, register: () => void): void {
  const suite: RegisteredSuite = { name, tests: [] }
  const previous = currentSuite
  currentSuite = suite
  register()
  currentSuite = previous
  suites.push(suite)
}

export function it(name: string, fn: TestFn): void {
  if (!currentSuite) {
    throw new Error(`Test "${name}" must be declared inside a describe() block.`)
  }
  currentSuite.tests.push({ name, fn })
}

export function getRegisteredSuites(): RegisteredSuite[] {
  return suites
}

export function expect<T>(actual: T) {
  return {
    toBe(expected: T): void {
      if (!Object.is(actual, expected)) {
        throw new Error(
          `Expected ${JSON.stringify(actual)} to be ${JSON.stringify(expected)}.`,
        )
      }
    },
    toEqual(expected: unknown): void {
      const actualJson = JSON.stringify(actual)
      const expectedJson = JSON.stringify(expected)
      if (actualJson !== expectedJson) {
        throw new Error(`Expected ${actualJson} to equal ${expectedJson}.`)
      }
    },
    toBeInstanceOf<C extends new (...args: never[]) => unknown>(
      ctor: C,
    ): void {
      if (!(actual instanceof ctor)) {
        throw new Error(`Expected value to be an instance of ${ctor.name}.`)
      }
    },
  }
}

