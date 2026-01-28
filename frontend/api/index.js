import Cookies from 'js-cookie'
import qs from 'query-string'

const isServer = import.meta.env.SSR

let appBaseUrl = ''
if (import.meta.env.SSR) {
  appBaseUrl = import.meta.env.API_BASE_URL
}

export async function sendRequest(url, method, data = undefined, headers = {}, retryable = true) {
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
    query = query.length ? `?${query}` : ''
  }

  const fullUrl = `${appBaseUrl}/${url}${query}`
  const response = await fetch(fullUrl, {
    headers,
    method,
    body,
    credentials: 'same-origin',
  })

  if (!response.ok) {
    if (response.status === 419) {
      expireXSRFToken()

      if (retryable) {
        return sendRequest(url, method, data, headers, false)
      }
    }

    // Try to get error details from response
    let errorDetails = null
    try {
      errorDetails = await response.json()
    } catch (e) {
      // Response might not be JSON
    }

    const error = new Error(` Request failed. URL: ${fullUrl} STATUS: ${response.status}`)
    error.status = response.status
    error.details = errorDetails
    throw error
  }

  const result = await response.json()

  // TODO

  return result
}

export async function apiXSRFRequest(force = false) {
  if (Cookies.get('XSRF-TOKEN') && !force) {
    return
  }

  await fetch(`${appBaseUrl}/api/auth/csrf-cookie`, {
    method: 'GET',
    headers: {},
    credentials: 'same-origin',
  })
}

export function expireXSRFToken() {
  Cookies.remove('XSRF-TOKEN')
}