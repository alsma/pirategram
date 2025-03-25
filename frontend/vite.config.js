import react from '@vitejs/plugin-react'
import vike from 'vike/plugin'
import path from 'path'

const root = path.resolve(__dirname)

export default {
  plugins: [react(), vike()],
  resolve: {
    alias: {
      '@': root,
    },
  },
}
