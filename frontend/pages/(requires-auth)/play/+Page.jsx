"use client"

import GameModes from "@/components/game/game-modes"

export default function PlayPage() {
  return (
    <div className="p-6">
      <div className="max-w-4xl mx-auto">
        <h1 className="text-3xl font-bold mb-8 text-ember-light">Game Lobby</h1>

        <GameModes />

        {/* Recent Matches Section */}
        <div className="mt-12">
          <h2 className="text-2xl font-semibold mb-4 text-gray-200">Recent Matches</h2>
          <div className="panel-texture rounded-2xl shadow-xl border border-ember/20 p-6">
            <p className="text-gray-400 text-center">No recent matches found.</p>
          </div>
        </div>
      </div>
    </div>
  )
}
