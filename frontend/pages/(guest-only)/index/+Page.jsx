import { useEffect } from 'react'
import { redirect } from 'vike/abort'

import { useAuthStore } from "@/store/context/auth"

import { Link } from '@/renderer/Link'
import { usePageContext } from '@/renderer/usePageContext.jsx'

export { Page }

function Page() {
  console.log(usePageContext().user)
  const { isAuthenticated } = useAuthStore()


  useEffect(() => {
    if (isAuthenticated) {
      // throw redirect('/play')
    }
  }, [isAuthenticated, redirect])

  return (
    <div className="flex flex-col items-center justify-center min-h-screen p-4">
      <div className="w-full max-w-4xl mx-auto text-center mb-12">
        <h1 className="text-5xl font-bold mb-4 bg-gradient-to-r from-ember to-burnt text-transparent bg-clip-text">
          Harbor Havoc
        </h1>
        <p className="text-xl text-gray-300 max-w-2xl mx-auto">
          Challenge friends, join teams, and climb the ranks in this strategic multiplayer experience.
        </p>
      </div>

      <div className="w-full max-w-md flex items-center justify-center">
        <Link href="/sign-up" className="dota-button w-80 text-center">
          Join now
        </Link>
      </div>

      <div className="mt-12 grid grid-cols-1 md:grid-cols-3 gap-8 w-full max-w-4xl">
        {[
          {
            title: 'Multiple Game Modes',
            description: 'Play 1v1, team 2v2, or free-for-all matches',
            icon: '⚔️',
          },
          {
            title: 'Competitive Ranking',
            description: 'Climb from Bronze to Legendary league',
            icon: '🏆',
          },
          {
            title: 'Party System',
            description: 'Team up with friends for the ultimate experience',
            icon: '👥',
          },
        ].map((feature, index) => (
          <div
            key={index}
            className="panel-texture p-6 rounded-2xl shadow-md border border-ember/10 hover:border-ember/30 transition-all hover:animate-pulse-glow"
          >
            <div className="flex items-center mb-3">
              <span className="text-2xl mr-3">{feature.icon}</span>
              <h3 className="text-xl font-semibold text-ember-light">{feature.title}</h3>
            </div>
            <p className="text-gray-300">{feature.description}</p>
          </div>
        ))}
      </div>
    </div>
  )
}
