import Cookies from 'js-cookie'
import qs from 'query-string'

const isServer = import.meta.env.SSR

let appBaseUrl = ''
if (import.meta.env.SSR) {
  appBaseUrl = import.meta.env.API_BASE_URL
}

export async function sendRequest(url, method, data = undefined, headers = {}) {
  headers['Accept'] = 'application/json'

  let body = undefined
  if (['PUT', 'POST', 'DELETE'].includes(method)) {
    if (!isServer) {
      await apiXSRFRequest()
      headers['X-XSRF-TOKEN'] = Cookies.get('XSRF-TOKEN')
    } else {
      headers['X-XSRF-TOKEN'] = serverCookies.get('XSRF-TOKEN')?.value
    }

    body = JSON.stringify(data)
    headers['Content-Type'] = 'application/json'
  }

  let query = ''
  if (method === 'GET' && data) {
    query = qs.stringify(data, { skipNull: true })
    query = query.length ? `${query}` : ''
  }

  const fullUrl = `${appBaseUrl}/${url}${query}`
  const response = await fetch(fullUrl, {
    headers,
    method,
    body,
    credentials: 'same-origin',
  })

  if (!response.ok) {
    throw new Error(` Request failed. URL: ${fullUrl} STATUS: ${response.status}`)
  }

  const result = await response.json()

  // TODO

  return result
}

export async function apiXSRFRequest() {
  if (Cookies.get('XSRF-TOKEN')) {
    return
  }

  await fetch(`${appBaseUrl}/api/auth/csrf-cookie`, {
    method: 'GET',
    headers: {},
    credentials: 'same-origin',
  })
}