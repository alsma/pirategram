// https://vike.dev/config
export default {
  // https://vike.dev/clientRouting
  clientRouting: true,
  passToClient: [
    'user',
  ],
  // https://vike.dev/meta
  meta: {
    // Define new setting 'title'
    title: {
      env: { server: true, client: true }
    },
    // Define new setting 'description'
    description: {
      env: { server: true }
    }
  },
  hydrationCanBeAborted: true
}
