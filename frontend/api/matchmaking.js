import { sendRequest } from '@/api/index.js'

const SESSION_ID_KEY = 'mm_session_id'

// Generate UUID v4
function generateUUID() {
  // Try native crypto.randomUUID first
  if (typeof self !== 'undefined' && self.crypto && self.crypto.randomUUID) {
    return self.crypto.randomUUID()
  }

  // Fallback to manual UUID generation
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
    const r = Math.random() * 16 | 0
    const v = c === 'x' ? r : (r & 0x3 | 0x8)
    return v.toString(16)
  })
}

// Generate or retrieve session ID from sessionStorage (tab-specific)
export function getSessionId() {
  if (typeof window === 'undefined') return generateUUID()

  let sessionId = sessionStorage.getItem(SESSION_ID_KEY)

  if (!sessionId) {
    sessionId = generateUUID()
    sessionStorage.setItem(SESSION_ID_KEY, sessionId)
  }

  return sessionId
}

// Clear session ID (useful for testing or logout)
export function clearSessionId() {
  if (typeof window !== 'undefined') {
    sessionStorage.removeItem(SESSION_ID_KEY)
  }
}

export async function startSearch(mode) {
  return sendRequest('api/mm/search/start', 'POST', {
    mode,
    sessionId: getSessionId()
  })
}

export async function cancelSearch() {
  return sendRequest('api/mm/search/cancel', 'POST', {
    sessionId: getSessionId()
  })
}

export async function getState() {
  return sendRequest('api/mm/state', 'GET')
}

export async function acceptTicket(ticketId) {
  return sendRequest(`api/mm/ticket/${ticketId}/accept`, 'POST', {
    sessionId: getSessionId()
  })
}

export async function declineTicket(ticketId) {
  return sendRequest(`api/mm/ticket/${ticketId}/decline`, 'POST', {
    sessionId: getSessionId()
  })
}
