"use client"

import { useEffect } from 'react'
import { echo } from '@laravel/echo-react'

import { useAuthStore } from "@/store/context/auth"
import { usePartyStore, selectQueueStatus } from "@/store/party-store"
import { CancelReason, GroupStatus } from '@/lib/constants/matchmaking.js'
import FriendsSidebar from "@/components/social/friends-sidebar"
import PartyBar from "@/components/social/party-bar"
import GameModes from "@/components/game/game-modes"
import MatchmakingModal from "@/components/game/matchmaking-modal"
import ReadyCheckModal from "@/components/game/ready-check-modal"
import { toast } from 'sonner'

export default function PlayPage() {
  const userHash = useAuthStore(s => s.user.hash)
  const {
    handleSearchUpdated,
    handleTicketCreated,
    handleTicketUpdated,
    handleTicketExpired,
    handleStarting,
    handleMatchStarted,
    restoreState,
  } = usePartyStore()
  const queueStatus = usePartyStore(selectQueueStatus)

  // Restore state on mount
  useEffect(() => {
    restoreState()
  }, [restoreState])

  useEffect(() => {
    if (!userHash) return

    const channel = echo().private(`user.${userHash}`)

    // Listen to all MM events
    channel
      .listen('.mm.search.updated', (e) => {
        handleSearchUpdated(e)

        // Show toast for certain state changes
        if (e.state === GroupStatus.Idle && e.reason === CancelReason.UserCancelled) {
          toast.info('Search cancelled')
        } else if (e.state === GroupStatus.Idle && e.reason === CancelReason.SearchTimeout) {
          toast.error('Search timed out - no match found')
        } else if (e.state === GroupStatus.Searching) {
          toast.success('Searching for match...')
        }
      })
      .listen('.mm.ticket.created', (e) => {
        handleTicketCreated(e)
        toast.success('Match found!')
      })
      .listen('.mm.ticket.updated', (e) => {
        handleTicketUpdated(e)
      })
      .listen('.mm.ticket.expired', (e) => {
        handleTicketExpired(e)

        if (e.reason === CancelReason.Declined) {
          if (e.backToSearch) {
            toast.warning('Player declined - returning to queue')
          } else {
            toast.error('You declined the match')
          }
        } else if (e.reason === CancelReason.Timeout) {
          if (e.backToSearch) {
            toast.warning('Player timed out - returning to queue')
          } else {
            toast.error('You failed to accept in time')
          }
        }
      })
      .listen('.mm.starting', (e) => {
        handleStarting(e)
        toast.success('All players ready - match starting!')
      })
      .listen('.mm.match.started', (e) => {
        handleMatchStarted(e)
        toast.success(`Match ${e.matchId} started!`)
      })

    return () => {
      channel.stopListening('.mm.search.updated')
      channel.stopListening('.mm.ticket.created')
      channel.stopListening('.mm.ticket.updated')
      channel.stopListening('.mm.ticket.expired')
      channel.stopListening('.mm.starting')
      channel.stopListening('.mm.match.started')
    }
  }, [
    userHash,
    handleSearchUpdated,
    handleTicketCreated,
    handleTicketUpdated,
    handleTicketExpired,
    handleStarting,
    handleMatchStarted,
  ])

  return (
    <div className="flex overflow-hidden" style={{ height: 'calc(100vh - 65px)' }}>
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
