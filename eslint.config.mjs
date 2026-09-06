// Lint config for the browser scripts - run with `npx eslint .` (CI does the
// same, pinned to the version in .github/workflows/ci.yml). No package.json
// on purpose: ESLint is a check, not a dependency of the product.
//
// Scope is deliberately narrow: undefined names, unused code, and a few
// mistakes `node --check` cannot see. Style stays with .editorconfig.
export default [
	{
		ignores: [ '_admin/.cache/**', 'private/**', 'public/**' ],
	},
	{
		files: [ '**/*.js' ],
		languageOptions: {
			ecmaVersion: 2022,
			sourceType: 'script',
			globals: {
				// browser
				window: 'readonly', document: 'readonly', navigator: 'readonly', location: 'readonly',
				history: 'readonly', localStorage: 'readonly', sessionStorage: 'readonly',
				console: 'readonly', fetch: 'readonly', FormData: 'readonly', URL: 'readonly',
				URLSearchParams: 'readonly', Blob: 'readonly', File: 'readonly', FileReader: 'readonly',
				XMLHttpRequest: 'readonly', Headers: 'readonly', Request: 'readonly', Response: 'readonly',
				AbortController: 'readonly', DOMParser: 'readonly', Image: 'readonly',
				HTMLElement: 'readonly', Element: 'readonly', Node: 'readonly', NodeList: 'readonly',
				Event: 'readonly', CustomEvent: 'readonly', KeyboardEvent: 'readonly', MouseEvent: 'readonly',
				MutationObserver: 'readonly', ResizeObserver: 'readonly', IntersectionObserver: 'readonly',
				NodeFilter: 'readonly', CSS: 'readonly', TextEncoder: 'readonly', TextDecoder: 'readonly',
				requestAnimationFrame: 'readonly', cancelAnimationFrame: 'readonly',
				setTimeout: 'readonly', clearTimeout: 'readonly', setInterval: 'readonly', clearInterval: 'readonly',
				getComputedStyle: 'readonly', matchMedia: 'readonly', crypto: 'readonly', performance: 'readonly',
				alert: 'readonly', confirm: 'readonly', prompt: 'readonly', btoa: 'readonly', atob: 'readonly',
				encodeURIComponent: 'readonly', decodeURIComponent: 'readonly', structuredClone: 'readonly',
				// Nino's own namespace object, declared once in _nino/Nino.js
				Nino: 'writable',
			},
		},
		rules: {
			'no-undef': 'error',
			'no-unused-vars': [ 'error', { args: 'none', caughtErrors: 'none' } ],
			'no-redeclare': 'error',
			'no-dupe-keys': 'error',
			'no-dupe-args': 'error',
			'no-duplicate-case': 'error',
			'no-unreachable': 'error',
			'no-constant-condition': [ 'error', { checkLoops: false } ],
			'no-self-assign': 'error',
			'no-self-compare': 'error',
			'no-unsafe-negation': 'error',
			'no-cond-assign': [ 'error', 'except-parens' ],
			'use-isnan': 'error',
			'valid-typeof': 'error',
			'eqeqeq': [ 'error', 'always' ],
		},
	},
	{
		// The tests run under node and build their own DOM stand-ins
		files: [ 'tests/**/*.js' ],
		languageOptions: {
			sourceType: 'commonjs',
			globals: { require: 'readonly', module: 'writable', process: 'readonly', __dirname: 'readonly', Buffer: 'readonly', global: 'writable' },
		},
	},
];
