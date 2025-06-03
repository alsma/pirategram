// https://vike.dev/onRenderHtml

export { onRenderHtml }

import ReactDOMServer from 'react-dom/server'
import { Layout } from './Layout'
import { escapeInject, dangerouslySkipEscape } from 'vike/server'
import { getPageTitle } from './getPageTitle'
import { AuthProvider } from '@/store/context/auth'

function onRenderHtml(pageContext) {
  const { Page } = pageContext

  // This onRenderHtml() hook only supports SSR, see https://vike.dev/render-modes for how to modify
  // onRenderHtml() to support SPA
  if (!Page) throw new Error('My onRenderHtml() hook expects pageContext.Page to be defined')

  const initialAuthState = { user: pageContext.user, isAuthenticated: !!pageContext.user }

  // Alternatively, we can use an HTML stream, see https://vike.dev/streaming
  const pageHtml = ReactDOMServer.renderToString(
    <AuthProvider initialState={initialAuthState}>
      <Layout pageContext={pageContext}>
        <Page />
      </Layout>
    </AuthProvider>
  )

  const title = getPageTitle(pageContext)
  const desc = pageContext.data?.description || pageContext.config.description || 'Demo of using Vike'

  const documentHtml = escapeInject`<!DOCTYPE html>
    <html lang="en">
      <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta name="description" content="${desc}" />
        <title>${title}</title>
      </head>
      <body class="bg-gradient-to-br from-[#101214] via-[#1a1c20] to-[#0d0f11] min-h-screen text-gray-100">
        <div id="react-root">${dangerouslySkipEscape(pageHtml)}</div>
      </body>
    </html>`

  return {
    documentHtml,
    pageContext: {
      // We can add custom pageContext properties here, see https://vike.dev/pageContext#custom
    }
  }
}
