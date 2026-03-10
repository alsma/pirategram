"use client"

import { useState } from 'react'
import { useAuthStore } from "@/store/context/auth"
import { usePartyStore } from "@/store/party-store"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Skeleton } from "@/components/ui/skeleton"
import { Crown, Users, X, UserPlus, LogOut, ChevronUp, ChevronDown, Send } from "lucide-react"
import { GameMode } from '@/lib/constants/matchmaking.js'
import { toast } from "sonner"
import placeholderImg from '@/assets/placeholder.svg'

export default function PartyBar() {
  const currentUserHash = useAuthStore(s => s.user?.hash)
  const {
    party,
    pendingInvites,
    isLoadingParty,
    sendInvite,
    acceptInvite,
    declineInvite,
    leaveParty,
    disbandParty,
    kickMember,
    promoteMember,
  } = usePartyStore()

  const [showInviteInput, setShowInviteInput] = useState(false)
  const [inviteUserHash, setInviteUserHash] = useState('')
  const [showInvites, setShowInvites] = useState(false)

  const isInParty = party !== null
  const isLeader = party && party.leaderHash === currentUserHash
  const mode = party?.mode || GameMode.TwoVsTwo // Default mode
  const isOnlyMember = party && party.members?.length === 1

  const handleSendInvite = async () => {
    if (!inviteUserHash.trim()) {
      toast.error('Please enter a user hash')
      return
    }

    await sendInvite(inviteUserHash, mode)
    setInviteUserHash('')
    setShowInviteInput(false)
  }

  const handleLeaveParty = async () => {
    await leaveParty()
  }

  const handleDisbandParty = async () => {
    await disbandParty()
  }

  const handleKick = async (memberHash) => {
    await kickMember(memberHash)
  }

  const handlePromote = async (memberHash) => {
    await promoteMember(memberHash)
  }

  const handleAcceptInvite = async (leaderHash) => {
    await acceptInvite(leaderHash)
    setShowInvites(false)
  }

  const handleDeclineInvite = async (leaderHash) => {
    await declineInvite(leaderHash)
  }

  return (
    <div className="panel-texture border-t border-ember/20 p-4">
      <div className="max-w-4xl mx-auto">
        {/* Party Members Display */}
        <div className="flex items-center justify-between mb-2">
          <div className="flex items-center">
            <div className="bg-ember/20 rounded-full p-2 mr-4 glow-ember">
              <Users className="h-5 w-5 text-ember-light" />
            </div>

            <div className="flex space-x-3">
              {isLoadingParty ? (
                // Show skeleton while loading
                <>
                  <Skeleton className="h-12 w-12 rounded-full" />
                  <Skeleton className="h-12 w-12 rounded-full" />
                </>
              ) : isInParty ? (
                party.members?.map((member) => (
                  <div key={member.userHash} className="relative group">
                    <Avatar className="h-12 w-12 border-2 border-ember/30 shadow-md">
                      <AvatarImage src={placeholderImg} />
                      <AvatarFallback className="bg-gray-800 text-ember">
                        {member.username.substring(0, 2)}
                      </AvatarFallback>
                    </Avatar>

                    {member.userHash === party.leaderHash && (
                      <Crown className="absolute -top-2 -right-2 h-5 w-5 text-brawl animate-float" />
                    )}

                    {isLeader && member.userHash !== party.leaderHash && (
                      <div className="absolute -top-1 -right-1 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col space-y-1">
                        <Button
                          variant="ghost"
                          size="icon"
                          className="h-5 w-5 rounded-full bg-blue-500/80 hover:bg-blue-500 p-0"
                          onClick={() => handlePromote(member.userHash)}
                          title="Promote to leader"
                        >
                          <Crown className="h-3 w-3 text-white" />
                        </Button>
                        <Button
                          variant="ghost"
                          size="icon"
                          className="h-5 w-5 rounded-full bg-ember/80 hover:bg-ember p-0"
                          onClick={() => handleKick(member.userHash)}
                          title="Kick from party"
                        >
                          <X className="h-3 w-3 text-white" />
                        </Button>
                      </div>
                    )}

                    <div className="absolute bottom-[-20px] left-0 right-0 text-xs text-center text-gray-400 whitespace-nowrap">
                      {member.username}
                    </div>
                  </div>
                ))
              ) : (
                <div className="h-12 flex items-center text-gray-400 text-sm">
                  Not in a party
                </div>
              )}
              {(!isInParty || (party.members?.length || 0) < (party.maxPlayers || 4)) && (
                <button
                  className="h-12 w-12 rounded-full border-2 border-dashed border-ember/30 flex items-center justify-center text-ember hover:border-ember/50 hover:text-ember-light transition-all"
                  onClick={() => setShowInviteInput(!showInviteInput)}
                  title="Invite player"
                >
                  <UserPlus className="h-5 w-5" />
                </button>
              )}
            </div>
          </div>

          <div className="flex items-center space-x-3">
            {/* Pending Invites Indicator */}
            {pendingInvites.length > 0 && (
              <Button
                variant="outline"
                size="sm"
                className="relative"
                onClick={() => setShowInvites(!showInvites)}
              >
                <Users className="h-4 w-4 mr-2" />
                {pendingInvites.length} Invite{pendingInvites.length > 1 ? 's' : ''}
                <span className="absolute -top-1 -right-1 bg-ember text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                  {pendingInvites.length}
                </span>
                {showInvites ? <ChevronDown className="ml-2 h-4 w-4" /> : <ChevronUp className="ml-2 h-4 w-4" />}
              </Button>
            )}

            {isInParty && isLeader && isOnlyMember && (
              <Button
                variant="outline"
                size="sm"
                onClick={handleDisbandParty}
                className="border-ember/30 hover:border-ember/50 text-ember hover:text-ember-light"
              >
                <X className="mr-2 h-4 w-4" />
                Disband Party
              </Button>
            )}

            {isInParty && (!isLeader || (isLeader && !isOnlyMember)) && (
              <Button
                variant="outline"
                size="sm"
                onClick={handleLeaveParty}
                className="border-ember/30 hover:border-ember/50"
              >
                <LogOut className="mr-2 h-4 w-4" />
                Leave Party
              </Button>
            )}
          </div>
        </div>

        {/* Invite Input */}
        {showInviteInput && (
          <div className="mt-4 p-4 bg-gray-800/50 rounded-lg border border-ember/20">
            <div className="flex items-center space-x-2">
              <Input
                type="text"
                placeholder="Enter user hash to invite..."
                value={inviteUserHash}
                onChange={(e) => setInviteUserHash(e.target.value)}
                onKeyPress={(e) => e.key === 'Enter' && handleSendInvite()}
                className="flex-1 bg-gray-900 border-ember/30 focus:border-ember"
              />
              <Button onClick={handleSendInvite} className="bg-ember hover:bg-ember-light">
                <Send className="h-4 w-4 mr-2" />
                Send Invite
              </Button>
              <Button variant="ghost" onClick={() => setShowInviteInput(false)}>
                <X className="h-4 w-4" />
              </Button>
            </div>
          </div>
        )}

        {/* Pending Invites List */}
        {showInvites && pendingInvites.length > 0 && (
          <div className="mt-4 p-4 bg-gray-800/50 rounded-lg border border-ember/20 space-y-2">
            <h3 className="text-sm font-semibold text-ember-light mb-2">Pending Invites</h3>
            {pendingInvites.map((invite) => (
              <div
                key={invite.leaderHash}
                className="flex items-center justify-between p-3 bg-gray-900/50 rounded-lg"
              >
                <div>
                  <p className="text-sm font-medium text-gray-200">{invite.leaderUsername}</p>
                  <p className="text-xs text-gray-400">Mode: {invite.mode}</p>
                </div>
                <div className="flex space-x-2">
                  <Button
                    size="sm"
                    onClick={() => handleAcceptInvite(invite.leaderHash)}
                    className="bg-green-700 hover:bg-green-600"
                  >
                    Accept
                  </Button>
                  <Button
                    size="sm"
                    variant="ghost"
                    onClick={() => handleDeclineInvite(invite.leaderHash)}
                    className="text-ember hover:text-ember-light"
                  >
                    Decline
                  </Button>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}
