"use client"

import { useAuthStore } from "@/store/context/auth"
import { usePartyStore } from "@/store/party-store"
import FriendsSidebar from "@/components/social/friends-sidebar"
import PartyBar from "@/components/social/party-bar"
import GameModes from "@/components/game/game-modes"
import MatchmakingModal from "@/components/game/matchmaking-modal"
import ReadyCheckModal from "@/components/game/ready-check-modal"

export default function PlayPage() {
  const isAuthenticated = useAuthStore(s => s.isAuthenticated)
  const { queueStatus } = usePartyStore()

  return (
    <div className="flex h-screen overflow-hidden">
      {/* Friends Sidebar - Hidden on mobile */}
      <div className="hidden md:block w-80 border-r border-ember/20 panel-texture">
        <FriendsSidebar />
      </div>

      {/* Main Content */}
      <div className="flex-1 flex flex-col overflow-hidden">
        <main className="flex-1 overflow-y-auto p-6">
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
        </main>

        {/* Party Bar - Fixed at bottom */}
        <PartyBar />
      </div>

      {/* Modals */}
      {queueStatus === "queuing" && <MatchmakingModal />}
      {queueStatus === "lobby-ready" && <ReadyCheckModal />}
    </div>
  )
}
