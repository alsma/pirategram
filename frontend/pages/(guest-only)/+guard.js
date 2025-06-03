import { redirect } from 'vike/abort'

export const guard = (pageContext) => {
  const { user } = pageContext
  console.log(JSON.stringify(user))
  // if (user) {
  //   throw redirect('/play')
  // }
}