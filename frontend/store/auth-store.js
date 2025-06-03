import { createStore } from 'zustand'
import { register, logout as apiLogout } from '@/api/auth'
import { navigate } from 'vike/client/router'

export const createAuthStore = (init = {}) =>
  createStore((set) => ({
    user: null,
    isAuthenticated: false,
    ...init,

    setUserInfo: (user = null) =>
      set({ user, isAuthenticated: Boolean(user) }),

    signup: async (email) => {
      const userInfo = await register(email, { agreement: true })
      set({ user: userInfo, isAuthenticated: true })
    },

    logout: async () => {
      try {
        await apiLogout()
      } finally {
        set({ user: null, isAuthenticated: false })
        navigate('/')
      }
    }
  }))

let clientSideStore
export const initAuthStore = (initialState = {}) => {
  console.log(initialState, clientSideStore)
  // SSR: always create a fresh store
  // CSR: reuse the same store instance across navigations
  if (typeof window === 'undefined') return createAuthStore(initialState)

  if (!clientSideStore) {
    clientSideStore = createAuthStore(initialState)
  }
  return clientSideStore
}
