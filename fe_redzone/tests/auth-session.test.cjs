const { test } = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const source = fs.readFileSync(path.join(__dirname, '../src/stores/auth.js'), 'utf8').replace(/^import .*$/gm, '').replace('export const useAuthStore =', 'return')
function setup(api) {
 const session = new Map([['auth', 'cached user']]), local = new Map([['auth', 'legacy user']])
 const config = new Function('defineStore', 'api', 'sessionStorage', 'localStorage', source)((_, config) => config, api, { removeItem: key => session.delete(key) }, { removeItem: key => local.delete(key) })
 return { store: { ...config.state(), ...config.actions, user: { id: 1 } }, session, local }
}
const failure = status => Object.assign(new Error('Request failed'), { response: { status } })
for (const status of [204, 401, 500]) test(`logout clears cached identity for status ${status}`, async () => {
 const { store, session, local } = setup({ post: async () => { if (status !== 204) throw failure(status) } })
 if (status === 500) await assert.rejects(() => store.logout()); else await store.logout()
 assert.equal(store.user, null); assert.equal(store.loading, false)
 assert.equal(session.has('auth'), false); assert.equal(local.has('auth'), false)
})
test('logout refreshes CSRF once after 419', async () => {
 const calls = []
 const { store } = setup({ post: async url => { calls.push(url); if (calls.length === 1) throw failure(419) }, get: async url => calls.push(url) })
 await store.logout()
 assert.deepEqual(calls, ['/logout', '/sanctum/csrf-cookie', '/logout']); assert.equal(store.user, null)
})
test('network failure clears identity', async () => {
 const { store } = setup({ post: async () => { throw new Error('Network error') } })
 await assert.rejects(() => store.logout()); assert.equal(store.user, null); assert.equal(store.loading, false)
})
test('401 interceptor excludes credential errors', async () => {
 let reject, expired = 0, alerts = 0
 const api = { defaults: { headers: { common: {} } }, interceptors: { response: { use: (_, handler) => { reject = handler } } } }
 const code = fs.readFileSync(path.join(__dirname, '../src/plugins/axios.js'), 'utf8').replace(/^import .*$/gm, '').replace(/import\.meta\.env\.VITE_API_URL/g, "'https://api.example.test'").replace('export function', 'function').replace('export default api', 'return setSessionExpiredHandler')
 const setHandler = new Function('axios', 'window', code)({ create: () => api }, { alert: () => alerts++ })
 setHandler(() => expired++)
 for (const [url, status, method] of [['/api/subscribers', 401, 'delete'], ['/login', 401, 'post'], ['/api/auth/login', 401, 'post'], ['/api/user', 403, 'get']]) await assert.rejects(() => reject({ response: { status }, config: { url, method } }))
 assert.equal(expired, 1); assert.equal(alerts, 0)
})
