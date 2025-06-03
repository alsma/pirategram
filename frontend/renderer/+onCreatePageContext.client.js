import { initAuthStore } from '@/store/auth-store'


export { onCreatePageContext }

async function onCreatePageContext(pageContext) {
  console.log('xxxx', pageContext.user)
  pageContext.user = initAuthStore().getState().user
}