// https://vike.dev/onPageTransitionStart
import { initAuthStore } from '@/store/auth-store.js'

export { onPageTransitionStart }

function onPageTransitionStart(pageContext) {
  if (!pageContext.user && pageContext?.previousPageContext.user) {
    pageContext.user = pageContext.previousPageContext.user
  }

  if (!pageContext.user && initAuthStore().getState().user) {
    pageContext.user = initAuthStore().getState().user
  }

  document.querySelector('body').classList.add('page-is-transitioning')
}
