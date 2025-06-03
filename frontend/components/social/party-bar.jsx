"use client"

import { usePartyStore } from "@/store/party-store"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Button } from "@/components/ui/button"
import { Crown, Users, X, Check, Search, XCircle } from "lucide-react"
import { toast } from "sonner"

import placeholderImg from '@/assets/placeholder.svg'

export default function PartyBar() {
  const { party, leaderId, queueStatus, isReady, addToParty, removeFromParty, setReady, startQueue, cancelQueue } =
    usePartyStore()

  const isLeader = leaderId === "current-user-id" // In a real app, compare with current user ID

  const handleStartQueue = () => {
    startQueue()
    toast.info("Searching for a match...")
  }

  const handleCancelQueue = () => {
    cancelQueue()
    toast.info("Search cancelled")
  }

  const handleReady = () => {
    setReady(true)
    toast.success("Ready!")
  }

  const handleLeaveParty = () => {
    // In a real app, this would remove the current user
    toast.info("Left the party")
  }

  return (
    <div className="panel-texture border-t border-ember/20 p-4">
      <div className="max-w-4xl mx-auto flex items-center justify-between">
        <div className="flex items-center">
          <div className="bg-ember/20 rounded-full p-2 mr-4 glow-ember">
            <Users className="h-5 w-5 text-ember-light" />
          </div>

          <div className="flex space-x-3">
            {party.map((member, index) => (
              <div key={index} className="relative">
                <Avatar className="h-12 w-12 border-2 border-ember/30 shadow-md">
                  <AvatarImage src={member.avatar || placeholderImg} />
                  <AvatarFallback className="bg-gray-800 text-ember">{member.handle.substring(0, 2)}</AvatarFallback>
                </Avatar>

                {member.id === leaderId && (
                  <Crown className="absolute -top-2 -right-2 h-5 w-5 text-brawl animate-float" />
                )}

                {isLeader && member.id !== leaderId && (
                  <Button
                    variant="ghost"
                    size="icon"
                    className="absolute -top-2 -right-2 h-5 w-5 rounded-full bg-ember/80 hover:bg-ember p-0"
                    onClick={() => removeFromParty(member.id)}
                  >
                    <X className="h-3 w-3" />
                  </Button>
                )}
              </div>
            ))}

            {party.length < 4 && (
              <button
                className="h-12 w-12 rounded-full border-2 border-dashed border-ember/30 flex items-center justify-center text-ember hover:border-ember/50 hover:text-ember-light"
                onClick={() => addToParty({ id: "new-member", handle: "Friend", avatar: "" })}
              >
                <Users className="h-5 w-5" />
              </button>
            )}
          </div>
        </div>

        <div className="flex items-center space-x-3">
          {queueStatus === "idle" && (
            <>
              {isLeader ? (
                <button className="dota-button" onClick={handleStartQueue}>
                  <Search className="mr-2 h-4 w-4 inline-block" />
                  Search Match
                </button>
              ) : (
                <>
                  <button
                    className="bg-green-700 hover:bg-green-600 text-white font-medium px-4 py-2 rounded-md shadow-md border border-green-800/50 transition-all duration-200"
                    onClick={handleReady}
                  >
                    <Check className="mr-2 h-4 w-4 inline-block" />
                    Ready
                  </button>

                  <button
                    className="bg-gray-700 hover:bg-gray-600 text-ember hover:text-ember-light font-medium px-4 py-2 rounded-md shadow-md border border-ember/30 transition-all duration-200"
                    onClick={handleLeaveParty}
                  >
                    <XCircle className="mr-2 h-4 w-4 inline-block" />
                    Leave
                  </button>
                </>
              )}
            </>
          )}

          {queueStatus === "queuing" && isLeader && (
            <button
              className="bg-gray-700 hover:bg-gray-600 text-ember hover:text-ember-light font-medium px-4 py-2 rounded-md shadow-md border border-ember/30 transition-all duration-200"
              onClick={handleCancelQueue}
            >
              <XCircle className="mr-2 h-4 w-4 inline-block" />
              Cancel Search
            </button>
          )}

          {queueStatus === "lobby-ready" && !isReady && (
            <button
              className="bg-green-700 hover:bg-green-600 text-white font-medium px-4 py-2 rounded-md shadow-md border border-green-800/50 transition-all duration-200"
              onClick={handleReady}
            >
              <Check className="mr-2 h-4 w-4 inline-block" />
              Accept
            </button>
          )}

          {queueStatus === "in-match" && <div className="text-ember-light font-medium">Match in progress</div>}
        </div>
      </div>
    </div>
  )
}
