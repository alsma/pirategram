"use client"

import { useState, useEffect } from "react"
import { useFriendsStore } from "@/store/friends-store"
import { usePartyStore } from "@/store/party-store"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Skeleton } from "@/components/ui/skeleton"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu"
import { Users, UserPlus, Search, MoreVertical, Eye, Swords, UserMinus, UserCheck, UserX, Clock } from "lucide-react"

import placeholderImg from '@/assets/placeholder.svg'
import { GameMode } from '@/lib/constants/matchmaking.js'
import { UserPresenceStatus, RelationshipStatus } from '@/lib/constants/social.js'
import { toast } from 'sonner'

export default function FriendsSidebar() {
  const {
    friends,
    incomingRequests,
    outgoingRequests,
    isLoading,
    sendRequest,
    acceptRequest,
    declineRequest,
    removeFriend,
    searchForUsers
  } = useFriendsStore()

  const { sendInvite, party } = usePartyStore()

  const [searchQuery, setSearchQuery] = useState("")
  const [searchResults, setSearchResults] = useState([])
  const [isSearching, setIsSearching] = useState(false)

  // Debounced search
  useEffect(() => {
    if (searchQuery.trim().length < 2) {
      setSearchResults([])
      return
    }

    const timer = setTimeout(async () => {
      setIsSearching(true)
      const results = await searchForUsers(searchQuery)
      setSearchResults(results)
      setIsSearching(false)
    }, 300)

    return () => clearTimeout(timer)
  }, [searchQuery, searchForUsers])

  const handleSendRequest = async (friendHash) => {
    await sendRequest(friendHash)
    setSearchQuery("")
    setSearchResults([])
  }

  const handleInviteToParty = (friendHash, friendUsername) => {
    // Check if friend is already in the party
    if (party?.members?.some(member => member.userHash === friendHash)) {
      toast.error('This friend is already in your party')
      return
    }

    // Use party mode if in party, otherwise default to 2v2
    const mode = party?.mode || GameMode.TwoVsTwo
    sendInvite(friendHash, mode, friendUsername)
  }

  const getStatusIcon = (status) => {
    switch (status) {
      case UserPresenceStatus.Online:
        return <span className="h-2.5 w-2.5 bg-green-500 rounded-full" style={{ boxShadow: '0 0 8px 2px rgba(34, 197, 94, 0.5)' }} />
      case UserPresenceStatus.Away:
        return <span className="h-2.5 w-2.5 bg-amber-400 rounded-full" />
      case UserPresenceStatus.InGame:
        return <Swords className="h-3 w-3 text-burnt" />
      case UserPresenceStatus.Offline:
      default:
        return <span className="h-2.5 w-2.5 bg-gray-500 rounded-full" />
    }
  }

  const getRelationshipBadge = (relationshipStatus) => {
    switch (relationshipStatus) {
      case RelationshipStatus.Friends:
        return <span className="text-xs text-green-400">Friends</span>
      case RelationshipStatus.RequestSent:
        return <span className="text-xs text-yellow-400">Pending</span>
      case RelationshipStatus.RequestReceived:
        return <span className="text-xs text-burnt">Incoming</span>
      default:
        return null
    }
  }

  return (
    <div className="h-full flex flex-col">
      <div className="p-4 border-b border-ember/20">
        <h2 className="text-xl font-semibold text-ember-light flex items-center">
          <Users className="mr-2 h-5 w-5" /> Friends
        </h2>
      </div>

      <Tabs defaultValue="friends" className="flex-1 flex flex-col">
        <TabsList className="grid grid-cols-3 p-1 mx-4 mt-4 bg-gray-800/70 rounded-lg">
          <TabsTrigger
            value="friends"
            className="rounded-md data-[state=active]:bg-ember/40 data-[state=active]:text-white"
          >
            Friends
          </TabsTrigger>
          <TabsTrigger
            value="requests"
            className="rounded-md data-[state=active]:bg-ember/40 data-[state=active]:text-white"
          >
            Requests
          </TabsTrigger>
          <TabsTrigger
            value="search"
            className="rounded-md data-[state=active]:bg-ember/40 data-[state=active]:text-white"
          >
            Search
          </TabsTrigger>
        </TabsList>

        <TabsContent value="friends" className="flex-1 overflow-y-auto p-4">
          {isLoading ? (
            <div className="space-y-2">
              {[1, 2, 3].map((i) => (
                <div key={i} className="flex items-center justify-between p-2">
                  <div className="flex items-center">
                    <Skeleton className="h-8 w-8 rounded-full mr-2" />
                    <div>
                      <Skeleton className="h-4 w-24 mb-1" />
                      <Skeleton className="h-3 w-16" />
                    </div>
                  </div>
                  <Skeleton className="h-8 w-8" />
                </div>
              ))}
            </div>
          ) : friends.length > 0 ? (
            <div className="space-y-2">
              {friends.map((friend) => (
                <div
                  key={friend.userHash}
                  className="flex items-center justify-between p-2 rounded-lg panel-texture border border-ember/10 hover:border-ember/30"
                >
                  <div className="flex items-center">
                    <Avatar className="h-8 w-8 mr-2 border border-ember/30">
                      <AvatarImage src={placeholderImg} />
                      <AvatarFallback className="bg-gray-800 text-xs">{friend.username.substring(0, 2).toUpperCase()}</AvatarFallback>
                    </Avatar>
                    <div>
                      <div className="font-medium text-gray-200">{friend.username}</div>
                      <div className="flex items-center text-xs text-gray-400">
                        {getStatusIcon(friend.status)}
                        <span className="ml-1 capitalize">{friend.status}</span>
                      </div>
                    </div>
                  </div>

                  <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                      <Button variant="ghost" size="icon" className="h-8 w-8">
                        <MoreVertical className="h-4 w-4" />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="bg-gray-800 border-ember/20">
                      <DropdownMenuItem
                        className="flex items-center text-gray-200 disabled:opacity-50 disabled:cursor-not-allowed"
                        disabled={friend.status === UserPresenceStatus.InGame}
                        onClick={() => friend.status !== UserPresenceStatus.InGame && handleInviteToParty(friend.userHash, friend.username)}
                      >
                        <Swords className="mr-2 h-4 w-4 text-burnt" />
                        <span>Invite to Party</span>
                      </DropdownMenuItem>
                      <DropdownMenuItem className="cursor-pointer flex items-center text-gray-200">
                        <Eye className="mr-2 h-4 w-4 text-slate" />
                        <span>Spectate</span>
                      </DropdownMenuItem>
                      <DropdownMenuItem
                        className="cursor-pointer flex items-center text-ember"
                        onClick={() => removeFriend(friend.userHash)}
                      >
                        <UserMinus className="mr-2 h-4 w-4" />
                        <span>Unfriend</span>
                      </DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </div>
              ))}
            </div>
          ) : (
            <div className="h-full flex flex-col items-center justify-center text-center p-4">
              <Users className="h-12 w-12 text-gray-500 mb-4" />
              <h3 className="text-lg font-medium text-gray-300 mb-2">No Friends Yet</h3>
              <p className="text-gray-400 text-sm">Add friends to play together and see their online status.</p>
            </div>
          )}
        </TabsContent>

        <TabsContent value="requests" className="flex-1 overflow-y-auto p-4">
          {incomingRequests.length > 0 || outgoingRequests.length > 0 ? (
            <div className="space-y-4">
              {incomingRequests.length > 0 && (
                <div>
                  <h3 className="text-sm font-semibold text-gray-400 mb-2">Incoming Requests</h3>
                  <div className="space-y-2">
                    {incomingRequests.map((request) => (
                      <div
                        key={request.requesterHash}
                        className="flex items-center justify-between p-2 rounded-lg panel-texture border border-burnt/20"
                      >
                        <div className="flex items-center">
                          <Avatar className="h-8 w-8 mr-2 border border-burnt/30">
                            <AvatarImage src={placeholderImg} />
                            <AvatarFallback className="bg-gray-800 text-xs">{request.username.substring(0, 2).toUpperCase()}</AvatarFallback>
                          </Avatar>
                          <div className="font-medium text-gray-200">{request.username}</div>
                        </div>

                        <div className="flex space-x-1">
                          <Button
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8 text-green-400 hover:text-green-300 hover:bg-green-900/20"
                            onClick={() => acceptRequest(request.requesterHash)}
                          >
                            <UserCheck className="h-4 w-4" />
                          </Button>
                          <Button
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8 text-ember hover:text-ember-light hover:bg-ember/20"
                            onClick={() => declineRequest(request.requesterHash)}
                          >
                            <UserX className="h-4 w-4" />
                          </Button>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {outgoingRequests.length > 0 && (
                <div>
                  <h3 className="text-sm font-semibold text-gray-400 mb-2">Outgoing Requests</h3>
                  <div className="space-y-2">
                    {outgoingRequests.map((request) => (
                      <div
                        key={request.recipientHash}
                        className="flex items-center justify-between p-2 rounded-lg panel-texture border border-ember/10"
                      >
                        <div className="flex items-center">
                          <Avatar className="h-8 w-8 mr-2 border border-ember/30">
                            <AvatarImage src={placeholderImg} />
                            <AvatarFallback className="bg-gray-800 text-xs">{request.username.substring(0, 2).toUpperCase()}</AvatarFallback>
                          </Avatar>
                          <div className="font-medium text-gray-200">{request.username}</div>
                        </div>

                        <div className="flex items-center text-xs text-yellow-400">
                          <Clock className="h-3 w-3 mr-1" />
                          Pending
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
          ) : (
            <div className="h-full flex flex-col items-center justify-center text-center p-4">
              <UserPlus className="h-12 w-12 text-gray-500 mb-4" />
              <h3 className="text-lg font-medium text-gray-300 mb-2">No Friend Requests</h3>
              <p className="text-gray-400 text-sm">When someone sends you a friend request, it will appear here.</p>
            </div>
          )}
        </TabsContent>

        <TabsContent value="search" className="flex-1 p-4 overflow-y-auto">
          <div className="mb-4">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-5 w-5" />
              <Input
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Search by username (min 2 chars)"
                className="pl-10 bg-gray-700/50 border-gray-600 focus:border-ember focus:ring-1 focus:ring-ember/50"
              />
            </div>
          </div>

          {isSearching && (
            <div className="text-center text-gray-400 py-4">Searching...</div>
          )}

          {!isSearching && searchResults.length > 0 && (
            <div className="space-y-2">
              {searchResults.map((user) => (
                <div
                  key={user.userHash}
                  className="flex items-center justify-between p-2 rounded-lg panel-texture border border-ember/10 hover:border-ember/30"
                >
                  <div className="flex items-center">
                    <Avatar className="h-8 w-8 mr-2 border border-ember/30">
                      <AvatarImage src={placeholderImg} />
                      <AvatarFallback className="bg-gray-800 text-xs">{user.username.substring(0, 2).toUpperCase()}</AvatarFallback>
                    </Avatar>
                    <div>
                      <div className="font-medium text-gray-200">{user.username}</div>
                      {getRelationshipBadge(user.relationshipStatus)}
                    </div>
                  </div>

                  {user.relationshipStatus === RelationshipStatus.None && (
                    <Button
                      variant="ghost"
                      size="icon"
                      className="h-8 w-8 text-ember hover:text-ember-light hover:bg-ember/20"
                      onClick={() => handleSendRequest(user.userHash)}
                    >
                      <UserPlus className="h-4 w-4" />
                    </Button>
                  )}
                </div>
              ))}
            </div>
          )}

          {!isSearching && searchQuery.trim().length >= 2 && searchResults.length === 0 && (
            <div className="text-center text-gray-400 py-4">No users found</div>
          )}

          {!searchQuery.trim() && (
            <div className="panel-texture border border-ember/20 rounded-lg p-4 text-sm text-gray-300">
              <p>Search tips:</p>
              <ul className="list-disc list-inside mt-2 space-y-1 text-gray-400">
                <li>Enter at least 2 characters</li>
                <li>Search by username</li>
                <li>Add friends to play together</li>
              </ul>
            </div>
          )}
        </TabsContent>
      </Tabs>
    </div>
  )
}
