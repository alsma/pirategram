import { parse } from 'cookie'

import { loadUser } from '@/api/auth'

export { onCreatePageContext }

const ApiSessionCookie = import.meta.env.API_SESSION_COOKIE || 'session'
const HeadersToPass = ['Cookie', 'Referer', 'Origin']

async function onCreatePageContext(pageContext) {
  try {
    const headers = {}
    for (const header of HeadersToPass) {
      if (pageContext.headers[header] || pageContext.headers[header.toLowerCase()]) {
        headers[header.toLowerCase()] = pageContext.headers[header] || pageContext.headers[header.toLowerCase()]
      }
    }

    const parsedCookies = parse(headers.cookie || '')
    if (!parsedCookies[ApiSessionCookie]) {
      return
    }

    if (!(headers.origin || headers.referer)) {
      headers.origin = pageContext.headers.host
    }

    pageContext.user = await loadUser(headers)
  } catch (e) {
    console.log(e)
  }
}