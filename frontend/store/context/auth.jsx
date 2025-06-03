import React, { createContext, useContext, useRef } from 'react'
import { useStore as useZustandStore } from 'zustand'
import { initAuthStore } from '@/store/auth-store'

const AuthStoreContext = createContext(null)

/* <AuthProvider initialState={...}> … */
export const AuthProvider = ({ children, initialState }) => {
  // keep the same store instance for this provider’s lifetime
  const storeRef = useRef()
  if (!storeRef.current) {
    storeRef.current = initAuthStore(initialState)
  }
  return (
    <AuthStoreContext.Provider value={storeRef.current}>
      {children}
    </AuthStoreContext.Provider>
  )
}

export const useAuthStore = (selector, equalityFn) => {
  const store = useContext(AuthStoreContext)
  if (!store)
    throw new Error('❌ useAuthStore must be used inside <AuthProvider>')
  return useZustandStore(store, selector, equalityFn)
}
