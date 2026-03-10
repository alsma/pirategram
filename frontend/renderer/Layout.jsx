import React from 'react'
import PropTypes from 'prop-types'

import { childrenPropType } from './PropTypeValues'
import { PageContextProvider } from './usePageContext'

import { Toaster } from '@/components/ui/toaster'
import { Toaster as SonnerToaster } from 'sonner'
import MainNav from '@/components/layout/main-nav'
import SocialLayout from '@/components/layout/social-layout'

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
        <SocialLayout>
          {children}
        </SocialLayout>
        <Toaster position="top-right" />
        <SonnerToaster position="top-right" richColors />
      </PageContextProvider>
    </React.StrictMode>
  )
}
