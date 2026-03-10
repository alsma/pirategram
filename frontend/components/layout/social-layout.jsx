"use client"

import { useEffect, useRef } from 'react'
import { echo } from '@laravel/echo-react'

import { useAuthStore } from "@/store/context/auth"
import { usePartyStore, selectQueueStatus } from "@/store/party-store"
import { useFriendsStore } from "@/store/friends-store"
import { CancelReason, GroupStatus } from '@/lib/constants/matchmaking.js'
import FriendsSidebar from "@/components/social/friends-sidebar"
import PartyBar from "@/components/social/party-bar"
import MatchmakingModal from "@/components/game/matchmaking-modal"
import ReadyCheckModal from "@/components/game/ready-check-modal"
import { toast } from 'sonner'

export default function SocialLayout({ children }) {
  const user = useAuthStore(s => s.user)
  const userHash = user?.hash

  const {
    party,
    handlePartyUpdated,
    handleInviteReceived,
    handleInviteDeclined,
    handleSearchUpdated,
    handleTicketCreated,
    handleTicketUpdated,
    handleTicketExpired,
    handleStarting,
    handleMatchStarted,
    restoreState,
  } = usePartyStore()
  const queueStatus = usePartyStore(selectQueueStatus)

  const {
    handleRequestReceived,
    handleRequestAccepted,
    handleRequestDeclined,
    handleFriendRemoved,
    handleStatusChanged,
    startHeartbeat,
    stopHeartbeat,
    goAway,
    restoreState: restoreFriendsState
  } = useFriendsStore()

  const partyState = usePartyStore(s => s.state)
  const prevPartyState = useRef(null)

  // Restore state on mount (only for authenticated users)
  useEffect(() => {
    if (!user) return

    restoreState()
    restoreFriendsState()
    startHeartbeat()

    return () => {
      stopHeartbeat()
    }
  }, [user, restoreState, restoreFriendsState, startHeartbeat, stopHeartbeat])

  // Away on tab hide, online on tab show (unless in a match)
  useEffect(() => {
    if (!user) return

    const onChange = () => {
      if (document.hidden) {
        goAway()
      } else if (partyState !== GroupStatus.InMatch) {
        startHeartbeat()
      }
    }

    document.addEventListener('visibilitychange', onChange)
    return () => document.removeEventListener('visibilitychange', onChange)
  }, [user, partyState, goAway, startHeartbeat])

  // Stop heartbeat during match to avoid overriding in-game status
  useEffect(() => {
    if (prevPartyState.current !== partyState) {
      if (partyState === GroupStatus.InMatch) {
        stopHeartbeat()
      } else if (prevPartyState.current === GroupStatus.InMatch) {
        startHeartbeat()
      }
      prevPartyState.current = partyState
    }
  }, [partyState, stopHeartbeat, startHeartbeat])

  // Subscribe to party channel when in a party
  useEffect(() => {
    if (!party || !party.partyHash) return

    const partyChannel = echo().private(`party.${party.partyHash}`)

    partyChannel.listen('.party.updated', (e) => {
      handlePartyUpdated(e)
    })

    return () => {
      partyChannel.stopListening('.party.updated')
    }
  }, [party?.partyHash, handlePartyUpdated])

  // Subscribe to user channel for personal events
  useEffect(() => {
    if (!userHash) return

    const channel = echo().private(`user.${userHash}`)

    // Listen to party invite events
    channel
      .listen('.party.invite.created', (e) => {
        handleInviteReceived(e)
      })
      .listen('.party.invite.declined', (e) => {
        handleInviteDeclined(e)
      })
      .listen('.party.updated', (e) => {
        handlePartyUpdated(e)
      })

    // Listen to friend events
    channel
      .listen('.friend.request.received', (e) => {
        handleRequestReceived(e)
      })
      .listen('.friend.request.accepted', (e) => {
        handleRequestAccepted(e)
      })
      .listen('.friend.request.declined', (e) => {
        handleRequestDeclined(e)
      })
      .listen('.friend.removed', (e) => {
        handleFriendRemoved(e)
      })
      .listen('.friend.status.changed', (e) => {
        handleStatusChanged(e)
      })

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
      channel.stopListening('.party.invite.created')
      channel.stopListening('.party.invite.declined')
      channel.stopListening('.party.updated')
      channel.stopListening('.friend.request.received')
      channel.stopListening('.friend.request.accepted')
      channel.stopListening('.friend.request.declined')
      channel.stopListening('.friend.removed')
      channel.stopListening('.friend.status.changed')
      channel.stopListening('.mm.search.updated')
      channel.stopListening('.mm.ticket.created')
      channel.stopListening('.mm.ticket.updated')
      channel.stopListening('.mm.ticket.expired')
      channel.stopListening('.mm.starting')
      channel.stopListening('.mm.match.started')
    }
  }, [
    userHash,
    handlePartyUpdated,
    handleInviteReceived,
    handleInviteDeclined,
    handleRequestReceived,
    handleRequestAccepted,
    handleRequestDeclined,
    handleFriendRemoved,
    handleStatusChanged,
    handleSearchUpdated,
    handleTicketCreated,
    handleTicketUpdated,
    handleTicketExpired,
    handleStarting,
    handleMatchStarted,
  ])

  // If not authenticated, just render children without social features
  if (!user) {
    return <>{children}</>
  }

  // For authenticated users, render with social features
  return (
    <div className="flex overflow-hidden" style={{ height: 'calc(100vh - 65px)' }}>
      {/* Friends Sidebar - Hidden on mobile */}
      <div className="hidden md:block w-80 border-r border-ember/20 panel-texture">
        <FriendsSidebar />
      </div>

      {/* Main Content */}
      <div className="flex-1 flex flex-col overflow-hidden">
        <main className="flex-1 overflow-y-auto">
          {children}
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
