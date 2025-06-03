import React from 'react'
import PropTypes from 'prop-types'

import { childrenPropType } from './PropTypeValues'
import { PageContextProvider } from './usePageContext'

import { Toaster } from '@/components/ui/toaster'
import MainNav from '@/components/layout/main-nav'

import './css/index.css'

export { Layout }

Layout.propTypes = {
  pageContext: PropTypes.any,
  children: childrenPropType
}
function Layout({ pageContext, children }) {
  return (
    <React.StrictMode>
      <PageContextProvider pageContext={pageContext}>
        <MainNav />
        <main>
          {children}
        </main>
        <Toaster position="top-right" />
      </PageContextProvider>
    </React.StrictMode>
  )
}
