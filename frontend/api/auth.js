import { sendRequest } from '@/api/index'

export async function login(identity, password) {
  return sendRequest('api/auth/login', 'POST', { identity, password })
}

export async function register(email, options = {}) {
  return sendRequest('api/auth/register', 'POST', { email, options })
}

export async function logout() {
  return sendRequest('api/auth/logout', 'POST')
}

export async function loadUser(headers) {
  return sendRequest('api/auth/me', 'GET', undefined, headers)
}
